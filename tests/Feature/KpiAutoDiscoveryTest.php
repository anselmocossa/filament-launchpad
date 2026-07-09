<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Tests\Support\Kpis\VendasHoje;
use Filament\Panel;
use Illuminate\Support\Facades\File;

/**
 * Point 4/5 of the new KPI design: LaunchpadPlugin::register() scans
 * config('launchpad.generators.path') for concrete KpiSource classes and
 * registers them automatically — no discoverKpis() call needed in the host
 * app — UNLESS the developer already registered KPIs manually via kpis()
 * or discoverKpis() (or explicitly called autoDiscoverKpis(false)), in
 * which case the manual registration wins and auto-discovery is skipped
 * entirely.
 *
 * register() is called directly with a bare Panel instance (no need to
 * boot a full HTTP request/panel) — it's the exact call Filament itself
 * makes while resolving a panel's plugins.
 *
 * Same shared-testbench-skeleton gotcha as MakeLaunchpadKpiCommandTest:
 * app_path('Filament/Launchpad') is shared across the whole suite, so wipe
 * it before and after every test here.
 */
function cleanupAutoDiscoveredKpis(): void
{
    File::deleteDirectory(app_path('Filament/Launchpad'));
}

function putAutoDiscoverableKpi(string $className, int $value): string
{
    $path = app_path("Filament/Launchpad/{$className}.php");

    File::ensureDirectoryExists(dirname($path));

    File::put($path, <<<PHP
        <?php

        namespace App\Filament\Launchpad;

        use Filament\Launchpad\Launchpad\BaseKpiSource;
        use Filament\Launchpad\Launchpad\KpiResult;

        class {$className} extends BaseKpiSource
        {
            public function resolve(): KpiResult
            {
                return KpiResult::make({$value});
            }
        }
        PHP);

    require_once $path;

    return $path;
}

beforeEach(function () {
    cleanupAutoDiscoveredKpis();
});

afterEach(function () {
    cleanupAutoDiscoveredKpis();
});

it('auto-discovers KpiSource classes dropped under the generators path without any explicit wiring', function () {
    putAutoDiscoverableKpi('AutoFoundKpi', 55);

    $plugin = LaunchpadPlugin::make();
    $plugin->register(Panel::make()->id('test'));

    expect($plugin->getKpiSourceOptions())->toHaveKey('auto_found', 'Auto Found');
});

it('is a no-op when the generators path does not exist', function () {
    expect(app_path('Filament/Launchpad'))->not->toBeDirectory();

    $plugin = LaunchpadPlugin::make();
    $plugin->register(Panel::make()->id('test'));

    expect($plugin->getKpiSourceOptions())->toBe([]);
});

it('registering kpis() manually before register() disables automatic discovery', function () {
    putAutoDiscoverableKpi('ShouldNotAutoRegisterKpi', 1);

    $plugin = LaunchpadPlugin::make()->kpis([VendasHoje::class]);
    $plugin->register(Panel::make()->id('test'));

    expect($plugin->getKpiSourceOptions())
        ->toHaveKey(VendasHoje::key())
        ->not->toHaveKey('should_not_auto_register');
});

it('registering discoverKpis() manually before register() disables automatic discovery', function () {
    putAutoDiscoverableKpi('ShouldNotAutoRegisterKpi', 1);

    $plugin = LaunchpadPlugin::make()->discoverKpis(
        in: __DIR__.'/../Support/Kpis',
        for: 'Filament\\Launchpad\\Tests\\Support\\Kpis',
    );
    $plugin->register(Panel::make()->id('test'));

    expect($plugin->getKpiSourceOptions())
        ->toHaveKey(VendasHoje::key())
        ->not->toHaveKey('should_not_auto_register');
});

it('autoDiscoverKpis(false) turns automatic discovery off even without any manual registration', function () {
    putAutoDiscoverableKpi('AutoFoundKpi', 55);

    $plugin = LaunchpadPlugin::make()->autoDiscoverKpis(false);
    $plugin->register(Panel::make()->id('test'));

    expect($plugin->getKpiSourceOptions())->not->toHaveKey('auto_found');
});

it('autoDiscoverKpis(true) forces discovery back on even after a manual kpis() call', function () {
    putAutoDiscoverableKpi('AutoFoundKpi', 55);

    $plugin = LaunchpadPlugin::make()->kpis([VendasHoje::class])->autoDiscoverKpis(true);
    $plugin->register(Panel::make()->id('test'));

    expect($plugin->getKpiSourceOptions())
        ->toHaveKey(VendasHoje::key())
        ->toHaveKey('auto_found');
});
