<?php

use Filament\Launchpad\Filament\Resources\PageResource\Pages\BuildLayout;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Pages\Launchpad;
use Filament\Launchpad\Tests\Support\TestStatsWidget;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

// ---------------------------------------------------------------------
// Plugin registry: widgets()/getWidgets()/getWidget().
// ---------------------------------------------------------------------

it('registers widgets and resolves them by key', function () {
    $plugin = LaunchpadPlugin::make()->widgets([
        ['key' => 'stats', 'class' => TestStatsWidget::class, 'label' => 'Estatísticas', 'icon' => 'heroicon-o-chart-bar', 'columnSpan' => 'full'],
    ]);

    expect($plugin->getWidget('stats')['class'])->toBe(TestStatsWidget::class)
        ->and($plugin->getWidget('stats')['label'])->toBe('Estatísticas')
        ->and($plugin->getWidget('inexistente'))->toBeNull();
});

it('auto-loads widgets already registered on the Filament panel', function () {
    $plugin = LaunchpadPlugin::make();

    expect($plugin->getWidget('test-stats-widget'))->not->toBeNull()
        ->and($plugin->getWidget('test-stats-widget')['class'])->toBe(TestStatsWidget::class)
        ->and($plugin->getWidget('test-stats-widget')['label'])->toBe('Test Stats Widget')
        ->and($plugin->getWidget('test-stats-widget')['columnSpan'])->toBe('6');
});

it('lets explicit widget metadata override the auto-loaded panel widget', function () {
    $plugin = LaunchpadPlugin::make()->widgets([
        [
            'key' => 'test-stats-widget',
            'class' => TestStatsWidget::class,
            'label' => 'Estatísticas',
            'icon' => 'heroicon-o-chart-bar',
            'columnSpan' => 6,
        ],
    ]);

    expect($plugin->getWidget('test-stats-widget')['label'])->toBe('Estatísticas')
        ->and($plugin->getWidget('test-stats-widget')['icon'])->toBe('heroicon-o-chart-bar')
        ->and($plugin->getWidget('test-stats-widget')['columnSpan'])->toBe(6);
});

it('merges widgets registered across multiple calls', function () {
    $plugin = LaunchpadPlugin::make()
        ->widgets([['key' => 'a', 'class' => TestStatsWidget::class, 'label' => 'A']])
        ->widgets([['key' => 'b', 'class' => TestStatsWidget::class, 'label' => 'B']]);

    expect($plugin->getWidget('a')['label'])->toBe('A')
        ->and($plugin->getWidget('b')['label'])->toBe('B');
});

// ---------------------------------------------------------------------
// mapCardToDto(): Card(type=widget) -> Tile (widget variant), or omitted.
// ---------------------------------------------------------------------

function widgetCardIn(Section $section, string $widgetKey, string $title = 'Widget'): Card
{
    return $section->cards()->create([
        'title' => $title,
        'type' => 'widget',
        'widget_key' => $widgetKey,
        'target_type' => 'none',
    ]);
}

it('maps a widget card to a widget Tile when its key resolves to a registered widget', function () {
    $plugin = LaunchpadPlugin::make()->widgets([
        ['key' => 'stats', 'class' => TestStatsWidget::class, 'label' => 'Estatísticas', 'icon' => 'heroicon-o-chart-bar', 'columnSpan' => 'full'],
    ]);

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);
    $card = widgetCardIn($section, 'stats');

    $method = new ReflectionMethod(LaunchpadPlugin::class, 'mapCardToDto');
    $method->setAccessible(true);
    $tile = $method->invoke($plugin, $card->refresh());

    expect($tile)->not->toBeNull()
        ->and($tile->isWidget())->toBeTrue()
        ->and($tile->getWidgetClass())->toBe(TestStatsWidget::class)
        ->and($tile->getWidgetColumnSpan())->toBe('full');
});

it('uses the placed card widget width over the widget default width', function () {
    $plugin = LaunchpadPlugin::make()->widgets([
        ['key' => 'stats', 'class' => TestStatsWidget::class, 'label' => 'Estatísticas', 'columnSpan' => 'full'],
    ]);

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);
    $card = widgetCardIn($section, 'stats');
    $card->update(['widget_column_span' => '6']);

    $method = new ReflectionMethod(LaunchpadPlugin::class, 'mapCardToDto');
    $method->setAccessible(true);
    $tile = $method->invoke($plugin, $card->refresh());

    expect($tile->getWidgetColumnSpan())->toBe('6');
});

it('omits a widget card whose key is not registered, without throwing', function () {
    $plugin = LaunchpadPlugin::make();

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);
    $card = widgetCardIn($section, 'inexistente', 'Fantasma');

    $method = new ReflectionMethod(LaunchpadPlugin::class, 'mapCardToDto');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($plugin, $card->refresh()))->not->toThrow(Throwable::class);
    expect($method->invoke($plugin, $card->refresh()))->toBeNull();
});

it('omits a widget card with a blank widget_key, without throwing', function () {
    $plugin = LaunchpadPlugin::make();

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);
    $card = $section->cards()->create([
        'title' => 'Sem key',
        'type' => 'widget',
        'widget_key' => null,
        'target_type' => 'none',
    ]);

    $method = new ReflectionMethod(LaunchpadPlugin::class, 'mapCardToDto');
    $method->setAccessible(true);

    expect($method->invoke($plugin, $card->refresh()))->toBeNull();
});

