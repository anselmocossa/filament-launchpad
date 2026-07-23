<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\BelongsToLaunchpadTenant;
use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use BelongsToLaunchpadTenant;
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_sections';

    protected $guarded = [];

    protected $casts = [
        'is_hidden' => 'boolean',
        'sort' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * A Section REFERENCES cards from the global catalog — it does not own
     * them. `sort` lives on the pivot (`launchpad_section_card`) and is this
     * section's own placement of the card, independent of any other section
     * that also references it. `is_pinned` marks a card that is injected to
     * every user (fixed) versus one that only sits in the catalog for users to
     * add themselves.
     *
     * @return BelongsToMany<Card, static>
     */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'launchpad_section_card')
            ->withPivot(['sort', 'is_pinned'])
            ->withTimestamps()
            ->orderByPivot('sort');
    }

    /**
     * Only the cards the admin pinned to this section — the fixed set every
     * user always sees, in the admin's order.
     *
     * @return BelongsToMany<Card, static>
     */
    public function pinnedCards(): BelongsToMany
    {
        return $this->cards()->wherePivot('is_pinned', true);
    }

    /**
     * Per-user personalisation rows for this section (each a catalog card a
     * user added to their own home).
     *
     * @return HasMany<UserCard, static>
     */
    public function userCards(): HasMany
    {
        return $this->hasMany(UserCard::class, 'section_id')->orderBy('sort');
    }
}
