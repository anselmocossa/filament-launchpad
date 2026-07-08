<?php

use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Launchpad\LaunchpadTab;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\Launchpad\TileGroup;
use Filament\Launchpad\LaunchpadPlugin;

it('defaults to the design accent color, normal tile size and light header', function () {
    $plugin = LaunchpadPlugin::make();

    expect($plugin->getAccentColor())->toBe('#16a34a')
        ->and($plugin->getTileSize())->toBe('normal')
        ->and($plugin->getTileWidth())->toBe(176)
        ->and($plugin->getTileSizing())->toBe('fixed')
        ->and($plugin->isDarkHeader())->toBeFalse()
        ->and($plugin->getNotificationCount())->toBe(0);
});

it('collapses empty grid tracks in fluid tileSizing while keeping fixed tile width', function () {
    $plugin = LaunchpadPlugin::make()->tileSizing('fluid');

    expect($plugin->getTileSizing())->toBe('fluid');
});

it('falls back to fixed tile sizing for unknown values', function () {
    $plugin = LaunchpadPlugin::make()->tileSizing('invalid');

    expect($plugin->getTileSizing())->toBe('fixed');
});

it('applies theming overrides fluently', function () {
    $plugin = LaunchpadPlugin::make()
        ->accentColor('#0a6ed1')
        ->darkHeader()
        ->tileSize('compact')
        ->notificationCount(3);

    expect($plugin->getAccentColor())->toBe('#0a6ed1')
        ->and($plugin->isDarkHeader())->toBeTrue()
        ->and($plugin->getTileSize())->toBe('compact')
        ->and($plugin->getTileWidth())->toBe(150)
        ->and($plugin->getNotificationCount())->toBe(3);
});

it('maps darkHeader(true) to the design dark header palette', function () {
    $colors = LaunchpadPlugin::make()->darkHeader(true)->getHeaderColors();

    expect($colors)->toBe([
        'headerBg' => '#1f2937',
        'headerBorder' => '#374151',
        'headerText' => '#f9fafb',
        'headerMuted' => '#9ca3af',
        'searchBg' => '#111827',
    ]);
});

it('maps darkHeader(false) (default) to the design light header palette', function () {
    $colors = LaunchpadPlugin::make()->getHeaderColors();

    expect($colors)->toBe([
        'headerBg' => '#ffffff',
        'headerBorder' => '#e5e7eb',
        'headerText' => '#111827',
        'headerMuted' => '#6b7280',
        'searchBg' => '#f9fafb',
    ]);
});

it('derives brand initials from the brand name when none are given', function () {
    $plugin = LaunchpadPlugin::make()->brand('Loja Demo');

    expect($plugin->getBrandName())->toBe('Loja Demo')
        ->and($plugin->getBrandInitials())->toBe('LD')
        ->and($plugin->getBrandLogo())->toBeNull();
});

it('uses explicit brand initials and logo when given', function () {
    $plugin = LaunchpadPlugin::make()->brand('Loja Demo', logo: '/logo.png', initials: 'X');

    expect($plugin->getBrandInitials())->toBe('X')
        ->and($plugin->getBrandLogo())->toBe('/logo.png');
});

it('spaces() stores LaunchpadSpace/LaunchpadPage instances directly and getSpaces() returns them', function () {
    $plugin = LaunchpadPlugin::make()->spaces([
        LaunchpadSpace::make('Ponto de Venda')->pages([
            LaunchpadPage::make('Visão Geral')->sections([]),
            LaunchpadPage::make('Vendas')->sections([]),
        ]),
    ]);

    $spaces = $plugin->getSpaces();

    expect($spaces)->toHaveCount(1)
        ->and($spaces[0]->getId())->toBe('ponto-de-venda')
        ->and($spaces[0]->getPages())->toHaveCount(2);
});

it('tabs() normalizes legacy LaunchpadTab configs into spaces with a single default page (retro-compat)', function () {
    $plugin = LaunchpadPlugin::make()->tabs([
        LaunchpadTab::make('Início')->groups([
            TileGroup::make('Favoritos')->tiles([Tile::make('A')]),
        ]),
    ]);

    $spaces = $plugin->getSpaces();

    expect($spaces)->toHaveCount(1)
        ->and($spaces[0]->getId())->toBe('inicio')
        ->and($spaces[0]->getPages())->toHaveCount(1)
        ->and($spaces[0]->getPages()[0]->getSections())->toHaveCount(1)
        ->and($plugin->getTabs())->toBe($plugin->getSpaces()); // getTabs() is a deprecated alias
});

it('provides a sensible default card library (KPI + Atalho presets)', function () {
    $library = LaunchpadPlugin::make()->getCardLibrary();

    expect($library)->toHaveCount(2)
        ->and(array_column($library, 'key'))->toBe(['kpi', 'atalho'])
        ->and($library[0]['type'])->toBe('kpi')
        ->and($library[1]['type'])->toBe('shortcut');
});

it('merges card library presets across calls, overriding the default', function () {
    $plugin = LaunchpadPlugin::make()
        ->cardLibrary([['key' => 'vendas', 'title' => 'Vendas', 'type' => 'kpi']])
        ->cardLibrary([['key' => 'clientes', 'title' => 'Clientes', 'type' => 'shortcut']]);

    $library = $plugin->getCardLibrary();

    expect($library)->toHaveCount(2)
        ->and(array_column($library, 'key'))->toBe(['vendas', 'clientes']);
});
