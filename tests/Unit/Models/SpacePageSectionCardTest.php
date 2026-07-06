<?php

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;

it('creates a space with a page, a section and a card', function () {
    $space = Space::create(['label' => 'Vendas', 'icon' => 'heroicon-o-shopping-cart', 'sort' => 1]);
    $page = $space->pages()->create(['label' => 'Visão Geral', 'sort' => 1]);
    $section = $page->sections()->create(['title' => 'Terminal', 'sort' => 1]);
    $card = $section->cards()->create(['title' => 'Abrir Caixa', 'type' => 'shortcut', 'sort' => 1]);

    expect(Space::count())->toBe(1)
        ->and(Page::count())->toBe(1)
        ->and(Section::count())->toBe(1)
        ->and(Card::count())->toBe(1)
        ->and($card->section->id)->toBe($section->id)
        ->and($section->page->id)->toBe($page->id)
        ->and($page->space->id)->toBe($space->id);
});

it('cascades deletes from space down to cards', function () {
    $space = Space::create(['label' => 'Vendas', 'sort' => 1]);
    $page = $space->pages()->create(['label' => 'Visão Geral', 'sort' => 1]);
    $section = $page->sections()->create(['title' => 'Terminal', 'sort' => 1]);
    $section->cards()->create(['title' => 'Abrir Caixa', 'sort' => 1]);

    $space->delete();

    expect(Page::count())->toBe(0)
        ->and(Section::count())->toBe(0)
        ->and(Card::count())->toBe(0);
});

it('orders pages, sections and cards by sort', function () {
    $space = Space::create(['label' => 'Vendas', 'sort' => 1]);
    $space->pages()->create(['label' => 'Segunda', 'sort' => 2]);
    $space->pages()->create(['label' => 'Primeira', 'sort' => 1]);

    expect($space->pages()->pluck('label')->all())->toBe(['Primeira', 'Segunda']);

    $page = $space->pages()->first();
    $page->sections()->create(['title' => 'Secção B', 'sort' => 2]);
    $page->sections()->create(['title' => 'Secção A', 'sort' => 1]);

    expect($page->sections()->pluck('title')->all())->toBe(['Secção A', 'Secção B']);

    $section = $page->sections()->first();
    $section->cards()->create(['title' => 'Card B', 'sort' => 2]);
    $section->cards()->create(['title' => 'Card A', 'sort' => 1]);

    expect($section->cards()->pluck('title')->all())->toBe(['Card A', 'Card B']);
});
