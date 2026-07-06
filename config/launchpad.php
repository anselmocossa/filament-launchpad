<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Basic branding shown on the launchpad header: the panel/product name
    | and an optional logo path/URL. When no logo is given, a badge with
    | initials (derived from the name, or given explicitly) is shown instead.
    |
    | Prefer configuring branding through the fluent plugin API instead:
    |     LaunchpadPlugin::make()->brand(name: 'Loja Demo', initials: 'LD')
    |
    */
    'branding' => [
        'name' => 'Launchpad',
        'logo' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accent color
    |--------------------------------------------------------------------------
    |
    | Applied to the logo badge, the active tab's underline, and focus states.
    | Prefer: LaunchpadPlugin::make()->accentColor('#16a34a')
    |
    */
    'accent_color' => '#16a34a',

    /*
    |--------------------------------------------------------------------------
    | Dark header
    |--------------------------------------------------------------------------
    |
    | Switches the header/tab bar to a dark palette while keeping the page
    | body light. Prefer: LaunchpadPlugin::make()->darkHeader()
    |
    */
    'dark_header' => false,

    /*
    |--------------------------------------------------------------------------
    | Tile size
    |--------------------------------------------------------------------------
    |
    | 'normal' (176px square tiles) or 'compact' (150px square tiles).
    | Prefer: LaunchpadPlugin::make()->tileSize('compact')
    |
    */
    'tile_size' => 'normal',

    /*
    |--------------------------------------------------------------------------
    | Tabs, groups & tiles
    |--------------------------------------------------------------------------
    |
    | Tabs (top-level sections), their tile groups, and each tile's title,
    | icon, badge, KPI and navigation target are defined preferably through
    | the fluent plugin API rather than this config file, since tiles often
    | need closures (for live KPIs and custom actions) that cannot be
    | expressed as plain config arrays:
    |
    |     use Filament\Launchpad\Launchpad\{LaunchpadTab, TileGroup, Tile};
    |
    |     LaunchpadPlugin::make()->tabs([
    |         LaunchpadTab::make('Início')->groups([
    |             TileGroup::make('Favoritos')->tiles([
    |                 Tile::make('Vendas Hoje')
    |                     ->subtitle('Ponto de Venda')
    |                     ->icon('heroicon-o-banknotes')
    |                     ->kpi(fn () => Sale::today()->count())
    |                     ->trend('+0% vs ontem', 'success')
    |                     ->url('/admin/sales'),
    |             ]),
    |         ]),
    |     ]);
    |
    */

];
