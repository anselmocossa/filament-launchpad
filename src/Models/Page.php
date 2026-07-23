<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\BelongsToLaunchpadTenant;
use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use BelongsToLaunchpadTenant;
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_pages';

    protected $guarded = [];

    protected $casts = [
        'is_hidden' => 'boolean',
        'sort' => 'integer',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'space_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'page_id')->orderBy('sort');
    }
}
