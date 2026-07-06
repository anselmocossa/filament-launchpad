<?php

use Filament\Launchpad\Tests\Support\TestUser;
use Filament\Launchpad\Tests\TestCase;
use Spatie\Permission\Models\Role;

uses(TestCase::class)->in('Feature', 'Unit');

function actingAsLaunchpadAdmin(): TestUser
{
    Role::query()->firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    $user = TestUser::query()->create(['name' => 'Admin']);
    $user->assignRole('super_admin');

    auth()->login($user);

    return $user;
}
