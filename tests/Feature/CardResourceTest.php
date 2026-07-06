<?php

use Filament\Launchpad\Filament\Resources\CardResource\Pages\ListCards;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

it('renders the flat Cards list page and shows an existing card', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);

    Card::query()->create([
        'section_id' => $section->id,
        'title' => 'Vendas Hoje',
        'subtitle' => 'Ponto de Venda',
        'type' => 'kpi',
        'target_type' => 'none',
        'sort' => 0,
    ]);

    Livewire::test(ListCards::class)
        ->assertOk()
        ->assertSee('Vendas Hoje');
});

it('edits a card through the flat Cards list page', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);

    $card = Card::query()->create([
        'section_id' => $section->id,
        'title' => 'Vendas Hoje',
        'subtitle' => 'Ponto de Venda',
        'type' => 'kpi',
        'target_type' => 'none',
        'sort' => 0,
    ]);

    Livewire::test(ListCards::class)
        ->callTableAction('edit', $card, data: [
            'title' => 'Vendas Hoje Actualizado',
            'type' => 'kpi',
            'target_type' => 'none',
        ])
        ->assertHasNoTableActionErrors();

    expect($card->refresh()->title)->toBe('Vendas Hoje Actualizado');
});
