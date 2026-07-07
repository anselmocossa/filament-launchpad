<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadVisibility;
use Filament\Launchpad\Tests\Support\TestUser;
use Spatie\Permission\Models\Role;

function makeHierarchy(): array
{
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);
    $card = $section->cards()->create([
        'title' => 'Vendas Hoje',
        'type' => 'kpi',
        'kpi_value' => '10',
        'target_type' => 'none',
    ]);

    return compact('space', 'page', 'section', 'card');
}

// ---------------------------------------------------------------------
// canView(): base rules.
// ---------------------------------------------------------------------

it('is visible to anyone (including a guest) when it has no roles attached', function () {
    ['card' => $card] = makeHierarchy();

    expect(LaunchpadVisibility::canView($card))->toBeTrue()
        ->and(LaunchpadVisibility::canView($card, null))->toBeTrue();
});

it('is visible to a user holding the required role, hidden from one who does not, and always visible to a super_admin', function () {
    ['card' => $card] = makeHierarchy();

    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);
    Role::create(['name' => 'Caixa', 'guard_name' => 'web']);
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

    $card->visibilityRoles()->sync([$vendedor->id]);
    $card->refresh();

    $userWithRole = TestUser::create(['name' => 'Com Papel']);
    $userWithRole->assignRole('Vendedor');

    $userWithoutRole = TestUser::create(['name' => 'Sem Papel']);
    $userWithoutRole->assignRole('Caixa');

    $superAdmin = TestUser::create(['name' => 'Super']);
    $superAdmin->assignRole('super_admin');

    expect(LaunchpadVisibility::canView($card, $userWithRole))->toBeTrue()
        ->and(LaunchpadVisibility::canView($card, $userWithoutRole))->toBeFalse()
        ->and(LaunchpadVisibility::canView($card, $superAdmin))->toBeTrue()
        ->and(LaunchpadVisibility::canView($card, null))->toBeFalse();
});

it('never throws when spatie/laravel-permission is treated as unavailable, and always allows viewing', function () {
    ['card' => $card] = makeHierarchy();

    // Simulate the SOFT-integration "package absent" branch by pointing the
    // resolved role model at a class that does not exist, without actually
    // uninstalling the package (kept installed for the rest of the suite).
    config(['permission.models.role' => 'App\\Models\\DoesNotExistRole']);

    try {
        expect(LaunchpadVisibility::spatieAvailable())->toBeFalse()
            ->and(fn () => LaunchpadVisibility::canView($card))->not->toThrow(Throwable::class)
            ->and(LaunchpadVisibility::canView($card))->toBeTrue();
    } finally {
        config(['permission.models.role' => Role::class]);
    }
});

// ---------------------------------------------------------------------
// Cascade: an ancestor whose children are all restricted disappears too.
// ---------------------------------------------------------------------

it('hides a space whose every page is restricted to a role the user does not have', function () {
    ['space' => $space, 'page' => $page] = makeHierarchy();

    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);
    $page->visibilityRoles()->sync([$vendedor->id]);

    $plugin = LaunchpadPlugin::make();

    $userWithoutRole = TestUser::create(['name' => 'Sem Papel']);
    auth()->login($userWithoutRole);

    expect($plugin->getSpaces())->toBe([]);

    auth()->logout();
});

it('keeps the space visible when at least one of its pages remains visible', function () {
    ['space' => $space, 'page' => $restrictedPage] = makeHierarchy();

    $otherPage = Page::query()->create(['space_id' => $space->id, 'label' => 'Outra Página', 'sort' => 1]);
    Section::query()->create(['page_id' => $otherPage->id, 'title' => 'S', 'sort' => 0])
        ->cards()->create(['title' => 'Atalho', 'type' => 'shortcut', 'target_type' => 'none', 'sort' => 0]);

    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);
    $restrictedPage->visibilityRoles()->sync([$vendedor->id]);

    $userWithoutRole = TestUser::create(['name' => 'Sem Papel']);
    auth()->login($userWithoutRole);

    $spaces = LaunchpadPlugin::make()->getSpaces();

    expect($spaces)->toHaveCount(1)
        ->and($spaces[0]->getPages())->toHaveCount(1)
        ->and($spaces[0]->getPages()[0]->getLabel())->toBe('Outra Página');

    auth()->logout();
});

// ---------------------------------------------------------------------
// NEGATIVE: a restricted card is never surfaced to a user without the role.
// ---------------------------------------------------------------------

it('never exposes a restricted card to a user without the role, even though the space/page/section are all unrestricted', function () {
    ['card' => $card] = makeHierarchy();

    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);
    $card->visibilityRoles()->sync([$vendedor->id]);

    $userWithoutRole = TestUser::create(['name' => 'Sem Papel']);
    auth()->login($userWithoutRole);

    $spaces = LaunchpadPlugin::make()->getSpaces();

    // The card was the section's only card, so the whole chain collapses:
    // no card -> no visible section -> no visible page -> no visible space.
    expect($spaces)->toBe([]);

    $userWithRole = TestUser::create(['name' => 'Com Papel']);
    $userWithRole->assignRole('Vendedor');
    auth()->login($userWithRole);

    $spacesForAllowedUser = LaunchpadPlugin::make()->getSpaces();

    expect($spacesForAllowedUser)->toHaveCount(1)
        ->and($spacesForAllowedUser[0]->getPages()[0]->getSections()[0]->getTiles())->toHaveCount(1);

    auth()->logout();
});
