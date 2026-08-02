<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Launchpad\Support\LaunchpadResourceAccess;

/**
 * Adds the host's installation-level gate on top of the resource's own policy.
 *
 * Filament's default `canAccess()` returns `canViewAny()`, i.e. the policy.
 * Replacing that with the host predicate would silently drop the permission
 * check — a resource would become readable by anyone the host lets through.
 * So both are required, and the policy call is delegated to `parent::` rather
 * than restated, so it keeps tracking whatever Filament's default does.
 */
trait GatedByResourceAccess
{
    public static function canAccess(): bool
    {
        return LaunchpadResourceAccess::allows(static::class)
            && parent::canAccess();
    }
}
