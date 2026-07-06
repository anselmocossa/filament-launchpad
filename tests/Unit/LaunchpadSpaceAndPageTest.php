<?php

use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\Launchpad\TileGroup;

it('auto-slugs a space id from its label', function () {
    expect(LaunchpadSpace::make('Ponto de Venda')->getId())->toBe('ponto-de-venda');
});

it('accepts an explicit space id', function () {
    expect(LaunchpadSpace::make('Início', 'inicio-custom')->getId())->toBe('inicio-custom');
});

it('auto-slugs a page id from its label', function () {
    expect(LaunchpadPage::make('Visão Geral')->getId())->toBe('visao-geral');
});

it('accepts an explicit page id', function () {
    expect(LaunchpadPage::make('Vendas', 'vendas-custom')->getId())->toBe('vendas-custom');
});

it('builds a space with several pages, each with their own sections', function () {
    $space = LaunchpadSpace::make('Ponto de Venda')->pages([
        LaunchpadPage::make('Visão Geral')->sections([
            TileGroup::make('Terminal')->tiles([Tile::make('Abrir Caixa')]),
        ]),
        LaunchpadPage::make('Vendas')->sections([
            TileGroup::make('Histórico')->tiles([Tile::make('Vendas do Dia')]),
        ]),
    ]);

    $pages = $space->getPages();

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->getLabel())->toBe('Visão Geral')
        ->and($pages[0]->getSections()[0]->getTitle())->toBe('Terminal')
        ->and($pages[1]->getLabel())->toBe('Vendas')
        ->and($pages[1]->getSections()[0]->getTitle())->toBe('Histórico');
});

it('serializes a space with nested pages and sections', function () {
    $space = LaunchpadSpace::make('Ponto de Venda')->pages([
        LaunchpadPage::make('Visão Geral')->sections([
            TileGroup::make('Terminal')->tiles([Tile::make('Abrir Caixa')]),
        ]),
    ]);

    $data = $space->toArray();

    expect($data['id'])->toBe('ponto-de-venda')
        ->and($data['label'])->toBe('Ponto de Venda')
        ->and($data['pages'])->toHaveCount(1)
        ->and($data['pages'][0]['label'])->toBe('Visão Geral')
        ->and($data['pages'][0]['sections'][0]['title'])->toBe('Terminal')
        ->and($data['pages'][0]['sections'][0]['tiles'][0]['t'])->toBe('Abrir Caixa');
});

it('degrades gracefully when a space has no pages', function () {
    $space = LaunchpadSpace::make('Vazio');

    expect($space->getPages())->toBe([])
        ->and($space->toArray()['pages'])->toBe([]);
});

it('degrades gracefully when a page has no sections', function () {
    $page = LaunchpadPage::make('Vazia');

    expect($page->getSections())->toBe([])
        ->and($page->toArray()['sections'])->toBe([]);
});

it('defaults to a null icon and accepts one fluently for a space', function () {
    $space = LaunchpadSpace::make('Ponto de Venda');
    expect($space->getIcon())->toBeNull();

    $space->icon('heroicon-o-shopping-cart');
    expect($space->getIcon())->toBe('heroicon-o-shopping-cart')
        ->and($space->toArray()['icon'])->toBe('heroicon-o-shopping-cart');
});

it('defaults to a null icon and accepts one fluently for a page', function () {
    $page = LaunchpadPage::make('Visão Geral');
    expect($page->getIcon())->toBeNull();

    $page->icon('heroicon-o-home');
    expect($page->getIcon())->toBe('heroicon-o-home')
        ->and($page->toArray()['icon'])->toBe('heroicon-o-home');
});
