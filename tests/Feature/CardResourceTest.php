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

    $section->cards()->create([
        'title' => 'Vendas Hoje',
        'subtitle' => 'Ponto de Venda',
        'type' => 'kpi',
        'target_type' => 'none',
    ]);

    Livewire::test(ListCards::class)
        ->assertOk()
        ->assertSee('Vendas Hoje');
});

it('edits a card through the flat Cards list page', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);

    $card = $section->cards()->create([
        'title' => 'Vendas Hoje',
        'subtitle' => 'Ponto de Venda',
        'type' => 'kpi',
        'target_type' => 'none',
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

it('creates a card through the flat Cards list page, unattached to any section', function () {
    Livewire::test(ListCards::class)
        ->callAction('create', data: [
            'title' => 'Novo Card',
            'type' => 'kpi',
            'target_type' => 'none',
        ])
        ->assertHasNoActionErrors();

    $card = Card::query()->where('title', 'Novo Card')->first();

    expect($card)->not->toBeNull()
        ->and($card->sections()->count())->toBe(0);
});

it('has a Delete action on the flat Cards list table that permanently removes the card and its pivot rows', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);

    $card = $section->cards()->create([
        'title' => 'Vendas Hoje',
        'type' => 'kpi',
        'target_type' => 'none',
    ]);

    Livewire::test(ListCards::class)
        ->callTableAction('delete', $card)
        ->assertHasNoTableActionErrors();

    expect(Card::query()->whereKey($card->id)->exists())->toBeFalse()
        ->and($section->cards()->count())->toBe(0);
});

it('shows the sections a card belongs to in the "Sections" column, including several at once', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $sectionA = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção A', 'sort' => 0]);
    $sectionB = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção B', 'sort' => 1]);

    $card = $sectionA->cards()->create(['title' => 'Partilhado', 'type' => 'kpi']);
    $sectionB->cards()->attach($card->id, ['sort' => 0]);

    Livewire::test(ListCards::class)
        ->assertSee('Secção A')
        ->assertSee('Secção B');
});