it('filters out omitted widget tiles from the section mapping, keeping normal tiles intact', function () {
    $plugin = LaunchpadPlugin::make()->widgets([
        ['key' => 'stats', 'class' => TestStatsWidget::class, 'label' => 'Estatísticas'],
    ]);

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);

    $section->cards()->create(['title' => 'KPI Normal', 'type' => 'kpi', 'kpi_value' => '10', 'target_type' => 'none']);
    widgetCardIn($section, 'inexistente', 'Fantasma');
    widgetCardIn($section, 'stats', 'Estatísticas');

    $section->load('cards');

    $method = new ReflectionMethod(LaunchpadPlugin::class, 'mapSectionToDto');
    $method->setAccessible(true);
    $group = $method->invoke($plugin, $section);

    expect($group->getTiles())->toHaveCount(2)
        ->and($group->getTiles()[0]->getTitle())->toBe('KPI Normal')
        ->and($group->getTiles()[1]->isWidget())->toBeTrue();
});

// ---------------------------------------------------------------------
// Builder: dragging a widget from the library into a section.
// ---------------------------------------------------------------------

it('adds a widget from the library into a section, persisting type=widget and widget_key', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->assertSeeHtml('widget:test-stats-widget')
        ->call('addWidgetFromLibrary', $section->id, 'test-stats-widget', null);

    $card = $section->cards()->first();

    expect($card)->not->toBeNull()
        ->and($card->type)->toBe('widget')
        ->and($card->widget_key)->toBe('test-stats-widget')
        ->and($card->widget_column_span)->toBe('6')
        ->and($card->title)->toBe('Test Stats Widget')
        ->and(pivotSort($section, $card))->toBe(0);
});

it('adds a widget at a specific index and shifts the rest', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $existing = $section->cards()->create(['title' => 'Existente', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addWidgetFromLibrary', $section->id, 'test-stats-widget', 0);

    $new = $section->cards()->where('widget_key', 'test-stats-widget')->first();

    expect(pivotSort($section, $new))->toBe(0)
        ->and(pivotSort($section, $existing))->toBe(1);
});

it('does not add a widget whose key is not registered', function () {
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addWidgetFromLibrary', $section->id, 'inexistente', null);

    expect($section->cards()->count())->toBe(0);
});

it('does not add a widget to a section belonging to another page', function () {
    LaunchpadPlugin::get()->widgets([
        ['key' => 'stats', 'class' => TestStatsWidget::class, 'label' => 'Estatísticas'],
    ]);

    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);

    $otherSpace = Space::query()->create(['label' => 'Outro', 'sort' => 1]);
    $otherPage = Page::query()->create(['space_id' => $otherSpace->id, 'label' => 'Outra', 'sort' => 0]);
    $otherSection = Section::query()->create(['page_id' => $otherPage->id, 'title' => 'Alheia', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addWidgetFromLibrary', $otherSection->id, 'stats', null);

    expect($otherSection->cards()->count())->toBe(0);
});

// ---------------------------------------------------------------------
// Render (HTTP): the launchpad renders registered widgets natively, and
// degrades gracefully for unregistered ones.
// ---------------------------------------------------------------------

it('renders a registered widget natively between tiles, without breaking the grid or dark mode', function () {
    LaunchpadPlugin::get()
        ->spaces([]) // force the DB-driven path for this test
        ->widgets([
            ['key' => 'stats', 'class' => TestStatsWidget::class, 'label' => 'Estatísticas', 'columnSpan' => 'full'],
        ]);

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);

    $section->cards()->create(['title' => 'Tile Normal', 'type' => 'kpi', 'kpi_value' => '5', 'target_type' => 'none']);
    widgetCardIn($section, 'stats', 'Estatísticas');

    Livewire::test(Launchpad::class)
        ->assertOk()
        ->assertSee('Tile Normal') // regular tiles keep rendering
        ->assertSee('Widget de Teste') // TestStatsWidget's stat label, rendered natively
        ->assertSee('42'); // TestStatsWidget's stat value
});

it('renders consecutive half-width widgets side by side in one widget row', function () {
    LaunchpadPlugin::get()
        ->spaces([]) // force the DB-driven path for this test
        ->widgets([
            ['key' => 'stats', 'class' => TestStatsWidget::class, 'label' => 'Estatísticas', 'columnSpan' => 'full'],
        ]);

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);

    $section->cards()->create(['title' => 'Widget A', 'type' => 'widget', 'widget_key' => 'stats', 'widget_column_span' => '6', 'target_type' => 'none']);
    $section->cards()->create(['title' => 'Widget B', 'type' => 'widget', 'widget_key' => 'stats', 'widget_column_span' => '6', 'target_type' => 'none']);

    Livewire::test(Launchpad::class)
        ->assertOk()
        ->assertSeeHtml('lp-widget-row')
        ->assertSeeHtml('grid-column:span 6 / span 6')
        ->assertSee('Widget de Teste');
});

it('does not 500 when a widget card key is not registered, and still renders the rest of the page', function () {
    LaunchpadPlugin::get()->spaces([]); // force the DB-driven path for this test

    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);

    widgetCardIn($section, 'inexistente', 'Fantasma');
    $section->cards()->create(['title' => 'Tile Normal', 'type' => 'kpi', 'kpi_value' => '5', 'target_type' => 'none']);

    Livewire::test(Launchpad::class)
        ->assertOk()
        ->assertSee('Tile Normal')
        ->assertDontSee('Fantasma');
});
