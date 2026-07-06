<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_cards';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
