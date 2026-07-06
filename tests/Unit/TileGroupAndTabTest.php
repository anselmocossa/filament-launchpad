<?php

use Filament\Launchpad\Launchpad\LaunchpadTab;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\Launchpad\TileGroup;

it('serializes a tile group with its tiles', function () {
    $group = TileGroup::make('Favoritos')->tiles([
        Tile::make('A'),
        Tile::make('B'),
    ]);

    $data = $group->toArray();

    expect($data['title'])->toBe('Favoritos')
        ->and($data['tiles'])->toHaveCount(2)
        ->and($data['tiles'][0]['t'])->toBe('A');
});

it('auto-slugs a tab id from its label', function () {
    $tab = LaunchpadTab::make('Ponto de Venda');

    expect($tab->getId())->toBe('ponto-de-venda');
});

it('accepts an explicit tab id', function () {
    $tab = LaunchpadTab::make('Início', 'inicio-custom');

    expect($tab->getId())->toBe('inicio-custom');
});

it('groups() wraps the given sections in a single default page sharing the tab id/label (retro-compat)', function () {
    $tab = LaunchpadTab::make('Início')->groups([
        TileGroup::make('Favoritos')->tiles([Tile::make('A')]),
    ]);

    $pages = $tab->getPages();

    expect($pages)->toHaveCount(1)
        ->and($pages[0]->getId())->toBe('inicio')
        ->and($pages[0]->getLabel())->toBe('Início')
        ->and($pages[0]->getSections())->toHaveCount(1)
        ->and($tab->getGroups())->toHaveCount(1) // legacy getter still works
        ->and($tab->getGroups()[0]->getTitle())->toBe('Favoritos');
});

it('serializes a tab (space) with its page(s) and their sections', function () {
    $tab = LaunchpadTab::make('Início')->groups([
        TileGroup::make('Favoritos')->tiles([Tile::make('A')]),
    ]);

    $data = $tab->toArray();

    expect($data['id'])->toBe('inicio')
        ->and($data['label'])->toBe('Início')
        ->and($data['pages'])->toHaveCount(1)
        ->and($data['pages'][0]['sections'])->toHaveCount(1)
        ->and($data['pages'][0]['sections'][0]['title'])->toBe('Favoritos');
});
