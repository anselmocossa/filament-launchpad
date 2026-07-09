<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Tests\Support\Cards\SalesTodayCard;
use Filament\Panel;
use Illuminate\Support\Facades\File;

/**
 * Card-preset counterpart to KpiAutoDiscoveryTest.php: LaunchpadPlugin::register()
 * scans config('launchpad.generators.path') for concrete CardPreset classes
 * and registers them automatically — no discoverCards() call needed in the
 * host app — UNLESS the developer already registered presets manually via
 * cards() or discoverCards() (or explicitly called autoDiscoverCards(false)),
 * in which case the manual registration wins and auto-discovery is skipped
 * entirely.
 *
 * Same shared-testbench-skeleton gotcha as KpiAutoDiscoveryTest: wipe
 * app_path('Filament/Launchpad') before and after every test here — it is
 * ALSO where KpiAutoDiscoveryTest drops its fixtures, but each test only
 * ever writes its own class name into it, so the two suites never collide
 * as long as both clean up after themselves.
 */
function cleanupAutoDiscoveredCardPresets(): void
{
    File::deleteDirectory(app_path('Filament/Launchpad'));
}

function putAutoDiscoverableCardPreset(string $className, string $title): string
{
    $path = app_path("Filament/Launchpad/{$className}.php");

    File::ensureDirectoryExists(dirname($path));

    File::put($path, <<<PHP
        <?php

        namespace App\Filament\Launchpad;

        use Filament\Launchpad\Launchpad\BaseCardPreset;

        class {$className} extends BaseCardPreset
        {
            protected string \$title = '{$title}';
        }
        PHP);

    require_once $path;

    return $path;
}

beforeEach(function () {
    cleanupAutoDiscoveredCardPresets();
});

afterEach(function () {
    cleanupAutoDiscoveredCardPresets();
});

it('auto-discovers CardPreset classes dropped under the generators path without any explicit wiring', function () {
    putAutoDiscoverableCardPreset('AutoFoundCard', 'Auto Found');

    $plugin = LaunchpadPlugin::make();
    $plugin->register(Panel::make()->id('test'));

    expect(array_column($plugin->getCardLibrary(), 'key'))->toContain('auto_found');
});

it('is a no-op when the generators path does not exist', function () {
    expect(app_path('Filament/Launchpad'))->not->toBeDirectory();

    $plugin = LaunchpadPlugin::make();
    $plugin->register(Panel::make()->id('test'));

    expect(array_column($plugin->getCardLibrary(), 'key'))->toBe(['kpi', 'atalho']);
});

it('registering cards() manually before register() disables automatic discovery', function () {
    putAutoDiscoverableCardPreset('ShouldNotAutoRegisterCard', 'Should Not Auto Register');

    $plugin = LaunchpadPlugin::make()->cards([SalesTodayCard::class]);
    $plugin->register(Panel::make()->id('test'));

    $keys = array_column($plugin->getCardLibrary(), 'key');

    expect($keys)->toContain('sales_today')
        ->not->toContain('should_not_auto_register');
});

it('registering discoverCards() manually before register() disables automatic discovery', function () {
    putAutoDiscoverableCardPreset('ShouldNotAutoRegisterCard', 'Should Not Auto Register');

    $plugin = LaunchpadPlugin::make()->discoverCards(
        in: __DIR__.'/../Support/Cards',
        for: 'Filament\\Launchpad\\Tests\\Support\\Cards',
    );
    $plugin->register(Panel::make()->id('test'));

    $keys = array_column($plugin->getCardLibrary(), 'key');

    expect($keys)->toContain('sales_today')
        ->not->toContain('should_not_auto_register');
});

it('autoDiscoverCards(false) turns automatic discovery off even without any manual registration', function () {
    putAutoDiscoverableCardPreset('AutoFoundCard', 'Auto Found');

    $plugin = LaunchpadPlugin::make()->autoDiscoverCards(false);
    $plugin->register(Panel::make()->id('test'));

    expect(array_column($plugin->getCardLibrary(), 'key'))->not->toContain('auto_found');
});

it('autoDiscoverCards(true) forces discovery back on even after a manual cards() call', function () {
    putAutoDiscoverableCardPreset('AutoFoundCard', 'Auto Found');

    $plugin = LaunchpadPlugin::make()->cards([SalesTodayCard::class])->autoDiscoverCards(true);
    $plugin->register(Panel::make()->id('test'));

    $keys = array_column($plugin->getCardLibrary(), 'key');

    expect($keys)->toContain('sales_today')
        ->toContain('auto_found');
});

it('auto-discovering KPIs and Cards from the same directory does not cross-register them', function () {
    putAutoDiscoverableCardPreset('OnlyACardCard', 'Only A Card');

    File::put(app_path('Filament/Launchpad/OnlyAKpiKpi.php'), <<<'PHP'
        <?php

        namespace App\Filament\Launchpad;

        use Filament\Launchpad\Launchpad\BaseKpiSource;
        use Filament\Launchpad\Launchpad\KpiResult;

        class OnlyAKpiKpi extends BaseKpiSource
        {
            public function resolve(): KpiResult
            {
                return KpiResult::make(1);
            }
        }
        PHP);
    require_once app_path('Filament/Launchpad/OnlyAKpiKpi.php');

    $plugin = LaunchpadPlugin::make();
    $plugin->register(Panel::make()->id('test'));

    expect(array_column($plugin->getCardLibrary(), 'key'))->toContain('only_a_card')
        ->and(array_column($plugin->getCardLibrary(), 'key'))->not->toContain('only_a_kpi')
        ->and($plugin->getKpiSourceOptions())->toHaveKey('only_a_kpi')
        ->and($plugin->getKpiSourceOptions())->not->toHaveKey('only_a_card');
});
