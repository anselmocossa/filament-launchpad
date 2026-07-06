<?php

use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\CardResource\Pages\ListCards;
use Filament\Launchpad\Filament\Resources\PageResource\Pages\BuildLayout;
use Filament\Launchpad\Filament\Resources\SectionResource\Pages\EditSection;
use Filament\Launchpad\Filament\Resources\SectionResource\RelationManagers\CardsRelationManager;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\CreateSpace;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\EditSpace;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Tests\Support\TestUser;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

// ---------------------------------------------------------------------
// SpaceResource: the "Visível para (papéis)" field appears, saves and
// reloads via Filament's own Select::relationship() machinery.
// ---------------------------------------------------------------------

it('saves the chosen roles on a new Space and reloads them on the edit form', function () {
    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);
    $caixa = Role::create(['name' => 'Caixa', 'guard_name' => 'web']);

    Livewire::test(CreateSpace::class)
        ->fillForm([
            'label' => 'Espaço Restrito',
            'sort' => 0,
            'visibilityRoles' => [$vendedor->id, $caixa->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $space = Space::query()->where('label', 'Espaço Restrito')->firstOrFail();

    expect($space->visibilityRoles()->pluck('name')->sort()->values()->all())->toBe(['Caixa', 'Vendedor']);

    Livewire::test(EditSpace::class, ['record' => $space->getRouteKey()])
        ->assertFormSet(['visibilityRoles' => [$vendedor->id, $caixa->id]]);
});

it('clears the roles on a Space when the field is emptied, making it visible to everyone again', function () {
    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);
    $space = Space::query()->create(['label' => 'Espaço', 'sort' => 0]);
    $space->visibilityRoles()->sync([$vendedor->id]);

    expect($space->refresh()->isRestricted())->toBeTrue();

    Livewire::test(EditSpace::class, ['record' => $space->getRouteKey()])
        ->fillForm(['visibilityRoles' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($space->refresh()->isRestricted())->toBeFalse();
});

// ---------------------------------------------------------------------
// Card form, shared across its three hosts (HasCardForm): the relation
// syncs correctly whichever entry point is used to edit the card.
// ---------------------------------------------------------------------

it('saves visibility roles on a card edited through the flat CardResource list', function () {
    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);

    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);
    $card = Card::query()->create([
        'section_id' => $section->id,
        'title' => 'Vendas Hoje',
        'type' => 'kpi',
        'target_type' => 'none',
        'sort' => 0,
    ]);

    Livewire::test(ListCards::class)
        ->callTableAction('edit', $card, data: [
            'title' => 'Vendas Hoje',
            'type' => 'kpi',
            'target_type' => 'none',
            'visibilityRoles' => [$vendedor->id],
        ])
        ->assertHasNoTableActionErrors();

    expect($card->refresh()->visibleToRoleIds())->toBe([$vendedor->id]);
});

it('saves visibility roles on a card edited through the CardsRelationManager', function () {
    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);

    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);
    $card = Card::query()->create([
        'section_id' => $section->id,
        'title' => 'Vendas Hoje',
        'type' => 'kpi',
        'target_type' => 'none',
        'sort' => 0,
    ]);

    Livewire::test(CardsRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->callTableAction('edit', $card, data: [
            'title' => 'Vendas Hoje',
            'type' => 'kpi',
            'target_type' => 'none',
            'visibilityRoles' => [$vendedor->id],
        ])
        ->assertHasNoTableActionErrors();

    expect($card->refresh()->visibleToRoleIds())->toBe([$vendedor->id]);
});

it('saves visibility roles on a card edited through the drag&drop Builder modal', function () {
    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);

    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $card = Card::query()->create(['section_id' => $section->id, 'title' => 'Card', 'type' => 'kpi', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->callAction('editCard', data: [
            'title' => 'Card',
            'type' => 'kpi',
            'target_type' => 'none',
            'visibilityRoles' => [$vendedor->id],
        ], arguments: ['card' => $card->id])
        ->assertHasNoActionErrors();

    expect($card->refresh()->visibleToRoleIds())->toBe([$vendedor->id]);
});

// ---------------------------------------------------------------------
// Global search: a restricted card never surfaces to a user without role.
// ---------------------------------------------------------------------

it('omits a restricted card from global search results for a user without the role', function () {
    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);

    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);
    $card = Card::query()->create([
        'section_id' => $section->id,
        'title' => 'Vendas Secretas',
        'type' => 'kpi',
        'target_type' => 'none',
        'sort' => 0,
    ]);
    $card->visibilityRoles()->sync([$vendedor->id]);

    $userWithoutRole = TestUser::create(['name' => 'Sem Papel']);
    auth()->login($userWithoutRole);

    expect(CardResource::getGlobalSearchResults('Secretas'))->toHaveCount(0);

    $userWithRole = TestUser::create(['name' => 'Com Papel']);
    $userWithRole->assignRole('Vendedor');
    auth()->login($userWithRole);

    expect(CardResource::getGlobalSearchResults('Secretas'))->toHaveCount(1);

    auth()->logout();
});
