<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Tests\Support\Kpis\BadgeShortcutSource;
use Filament\Launchpad\Tests\Support\Kpis\CachedSource;
use Filament\Launchpad\Tests\Support\Kpis\CountingSource;
use Filament\Launchpad\Tests\Support\Kpis\RestrictedSource;
use Filament\Launchpad\Tests\Support\Kpis\ThrowingSource;
use Filament\Launchpad\Tests\Support\Kpis\VendasHoje;
use Illuminate\Support\Facades\Cache;

/**
 * Phase G: class-based KPI/badge/trend engine (KpiSource/KpiResult/KpiResolver
 * + LaunchpadPlugin::kpis()/discoverKpis()/getKpiSourceOptions()). Coverage
 * maps 1:1 to the CA-01..CA-07 acceptance criteria of the phase.
 *
 * tests/Feature/KpiSourceTest.php (untouched, pre-Phase-G baseline) already
 * proves the LEGACY kpiSources() closure path keeps its exact historical
 * behaviour (a live source wins over a fixed value; a throwing closure
 * degrades to "—"). This file exercises the NEW class-based engine, which
 * now shares the SAME precedence as the legacy path: the live source WINS,
 * the card's static value is the fallback (a field the source returns null
 * for falls through to the static value). Both paths only differ in how they
 * degrade — see LaunchpadPlugin::mapCardToDto()'s docblock.
 */
function createEngineSection(): Section
{
    $space = Space::create(['label' => 'Espaço', 'sort' => 1]);
    $page = Page::create(['space_id' => $space->id, 'label' => 'Página', 'sort' => 1]);

    return Section::create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 1]);
}

beforeEach(function () {
    CountingSource::$calls = 0;
    CachedSource::$calls = 0;
});

// CA-01
it('lists a class-based source registered via kpis() in getKpiSourceOptions() by its label', function () {
    $plugin = LaunchpadPlugin::make()->kpis([VendasHoje::class]);

    expect($plugin->getKpiSourceOptions())->toHaveKey(VendasHoje::key(), 'Vendas de Hoje');
});

it('lists a class-based source registered via discoverKpis() too', function () {
    $plugin = LaunchpadPlugin::make()->discoverKpis(
        in: __DIR__.'/../Support/Kpis',
        for: 'Filament\\Launchpad\\Tests\\Support\\Kpis',
    );

    expect($plugin->getKpiSourceOptions())->toHaveKey(VendasHoje::key(), 'Vendas de Hoje');
});

it('discoverKpis() is idempotent and ignores a non-existent folder instead of throwing', function () {
    $plugin = LaunchpadPlugin::make()
        ->discoverKpis(in: __DIR__.'/../Support/Kpis', for: 'Filament\\Launchpad\\Tests\\Support\\Kpis')
        ->discoverKpis(in: __DIR__.'/../Support/Kpis', for: 'Filament\\Launchpad\\Tests\\Support\\Kpis')
        ->discoverKpis(in: '/path/does/not/exist', for: 'Some\\Missing\\Namespace');

    expect($plugin->getKpiSourceOptions())->toHaveKey(VendasHoje::key(), 'Vendas de Hoje');
});

// CA-02
it('resolves a source used by 3 tiles exactly once per request (memoization)', function () {
    $section = createEngineSection();

    foreach (range(1, 3) as $i) {
        $section->cards()->create([
            'title' => "Tile {$i}",
            'type' => 'kpi',
            'kpi_source' => CountingSource::key(),
            'sort' => $i,
        ]);
    }

    $plugin = LaunchpadPlugin::make()->kpis([CountingSource::class]);
    $tiles = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles();

    expect($tiles)->toHaveCount(3)
        ->and(CountingSource::$calls)->toBe(1)
        ->and($tiles[0]->toArray()['kpi'])->toBe('1')
        ->and($tiles[1]->toArray()['kpi'])->toBe('1')
        ->and($tiles[2]->toArray()['kpi'])->toBe('1');
});

// CA-03
it('respects cacheFor()\'s TTL: a fresh resolver instance within the TTL hits the cache, not the source', function () {
    config()->set('cache.default', 'array');
    Cache::flush();

    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Vendas Cacheadas',
        'type' => 'kpi',
        'kpi_source' => CachedSource::key(),
        'sort' => 1,
    ]);

    // First "request": a brand new LaunchpadPlugin (and thus a brand new,
    // empty-memo KpiResolver) resolves the source for the first time.
    $firstPlugin = LaunchpadPlugin::make()->kpis([CachedSource::class]);
    $firstTile = $firstPlugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    // Second "request": another brand new plugin/resolver — memoization
    // can't be the reason this doesn't re-run; only the Cache::remember()
    // TTL can explain the counter staying put.
    $secondPlugin = LaunchpadPlugin::make()->kpis([CachedSource::class]);
    $secondTile = $secondPlugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($firstTile['kpi'])->toBe('1')
        ->and($secondTile['kpi'])->toBe('1')
        ->and(CachedSource::$calls)->toBe(1);
});

