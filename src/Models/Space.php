<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Space extends Model
{
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_spaces';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'space_id')->orderBy('sort');
    }
}
