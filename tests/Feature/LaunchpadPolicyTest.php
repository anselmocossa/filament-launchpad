<?php

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Exercises the plugin's own policies (registered by LaunchpadServiceProvider
 * via Gate::policy() when spatie/laravel-permission is present) through
 * Laravel's Gate — proving they are actually wired up for models living
 * outside `App\Models`, not just callable in isolation.
 */
function makeLaunchpadHierarchy(): array
{
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);
    $card = Card::query()->create([
        'section_id' => $section->id,
        'title' => 'Vendas Hoje',
        'type' => 'kpi',
        'kpi_value' => '10',
        'target_type' => 'none',
        'sort' => 0,
    ]);

    return compact('space', 'page', 'section', 'card');
}

it('grants every ability on every model when spatie/laravel-permission is treated as unavailable', function () {
    ['space' => $space, 'page' => $page, 'section' => $section, 'card' => $card] = makeLaunchpadHierarchy();

    config(['permission.models.role' => 'App\\Models\\DoesNotExistRole']);

    try {
        $user = TestUser::create(['name' => 'Qualquer']);

        expect($user->can('view', $space))->toBeTrue()
            ->and($user->can('update', $page))->toBeTrue()
            ->and($user->can('delete', $section))->toBeTrue()
            ->and($user->can('create', Card::class))->toBeTrue();
    } finally {
        config(['permission.models.role' => Role::class]);
    }
});

it('respects Shield-style permissions once spatie/laravel-permission is available', function () {
    ['space' => $space] = makeLaunchpadHierarchy();

    Permission::create(['name' => 'View:Space', 'guard_name' => 'web']);
    Permission::create(['name' => 'Update:Space', 'guard_name' => 'web']);

    $withView = TestUser::create(['name' => 'Com View']);
    $withView->givePermissionTo('View:Space');

    $withoutAny = TestUser::create(['name' => 'Sem Nada']);

    expect($withView->can('view', $space))->toBeTrue()
        ->and($withView->can('update', $space))->toBeFalse()
        ->and($withoutAny->can('view', $space))->toBeFalse();
});

it('always grants a super_admin every ability on every model', function () {
    ['space' => $space, 'page' => $page, 'section' => $section, 'card' => $card] = makeLaunchpadHierarchy();

    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = TestUser::create(['name' => 'Super']);
    $superAdmin->assignRole('super_admin');

    expect($superAdmin->can('view', $space))->toBeTrue()
        ->and($superAdmin->can('delete', $page))->toBeTrue()
        ->and($superAdmin->can('update', $section))->toBeTrue()
        ->and($superAdmin->can('create', Card::class))->toBeTrue();
});

it('grants a guest (no user) even once spatie/laravel-permission is available, mirroring pre-Phase E.2 behaviour', function () {
    ['space' => $space] = makeLaunchpadHierarchy();

    expect(Gate::forUser(null)->allows('view', $space))->toBeTrue();
});
