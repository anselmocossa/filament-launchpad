<?php

namespace Filament\Launchpad\Policies;

use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Support\LaunchpadPermission;

/**
 * See SpacePolicy for the SOFT-integration rationale; this policy applies
 * the same rules to the `Action:Page` permission family.
 */
class PagePolicy
{
    public function viewAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'ViewAny:Page');
    }

    public function view(mixed $user, Page $page): bool
    {
        return LaunchpadPermission::check($user, 'View:Page');
    }

    public function create(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'Create:Page');
    }

    public function update(mixed $user, Page $page): bool
    {
        return LaunchpadPermission::check($user, 'Update:Page');
    }

    public function delete(mixed $user, Page $page): bool
    {
        return LaunchpadPermission::check($user, 'Delete:Page');
    }

    public function deleteAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'DeleteAny:Page');
    }

    public function restore(mixed $user, Page $page): bool
    {
        return LaunchpadPermission::check($user, 'Restore:Page');
    }

    public function forceDelete(mixed $user, Page $page): bool
    {
        return LaunchpadPermission::check($user, 'ForceDelete:Page');
    }
}
