<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'section_id')->orderBy('sort');
    }
}
