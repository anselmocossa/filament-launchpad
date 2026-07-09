<?php

use Filament\Facades\Filament;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Tests\Support\Kpis\StoreOnlySource;
use Filament\Launchpad\Tests\Support\Kpis\VendasHoje;
use Filament\Panel;

/**
 * Point 6 of the new KPI design: KpiSource::panels() restricts a source to
 * specific panel ids. LaunchpadPanel::id() reads
 * Filament::getCurrentPanel()?->getId(), so these tests fake the "current
 * panel" directly via Filament::setCurrentPanel() with a bare Panel
 * instance (never registered on the PanelRegistry — the plugin only ever
 * reads ->getId() off it, so it doesn't need to be).
 */
function createPanelScopedSection(): Section
{
    $space = Space::create(['label' => 'Espaço', 'sort' => 1]);
    $page = Page::create(['space_id' => $space->id, 'label' => 'Página', 'sort' => 1]);

    return Section::create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 1]);
}

afterEach(function () {
    Filament::setCurrentPanel(null);
});

it('resolves a panels()-restricted source when the current panel is in its list', function () {
    $section = createPanelScopedSection();
    $section->cards()->create([
        'title' => 'Só Store',
        'type' => 'kpi',
        'kpi_source' => StoreOnlySource::key(),
        'sort' => 1,
    ]);

    Filament::setCurrentPanel(Panel::make()->id('store'));

    $plugin = LaunchpadPlugin::make()->kpis([StoreOnlySource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['hasKpi'])->toBeTrue()
        ->and($tile['kpi'])->toBe('7');
});

it('hides a panels()-restricted source on a panel not in its list', function () {
    $section = createPanelScopedSection();
    $section->cards()->create([
        'title' => 'Só Store',
        'type' => 'kpi',
        'kpi_source' => StoreOnlySource::key(),
        'sort' => 1,
    ]);

    Filament::setCurrentPanel(Panel::make()->id('outro'));

    $plugin = LaunchpadPlugin::make()->kpis([StoreOnlySource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['hasKpi'])->toBeFalse()
        ->and($tile['kpi'])->toBeNull();
});

it('hides a panels()-restricted source when there is no current panel at all', function () {
    $section = createPanelScopedSection();
    $section->cards()->create([
        'title' => 'Só Store',
        'type' => 'kpi',
        'kpi_source' => StoreOnlySource::key(),
        'sort' => 1,
    ]);

    Filament::setCurrentPanel(null);

    $plugin = LaunchpadPlugin::make()->kpis([StoreOnlySource::class]);
    $tile = $plugin->getSpaces()[0]->getPages()[0]->getSections()[0]->getTiles()[0]->toArray();

    expect($tile['hasKpi'])->toBeFalse();
});

it('excludes a panels()-restricted source from getKpiSourceOptions() on a non-matching panel and includes it on a matching one', function () {
    $plugin = LaunchpadPlugin::make()->kpis([StoreOnlySource::class]);

    Filament::setCurrentPanel(Panel::make()->id('outro'));
    expect($plugin->getKpiSourceOptions())->not->toHaveKey(StoreOnlySource::key());

    Filament::setCurrentPanel(Panel::make()->id('store'));
    expect($plugin->getKpiSourceOptions())->toHaveKey(StoreOnlySource::key());
});

it('does not restrict a source with the default empty panels() regardless of the current panel', function () {
    Filament::setCurrentPanel(Panel::make()->id('outro'));

    $plugin = LaunchpadPlugin::make()->kpis([VendasHoje::class]);

    expect($plugin->getKpiSourceOptions())->toHaveKey(VendasHoje::key());
});
