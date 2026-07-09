<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Tests\Support\Cards\PedidosPendentesCard;
use Filament\Launchpad\Tests\Support\Cards\RelatorioSemanal;
use Filament\Launchpad\Tests\Support\Cards\SalesTodayCard;

/**
 * Class-based card preset registration (Phase H, the card-preset
 * counterpart to KPIs' kpis()/discoverKpis()/getKpiSourceOptions() tests) —
 * see tests/Feature/CardAutoDiscoveryTest.php for the register()-driven
 * auto-discovery behaviour.
 */
it('cards() registers class-based presets, appearing in getCardLibrary() by their toArray()', function () {
    $plugin = LaunchpadPlugin::make()->cards([SalesTodayCard::class]);

    $library = $plugin->getCardLibrary();

    expect(array_column($library, 'key'))->toContain('sales_today');

    $preset = collect($library)->firstWhere('key', 'sales_today');

    expect($preset)->toBe((new SalesTodayCard)->toArray());
});

it('discoverCards() scans a directory and registers every concrete CardPreset class found', function () {
    $plugin = LaunchpadPlugin::make()->discoverCards(
        in: __DIR__.'/../Support/Cards',
        for: 'Filament\\Launchpad\\Tests\\Support\\Cards',
    );

    $keys = array_column($plugin->getCardLibrary(), 'key');

    expect($keys)->toContain('sales_today')
        ->toContain('pedidos_pendentes')
        ->toContain('relatorio_semanal')
        ->toContain('card'); // the literally-named "Card" edge case
});

it('getCardLibrary() fuses legacy array presets with class-based presets', function () {
    $plugin = LaunchpadPlugin::make()
        ->cardLibrary([['key' => 'vendas', 'title' => 'Vendas', 'type' => 'kpi']])
        ->cards([SalesTodayCard::class]);

    $keys = array_column($plugin->getCardLibrary(), 'key');

    expect($keys)->toBe(['vendas', 'sales_today']);
});

it('a class-based preset wins over a same-keyed legacy array preset, regardless of registration order', function () {
    $plugin = LaunchpadPlugin::make()
        ->cards([SalesTodayCard::class])
        ->cardLibrary([['key' => 'sales_today', 'title' => 'Legacy Title', 'type' => 'shortcut']]);

    $library = $plugin->getCardLibrary();

    expect($library)->toHaveCount(1);

    $preset = collect($library)->firstWhere('key', 'sales_today');

    expect($preset['title'])->toBe('Sales Today');
});

it('falls back to the built-in KPI/Atalho pair when no legacy presets were registered, still fusing class-based ones', function () {
    $plugin = LaunchpadPlugin::make()->cards([PedidosPendentesCard::class]);

    $keys = array_column($plugin->getCardLibrary(), 'key');

    expect($keys)->toBe(['kpi', 'atalho', 'pedidos_pendentes']);
});

it('cards() called multiple times merges (not replaces) previously registered presets', function () {
    $plugin = LaunchpadPlugin::make()
        ->cards([SalesTodayCard::class])
        ->cards([RelatorioSemanal::class]);

    $keys = array_column($plugin->getCardLibrary(), 'key');

    expect($keys)->toContain('sales_today')->toContain('relatorio_semanal');
});
