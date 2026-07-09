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
    | Controls tile grid layout: 'fixed' keeps tiles at their configured
    | square size; 'fluid' stretches them equally to fill the row width.
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

    /*
    |--------------------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------------------
    |
    | Used by `make:launchpad-kpi` and `make:launchpad-widget`. Left null,
    | both commands generate into the generic default: app/Launchpad/Kpis
    | and app/Launchpad/Widgets (namespaces App\Launchpad\Kpis / Widgets).
    |
    | Set BOTH values below to instead place generated classes inside your
    | own module structure — e.g.:
    |
    |     'module_path' => app_path('Filament/Store/Modules'),
    |     'module_namespace' => 'App\\Filament\\Store\\Modules',
    |
    | With these set, `--module=Sales` (or picking "Sales" from the
    | interactive prompt) places a KPI at
    | {module_path}/Sales/Kpis/{Name}.php with namespace
    | {module_namespace}\Sales\Kpis (and similarly for Widgets).
    |
    */
    'generators' => [
        'module_path' => null,
        'module_namespace' => null,
    ],

];