// CA-04
it('lets a shortcut-typed card inherit badge and trend from its live source without becoming a kpi tile', function () {
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Encomendas',
        'type' => 'shortcut',
        'kpi_source' => BadgeShortcutSource::key(),
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpis([BadgeShortcutSource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['hasKpi'])->toBeFalse()
        ->and($tile['kpi'])->toBeNull()
        ->and($tile['badge'])->toBe('3 pendentes')
        ->and($tile['badgeBg'])->toBe('#fef3c7')
        ->and($tile['badgeColor'])->toBe('#92400e')
        ->and($tile['trend'])->toBe('+1 hoje')
        ->and($tile['trendColor'])->toBe('#d97706');
});

it('lets the live source\'s badge/trend win over the card\'s static badge/trend', function () {
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Encomendas',
        'type' => 'shortcut',
        'kpi_source' => BadgeShortcutSource::key(),
        'badge' => 'Fixo',
        'badge_bg' => '#000000',
        'badge_color' => '#ffffff',
        'trend' => 'Estático',
        'trend_color' => 'success',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpis([BadgeShortcutSource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['badge'])->toBe('3 pendentes')
        ->and($tile['badgeBg'])->toBe('#fef3c7')
        ->and($tile['badgeColor'])->toBe('#92400e')
        ->and($tile['trend'])->toBe('+1 hoje')
        ->and($tile['trendColor'])->toBe('#d97706');
});

it('lets the live source\'s value win over the card\'s fixed kpi_value/unit', function () {
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => VendasHoje::key(),
        'kpi_value' => '999',
        'unit' => 'MT',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpis([VendasHoje::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['kpi'])->toBe('42')
        ->and($tile['unit'])->toBe('un');
});

it('falls through to the card\'s static field when the source returns null for it (per-field control)', function () {
    // VendasHoje sets value + unit but leaves badge/trend null — those two
    // fields must fall through to whatever the card statically defines.
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => VendasHoje::key(),
        'badge' => 'Estático',
        'badge_bg' => '#ecfdf5',
        'badge_color' => '#065f46',
        'trend' => '+5% manual',
        'trend_color' => 'success',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpis([VendasHoje::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['kpi'])->toBe('42')
        ->and($tile['unit'])->toBe('un')
        ->and($tile['badge'])->toBe('Estático')
        ->and($tile['badgeBg'])->toBe('#ecfdf5')
        ->and($tile['badgeColor'])->toBe('#065f46')
        ->and($tile['trend'])->toBe('+5% manual')
        ->and($tile['trendColor'])->toBe('#16a34a');
});

// CA-05
it('degrades a throwing class-based source to an absent kpi value instead of a 500', function () {
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => ThrowingSource::key(),
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpis([ThrowingSource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['hasKpi'])->toBeFalse()
        ->and($tile['kpi'])->toBeNull();
});

it('falls back to a fixed kpi_value when the class-based source throws', function () {
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Vendas',
        'type' => 'kpi',
        'kpi_source' => ThrowingSource::key(),
        'kpi_value' => '77',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpis([ThrowingSource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['kpi'])->toBe('77');
});

// CA-06
it('hides the value entirely when authorize() returns false', function () {
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Confidencial',
        'type' => 'kpi',
        'kpi_source' => RestrictedSource::key(),
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make()->kpis([RestrictedSource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['hasKpi'])->toBeFalse()
        ->and($tile['kpi'])->toBeNull();
});

// CA-07 (new-engine-specific retrocompat: legacy closures and class-based
// sources happily coexist on the SAME plugin instance/request).
it('keeps legacy kpiSources() closures resolving fine alongside the new class-based engine', function () {
    $section = createEngineSection();
    $section->cards()->create([
        'title' => 'Legado',
        'type' => 'kpi',
        'kpi_source' => 'legacy_receita',
        'sort' => 1,
    ]);
    $section->cards()->create([
        'title' => 'Nova Fonte',
        'type' => 'kpi',
        'kpi_source' => VendasHoje::key(),
        'sort' => 2,
    ]);

    $plugin = LaunchpadPlugin::make()
        ->kpiSources(['legacy_receita' => fn (): int => 777])
        ->kpis([VendasHoje::class]);

    $tiles = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles();
    $byTitle = collect($tiles)->keyBy(fn ($tile) => $tile->toArray()['t']);

    expect($byTitle['Legado']->toArray()['kpi'])->toBe('777')
        ->and($byTitle['Nova Fonte']->toArray()['kpi'])->toBe('42')
        ->and($byTitle['Nova Fonte']->toArray()['unit'])->toBe('un');
});

it('a class-based registration overrides a same-keyed legacy closure, regardless of call order', function () {
    $plugin = LaunchpadPlugin::make()
        ->kpiSources([VendasHoje::key() => fn (): int => 111])
        ->kpis([VendasHoje::class]);

    expect($plugin->getKpiSource(VendasHoje::key()))->toBeNull()
        ->and($plugin->getKpiSourceOptions())->toHaveKey(VendasHoje::key(), 'Vendas de Hoje');
});
