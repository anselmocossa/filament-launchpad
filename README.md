# Filament Launchpad

[![Latest Version on Packagist](https://img.shields.io/packagist/v/filamentphp/launchpad.svg?style=flat-square)](https://packagist.org/packages/filamentphp/launchpad)
[![Total Downloads](https://img.shields.io/packagist/dt/filamentphp/launchpad.svg?style=flat-square)](https://packagist.org/packages/filamentphp/launchpad)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg?style=flat-square)](#)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

Transform your Filament panel homepage into a Fiori-style launchpad: a grid of grouped tiles, each with an icon, a title, and a live counter — inspired by the SAP Fiori Launchpad experience.

## Introduction

Filament Launchpad adds a launchpad home page to your panel — a sub-nav of tabs plus a grid of grouped, clickable KPI tiles — so users land on an at-a-glance overview of the areas and counters that matter to them.

The launchpad renders **inside the native Filament page shell**: your panel's topbar, sidebar, user menu, breadcrumbs, and dark-mode toggle stay exactly as they are. The plugin only adds its tab sub-nav (a full-width second navbar glued directly under the topbar, holding the app tabs) and the tile grid to the page content area — it does not replace the panel chrome.

## Requirements

- PHP 8.2+
- Filament 4.0+ or 5.0+
- Laravel (Illuminate contracts/support) 11.0+, 12.0+, or 13.0+

## Installation

You can install the package via composer:

```bash
composer require filamentphp/launchpad
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="launchpad-config"
```

## Usage

Register the plugin in your panel provider and describe your tabs, groups, and
tiles with the fluent API — no Blade required on your side:

```php
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Launchpad\{LaunchpadTab, TileGroup, Tile};

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            LaunchpadPlugin::make()
                ->accentColor('#16a34a')   // active tab underline
                ->tileSize('normal')       // 'normal' (176px) | 'compact' (150px)
                ->tabs([
                    LaunchpadTab::make('Início')->groups([
                        TileGroup::make('Favoritos')->tiles([
                            // KPI variant: a tile becomes a KPI tile as soon as ->kpi() is set
                            Tile::make('Sales Today')
                                ->subtitle('Point of Sale')
                                ->icon('heroicon-o-banknotes')
                                ->kpi(fn () => Order::whereDate('created_at', today())->count())
                                ->unit('orders')
                                ->trend('+12% vs yesterday', 'success') // success|danger|warning|gray
                                ->url('/admin/orders'),

                            // Icon-only variant: no ->kpi()
                            Tile::make('Products')
                                ->subtitle('Catalog')
                                ->icon('heroicon-o-cube')
                                ->badge('24')
                                ->resource(ProductResource::class),
                        ]),
                    ]),
                ]),
        );
}
```

The plugin registers a `Launchpad` page at the panel root (`/`), so it becomes
the panel home. If your panel also registers the default `Dashboard` page,
remove it (or give it another slug) so the launchpad can own `/`.

### Tile targets

Each tile can point at a destination, resolved when the tile is clicked:

- `->url('/path')` or `->url(fn () => ...)`
- `->resource(MyResource::class)` → the resource index URL
- `->page(MyPage::class)` → the page URL
- `->action(fn () => ...)` → run a closure instead of navigating

A tile with no target is inert (it dispatches a Filament notification). KPI
closures are resolved safely on render: if one throws, the tile degrades to
`—` rather than breaking the page (no fabricated data).

## Configuration

The published config file (`config/launchpad.php`) exposes `branding`,
`accent_color`, `dark_header`, and `tile_size`. Tabs/groups/tiles are defined
preferably through the fluent API above, since tiles usually need closures
(live KPIs, custom actions) that plain config arrays cannot express.

### Deprecated / no-op methods

Because the launchpad lives inside the native shell, a few older fluent
methods are **accepted but no longer render anything** (kept so existing
configs don't break):

- `->brand(name, logo, initials)` — the sub-nav no longer shows a brand block
  (branding is left to the native Filament topbar). The name is still used for
  the page `<title>`.
- `->darkHeader(bool)` — light/dark theming is handled natively by Filament.
- `->notificationCount(int)` — the bell lives in the native topbar.

## Roadmap / Coming soon

- [ ] Auto-discovery of resources to generate tiles
- [ ] Per-user favorites and drag-to-reorder layouts
- [ ] Authorization per tile/group

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Anselmo Kossa](https://github.com/anselmocossa)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
