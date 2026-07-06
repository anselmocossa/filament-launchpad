<?php

namespace Filament\Launchpad\Policies;

use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Support\LaunchpadPermission;

/**
 * See SpacePolicy for the SOFT-integration rationale; this policy applies
 * the same rules to the `Action:Section` permission family.
 */
class SectionPolicy
{
    public function viewAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'ViewAny:Section');
    }

    public function view(mixed $user, Section $section): bool
    {
        return LaunchpadPermission::check($user, 'View:Section');
    }

    public function create(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'Create:Section');
    }

    public function update(mixed $user, Section $section): bool
    {
        return LaunchpadPermission::check($user, 'Update:Section');
    }

    public function delete(mixed $user, Section $section): bool
    {
        return LaunchpadPermission::check($user, 'Delete:Section');
    }

    public function deleteAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'DeleteAny:Section');
    }

    public function restore(mixed $user, Section $section): bool
    {
        return LaunchpadPermission::check($user, 'Restore:Section');
    }

    public function forceDelete(mixed $user, Section $section): bool
    {
        return LaunchpadPermission::check($user, 'ForceDelete:Section');
    }
}
