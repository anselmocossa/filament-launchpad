<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Support\LaunchpadScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the OVERLAY layer: a deviation from the parent's template, owned
 * by a scope (a tenant or a user — see Support\LaunchpadScope).
 *
 * Two kinds of row live here:
 *   is_hidden = false → this scope ADDED this card to this section
 *   is_hidden = true  → this scope HID a parent card in this section (tombstone)
 *
 * Swapping a card is therefore two rows: a tombstone over the parent's card and
 * a normal row for the replacement. The parent's own pinned cards live in the
 * launchpad_section_card pivot and are never copied in here — which is what
 * lets the parent keep pushing template changes into slots a scope has not
 * touched.
 *
 * Kept named UserCard (and on the same table) through Phase H on purpose: this
 * is a published plugin, and renaming a table for tidiness would break every
 * consumer's raw queries for no functional gain.
 */
class UserCard extends Model
{
    protected $table = 'launchpad_user_cards';

    protected $guarded = [];

    protected $casts = [
        'section_id' => 'integer',
        'card_id' => 'integer',
        'sort' => 'integer',
        'is_hidden' => 'boolean',
    ];

    /**
     * `scope_key` is derived, never supplied. Keeping it in the model rather
     * than at the call sites means a row inserted directly — by a consumer's
     * own code, a seeder or a test — is still addressable by the overlay
     * queries, instead of becoming an orphan that renders nowhere and can never
     * be deleted through the UI.
     */
    protected static function booted(): void
    {
        static::saving(function (UserCard $userCard): void {
            $userCard->scope_key = LaunchpadScope::key(
                blank($userCard->tenant_id) ? null : (string) $userCard->tenant_id,
                blank($userCard->user_id) ? null : (string) $userCard->user_id,
            );
        });
    }

    /**
     * Rows belonging to exactly one scope — never a prefix match, so the tenant
     * layer can never pick up a user's rows or vice versa.
     */
    public function scopeForScope($query, ?string $tenantId, ?string $userId)
    {
        return $query->where('scope_key', LaunchpadScope::key($tenantId, $userId));
    }

    /**
     * Every overlay row that applies to one viewer: their tenant's layer plus
     * their own personal layer.
     */
    public function scopeVisibleTo($query, ?string $tenantId, ?string $userId)
    {
        $keys = [LaunchpadScope::key($tenantId, null)];

        if (filled($userId)) {
            $keys[] = LaunchpadScope::key($tenantId, $userId);
        }

        return $query->whereIn('scope_key', array_unique($keys));
    }

    public function scopeAdditions($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeTombstones($query)
    {
        return $query->where('is_hidden', true);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
