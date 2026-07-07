<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Section extends Model
{
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_sections';

    protected $guarded = [];

    protected $casts = [
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
     * that also references it.
     *
     * @return BelongsToMany<Card, static>
     */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'launchpad_section_card')
            ->withPivot('sort')
            ->withTimestamps()
            ->orderByPivot('sort');
    }
}
