<?php

namespace Filament\Launchpad\Policies;

use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadPermission;

/**
 * SOFT-integrated with bezhansalleh/filament-shield + spatie/laravel-permission
 * via LaunchpadPermission: absent spatie/laravel-permission, every ability is
 * granted (today's behaviour unchanged). Present, an ability is granted to
 * the Shield `super_admin` role or to a user holding the matching
 * `Action:Space` permission (Shield's default pascal-case, `:`-separated
 * naming, e.g. `ViewAny:Space`).
 *
 * Registered in LaunchpadServiceProvider only when spatie/laravel-permission
 * is present, so the models (living outside `App\Models`, where Laravel's
 * policy auto-discovery cannot find them) actually get their authorization
 * enforced by Filament's Resource pages/actions.
 */
class SpacePolicy
{
    public function viewAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'ViewAny:Space');
    }

    public function view(mixed $user, Space $space): bool
    {
        return LaunchpadPermission::check($user, 'View:Space');
    }

    public function create(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'Create:Space');
    }

    public function update(mixed $user, Space $space): bool
    {
        return LaunchpadPermission::check($user, 'Update:Space');
    }

    public function delete(mixed $user, Space $space): bool
    {
        return LaunchpadPermission::check($user, 'Delete:Space');
    }

    public function deleteAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'DeleteAny:Space');
    }

    public function restore(mixed $user, Space $space): bool
    {
        return LaunchpadPermission::check($user, 'Restore:Space');
    }

    public function forceDelete(mixed $user, Space $space): bool
    {
        return LaunchpadPermission::check($user, 'ForceDelete:Space');
    }
}
