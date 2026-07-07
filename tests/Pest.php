<?php

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Tests\Support\TestUser;
use Filament\Launchpad\Tests\TestCase;
use Illuminate\Support\Facades\DB;
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
