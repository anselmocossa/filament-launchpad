<?php

use Filament\Launchpad\Pages\EditHome;
use Filament\Launchpad\Pages\Launchpad;
use Filament\Launchpad\Support\LaunchpadPermission;
use Filament\Launchpad\Tests\Support\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// ---------------------------------------------------------------------
// LaunchpadPermission::check() — the shared SOFT gate.
// ---------------------------------------------------------------------

it('grants everything when spatie/laravel-permission is treated as unavailable', function () {
    config(['permission.models.role' => 'App\\Models\\DoesNotExistRole']);

    try {
        expect(LaunchpadPermission::check(null, 'View:Space'))->toBeTrue()
            ->and(LaunchpadPermission::check(new TestUser, 'View:Space'))->toBeTrue();
    } finally {
        config(['permission.models.role' => Role::class]);
    }
});

it('grants a guest (no user) even once spatie/laravel-permission is available, mirroring pre-Phase E.2 behaviour (a real panel already sits behind its own auth middleware)', function () {
    expect(LaunchpadPermission::check(null, 'View:Space'))->toBeTrue();
});

it('grants a user when the requested permission has not been generated yet', function () {
    $user = TestUser::create(['name' => 'Sem Permissão Gerada']);

    expect(LaunchpadPermission::check($user, 'View:EditHome'))->toBeTrue();
});

it('grants a user holding the permission and denies one who does not', function () {
    Permission::create(['name' => 'View:Space', 'guard_name' => 'web']);

    $withPermission = TestUser::create(['name' => 'Com Permissão']);
    $withPermission->givePermissionTo('View:Space');

    $withoutPermission = TestUser::create(['name' => 'Sem Permissão']);

    expect(LaunchpadPermission::check($withPermission, 'View:Space'))->toBeTrue()
        ->and(LaunchpadPermission::check($withoutPermission, 'View:Space'))->toBeFalse();
});

it('always grants a super_admin, regardless of the specific permission', function () {
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = TestUser::create(['name' => 'Super']);
    $superAdmin->assignRole('super_admin');

    expect(LaunchpadPermission::check($superAdmin, 'View:Space'))->toBeTrue()
        ->and(LaunchpadPermission::check($superAdmin, 'Delete:Card'))->toBeTrue();
});

// ---------------------------------------------------------------------
// Launchpad::canAccess() / EditHome::canAccess() — Shield-aware pages.
// ---------------------------------------------------------------------

it('lets the Launchpad home page through when spatie/laravel-permission is unavailable', function () {
    config(['permission.models.role' => 'App\\Models\\DoesNotExistRole']);

    try {
        expect(Launchpad::canAccess())->toBeTrue();
    } finally {
        config(['permission.models.role' => Role::class]);
    }
});

it('gates the Launchpad home page behind View:Launchpad once spatie/laravel-permission is available', function () {
    Permission::create(['name' => 'View:Launchpad', 'guard_name' => 'web']);

    $withPermission = TestUser::create(['name' => 'Com Permissão']);
    $withPermission->givePermissionTo('View:Launchpad');
    auth()->login($withPermission);
    expect(Launchpad::canAccess())->toBeTrue();
    auth()->logout();

    $withoutPermission = TestUser::create(['name' => 'Sem Permissão']);
    auth()->login($withoutPermission);
    expect(Launchpad::canAccess())->toBeFalse();
    auth()->logout();

    // A guest (no logged-in user at all) is granted — see LaunchpadPermission
    // for why: a real panel's own `auth` middleware is the actual guest gate.
    expect(Launchpad::canAccess())->toBeTrue();
});

it('always lets a super_admin into the Launchpad home page', function () {
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = TestUser::create(['name' => 'Super']);
    $superAdmin->assignRole('super_admin');
    auth()->login($superAdmin);

    expect(Launchpad::canAccess())->toBeTrue();

    auth()->logout();
});

it('gates EditHome behind View:EditHome the same way', function () {
    Permission::create(['name' => 'View:EditHome', 'guard_name' => 'web']);

    $withPermission = TestUser::create(['name' => 'Com Permissão']);
    $withPermission->givePermissionTo('View:EditHome');
    auth()->login($withPermission);
    expect(EditHome::canAccess())->toBeTrue();
    auth()->logout();

    $withoutPermission = TestUser::create(['name' => 'Sem Permissão']);
    auth()->login($withoutPermission);
    expect(EditHome::canAccess())->toBeFalse();
    auth()->logout();
});
