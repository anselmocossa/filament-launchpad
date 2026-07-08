<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Legacy launchpad branding values kept for backwards compatibility.
    | The visible panel brand should be configured through Filament's native
    | panel branding APIs instead.
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
    | Most applications can keep the default.
    |
    */
    'tile_size' => 'normal',

    /*
    |--------------------------------------------------------------------------
    | Tile sizing (layout)
    |--------------------------------------------------------------------------
    |
    | Controls whether KPI/shortcut tiles keep a fixed square size when alone
    | in a section ('fixed', default) or stretch to fill the row ('fluid').
    | Prefer: LaunchpadPlugin::make()->tileSizing('fixed')
    |
    */
    'tile_sizing' => 'fixed',

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
