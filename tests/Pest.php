<?php

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Tests\Support\TestUser;
use Filament\Launchpad\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
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

/**
 * A plain tenant user: authenticated, but NOT the main. The
 * `Manage:LaunchpadPrimary` permission is created so it genuinely EXISTS (a
 * missing permission would soft-degrade to "allowed"), and the user is left
 * without it — so it may customise its own layer but never the shared template.
 */
function actingAsLaunchpadTenantUser(): TestUser
{
    Permission::query()->firstOrCreate([
        'name' => 'Manage:LaunchpadPrimary',
        'guard_name' => 'web',
    ]);

    $user = TestUser::query()->create(['name' => 'Tenant User']);

    auth()->login($user);

    return $user;
}

/**
 * The `sort` used to live on `launchpad_cards` itself; now that a Card is a
 * reusable catalog item (belongsToMany with Section), placement order lives
 * on the `launchpad_section_card` pivot instead — this reads that value
 * directly, without relying on Eloquent relation state.
 */
function pivotSort(Section $section, Card $card): ?int
{
    return DB::table('launchpad_section_card')
        ->where('section_id', $section->id)
        ->where('card_id', $card->id)
        ->value('sort');
}
