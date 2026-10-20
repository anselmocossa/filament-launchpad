<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Tests\Support\Cards\SalesTodayCard;
use Filament\Launchpad\Tests\Support\Kpis\TopUserKpi;
use Filament\Launchpad\Tests\Support\Kpis\VendasHoje;

/**
 * provide(): other packages hand the launchpad their own KPI sources and card
 * presets without switching the app's auto-discovery off.
 */
afterEach(fn () => LaunchpadPlugin::forgetProvided());

it('adds provided KPI sources to the registry and the source select', function () {
    LaunchpadPlugin::provide(kpis: [TopUserKpi::class]);

    $plugin = LaunchpadPlugin::make();

    expect($plugin->getRegisteredKpiSource('top_user'))->toBeInstanceOf(TopUserKpi::class)
        ->and($plugin->getKpiSourceOptions())->toHaveKey('top_user');
});

it('keeps the app\'s own sources when a package provides the same key', function () {
    LaunchpadPlugin::provide(kpis: [VendasHoje::class]);

    $plugin = LaunchpadPlugin::make()->kpiSources(['vendas_hoje' => fn () => 7]);

    expect($plugin->getKpiSourceOptions())->toHaveKey('vendas_hoje')
        ->and($plugin->getKpiSource('vendas_hoje'))->not->toBeNull();
});

it('adds provided card presets from classes, arrays and closures, keeping the defaults', function () {
    LaunchpadPlugin::provide(cards: [
        SalesTodayCard::class,
        ['key' => 'pkg_static', 'title' => 'Static', 'type' => 'shortcut'],
        fn (): array => [['key' => 'pkg_lazy', 'title' => 'Lazy', 'type' => 'shortcut']],
    ]);

    $keys = array_column(LaunchpadPlugin::make()->getCardLibrary(), 'key');

    expect($keys)->toContain('sales_today', 'pkg_static', 'pkg_lazy', 'kpi');
});

it('lets the app\'s presets win over provided ones and skips a closure that throws', function () {
    LaunchpadPlugin::provide(cards: [
        ['key' => 'sales_today', 'title' => 'From a package', 'type' => 'shortcut'],
        fn (): array => throw new RuntimeException('boom'),
    ]);

    $library = LaunchpadPlugin::make()->cards([SalesTodayCard::class])->getCardLibrary();

    expect(collect($library)->firstWhere('key', 'sales_today'))->toBe((new SalesTodayCard)->toArray());
});
