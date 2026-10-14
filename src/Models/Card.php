<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\BelongsToLaunchpadTenant;
use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Filament\Launchpad\Support\LaunchpadBookmarks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use BelongsToLaunchpadTenant;
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_cards';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Card $card): void {
            $card->userCards()->delete();
        });
    }

    /**
     * Cards are a reusable catalog: the same Card can be referenced by
     * several Sections (authentic Fiori model). `sort` lives on the pivot
     * (`launchpad_section_card`) — it is per-section placement, not a
     * property of the card itself.
     *
     * @return BelongsToMany<Section, static>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'launchpad_section_card')
            ->withPivot('sort')
            ->withTimestamps()
            ->orderByPivot('sort');
    }

    /**
     * @return HasMany<UserCard, static>
     */
    public function userCards(): HasMany
    {
        return $this->hasMany(UserCard::class, 'card_id');
    }

    /**
     * Leaves out personal bookmarks (Support\LaunchpadBookmarks): they belong to
     * one user and must not show up in shared listings, catalogs or searches.
     */
    public function scopeWithoutBookmarks($query)
    {
        return $query->where(fn ($query) => $query
            ->whereNull('library_key')
            ->orWhere('library_key', 'not like', LaunchpadBookmarks::KEY_PREFIX.'%'));
    }
}
