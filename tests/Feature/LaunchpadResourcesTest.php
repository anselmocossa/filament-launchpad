<?php

use Filament\Launchpad\Filament\Resources\SectionResource\Pages\EditSection;
use Filament\Launchpad\Filament\Resources\SectionResource\RelationManagers\CardsRelationManager;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\ListSpaces;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

it('renders the Spaces list page', function () {
    Space::query()->create(['label' => 'Ponto de Venda', 'icon' => 'heroicon-o-computer-desktop', 'sort' => 0]);

    Livewire::test(ListSpaces::class)->assertOk();
});

it('exposes Páginas and Cards header actions on the Spaces list page', function () {
    Livewire::test(ListSpaces::class)
        ->assertActionExists('pages')
        ->assertActionExists('cards');
});

it('creates a Space through the resource form', function () {
    Livewire::test(ListSpaces::class)
        ->callAction('create', data: [
            'label' => 'Recursos Humanos',
            'icon' => 'heroicon-o-users',
            'sort' => 1,
        ])
        ->assertHasNoActionErrors();

    expect(Space::query()->where('label', 'Recursos Humanos')->exists())->toBeTrue();
});

it('creates a kpi card through the CardsRelationManager', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);

    Livewire::test(CardsRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->callTableAction('create', data: [
            'title' => 'Vendas Hoje',
            'subtitle' => 'Ponto de Venda',
            'icon' => 'heroicon-o-banknotes',
            'type' => 'kpi',
            'kpi_value' => '128',
            'unit' => 'un',
            'trend' => '+12% vs ontem',
            'trend_color' => 'success',
            'target_type' => 'none',
        ])
        ->assertHasNoTableActionErrors();

    $card = Card::query()->where('title', 'Vendas Hoje')->first();

    expect($card)->not->toBeNull()
        ->and($card->type)->toBe('kpi')
        ->and($card->kpi_value)->toBe('128')
        ->and($section->cards()->whereKey($card->id)->exists())->toBeTrue();
});
