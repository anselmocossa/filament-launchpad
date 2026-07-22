<?php

namespace Filament\Launchpad\Models;

use Filament\Launchpad\Models\Concerns\BelongsToLaunchpadTenant;
use Filament\Launchpad\Models\Concerns\HasLaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Space extends Model
{
    use BelongsToLaunchpadTenant;
    use HasLaunchpadVisibility;

    protected $table = 'launchpad_spaces';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * The default "Início" Space is the launchpad home and can never be
     * deleted — the guard here backs up the UI (hidden delete buttons) and the
     * policy so it holds even from tinker or a stray forceDelete.
     */
    protected static function booted(): void
    {
        static::deleting(function (Space $space): void {
            if ($space->is_default) {
                throw new RuntimeException('The default Launchpad home Space cannot be deleted.');
            }
        });
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'space_id')->orderBy('sort');
    }

    public function scopeForPanel($query, ?string $panelId)
    {
        if (blank($panelId)) {
            return $query;
        }

        return $query->where(function ($query) use ($panelId): void {
            $query->where('panel_id', $panelId)
                ->orWhereNull('panel_id');
        });
    }
}
