<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Card extends Model
{
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_cards';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
    ];

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
}
