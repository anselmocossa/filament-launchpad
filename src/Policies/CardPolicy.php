<?php

namespace Filament\Launchpad\Policies;

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Support\LaunchpadPermission;

/**
 * See SpacePolicy for the SOFT-integration rationale; this policy applies
 * the same rules to the `Action:Card` permission family.
 */
class CardPolicy
{
    public function viewAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'ViewAny:Card');
    }

    public function view(mixed $user, Card $card): bool
    {
        return LaunchpadPermission::check($user, 'View:Card');
    }

    public function create(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'Create:Card');
    }

    public function update(mixed $user, Card $card): bool
    {
        return LaunchpadPermission::check($user, 'Update:Card');
    }

    public function delete(mixed $user, Card $card): bool
    {
        return LaunchpadPermission::check($user, 'Delete:Card');
    }

    public function deleteAny(mixed $user): bool
    {
        return LaunchpadPermission::check($user, 'DeleteAny:Card');
    }

    public function restore(mixed $user, Card $card): bool
    {
        return LaunchpadPermission::check($user, 'Restore:Card');
    }

    public function forceDelete(mixed $user, Card $card): bool
    {
        return LaunchpadPermission::check($user, 'ForceDelete:Card');
    }
}
