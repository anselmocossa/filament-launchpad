<?php

namespace Filament\Launchpad\Models\Concerns;

use Filament\Launchpad\Support\LaunchpadVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Adds a role-scoped visibility pivot to a launchpad model (Space/Page/
 * Section/Card). SOFT on spatie/laravel-permission: `visibilityRoles()`
 * itself only ever gets called when the package's Role model actually
 * exists (guarded via LaunchpadVisibility::spatieAvailable(), consulted by
 * every method below) — never on a plain install without the package.
 */
trait HasLaunchpadVisibility
{
    /**
     * @return MorphToMany<Model, static>
     */
    public function visibilityRoles(): MorphToMany
    {
        return $this->morphToMany(
            LaunchpadVisibility::roleModelClass(),
            'visible',
            'launchpad_role_visibility',
            'visible_id',
            'role_id',
        );
    }

    /**
     * Whether this item has been scoped to one or more roles. False (=
     * everyone sees it) when spatie/laravel-permission is not installed, or
     * when no roles were ever attached.
     */
    public function isRestricted(): bool
    {
        if (! LaunchpadVisibility::spatieAvailable()) {
            return false;
        }

        return $this->visibilityRoles()->exists();
    }

    /**
     * @return array<int, int|string>
     */
    public function visibleToRoleIds(): array
    {
        if (! LaunchpadVisibility::spatieAvailable()) {
            return [];
        }

        $relation = $this->visibilityRoles();

        return $relation->pluck($relation->getRelated()->getQualifiedKeyName())->all();
    }
}
