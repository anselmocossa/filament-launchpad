<?php

use Filament\Launchpad\Filament\Resources\SectionResource\Pages\EditSection;
use Filament\Launchpad\Filament\Resources\SectionResource\RelationManagers\CardsRelationManager;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Livewire\Livewire;

function createKpiSection(): Section
{
    $space = Space::create(['label' => 'Espaço', 'sort' => 1]);
    $page = Page::create(['space_id' => $space->id, 'label' => 'Página', 'sort' => 1]);

    return Section::create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 1]);
}

it('resolves a registered kpi source live', function () {
    $section = createKpiSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => 'x',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpiSources(['x' => fn () => 42]);
    $spaces = $plugin->getSpaces();

    $tile = $spaces[0]->getPages()[0]->getSections()[0]->getTiles()[0];

    expect($tile->toArray()['kpi'])->toBe('42');
});

it('degrades a throwing kpi source to an em dash instead of crashing', function () {
    $section = createKpiSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => 'boom',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpiSources(['boom' => fn () => throw new Exception('erro')]);
    $spaces = $plugin->getSpaces();

    $tile = $spaces[0]->getPages()[0]->getSections()[0]->getTiles()[0];

    expect($tile->toArray()['kpi'])->toBe('—');
});

it('lets the live kpi source win over a fixed kpi value', function () {
    $section = createKpiSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => 'x',
        'kpi_value' => '999',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpiSources(['x' => fn () => 42]);
    $spaces = $plugin->getSpaces();

    $tile = $spaces[0]->getPages()[0]->getSections()[0]->getTiles()[0];

    expect($tile->toArray()['kpi'])->toBe('42');
});

it('falls back to the fixed kpi value when no source is set', function () {
    $section = createKpiSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_value' => '123',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make();
    $spaces = $plugin->getSpaces();

    $tile = $spaces[0]->getPages()[0]->getSections()[0]->getTiles()[0];

    expect($tile->toArray()['kpi'])->toBe('123');
});

it('falls back to the fixed kpi value when the referenced source is not registered', function () {
    $section = createKpiSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => 'naoexiste',
        'kpi_value' => '55',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make();
    $spaces = $plugin->getSpaces();

    $tile = $spaces[0]->getPages()[0]->getSections()[0]->getTiles()[0];

    expect($tile->toArray()['kpi'])->toBe('55');
});

it('does not crash when the referenced source is missing and there is no fixed value either', function () {
    $section = createKpiSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => 'naoexiste',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make();
    $spaces = $plugin->getSpaces();

    $tile = $spaces[0]->getPages()[0]->getSections()[0]->getTiles()[0];

    expect($tile->toArray()['hasKpi'])->toBeFalse()
        ->and($tile->toArray()['kpi'])->toBeNull();
});

it('lets an admin create a fixed kpi card without registering code sources', function () {
    actingAsLaunchpadAdmin();

    $section = createKpiSection();

    Livewire::test(CardsRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->callTableAction('create', data: [
            'title' => 'Receita Hoje',
            'subtitle' => 'Ponto de Venda',
            'type' => 'kpi',
            'kpi_value' => '128',
            'unit' => 'MT',
            'trend' => '+12%',
            'trend_color' => 'success',
            'target_type' => 'none',
        ])
        ->assertHasNoTableActionErrors();

    $card = Card::query()->where('title', 'Receita Hoje')->first();
    $plugin = LaunchpadPlugin::make();
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($card)->not->toBeNull()
        ->and($card->kpi_source)->toBeNull()
        ->and($card->kpi_value)->toBe('128')
        ->and($tile['hasKpi'])->toBeTrue()
        ->and($tile['kpi'])->toBe('128')
        ->and($tile['unit'])->toBe('MT')
        ->and($tile['trend'])->toBe('+12%');
});

it('lets an admin choose a registered live kpi source from the card form', function () {
    actingAsLaunchpadAdmin();

    $section = createKpiSection();

    LaunchpadPlugin::get()->kpiSources([
        'receita_hoje' => fn (): int => 987,
    ]);

    Livewire::test(CardsRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->callTableAction('create', data: [
            'title' => 'Receita Hoje',
            'type' => 'kpi',
            'kpi_source' => 'receita_hoje',
            'kpi_value' => '0',
            'unit' => 'MT',
            'target_type' => 'none',
        ])
        ->assertHasNoTableActionErrors();

    $card = Card::query()->where('title', 'Receita Hoje')->first();
    $plugin = LaunchpadPlugin::make()->kpiSources([
        'receita_hoje' => fn (): int => 987,
    ]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($card)->not->toBeNull()
        ->and($card->kpi_source)->toBe('receita_hoje')
        ->and($tile['kpi'])->toBe('987')
        ->and($tile['unit'])->toBe('MT');
});
