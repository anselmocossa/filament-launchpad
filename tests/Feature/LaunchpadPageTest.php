<?php

use Filament\Launchpad\Pages\Launchpad;
use Livewire\Livewire;

it('renders inside the default Filament page layout, keeping the native chrome', function () {
    // The launchpad must NOT replace the panel shell: it uses the default
    // Filament page layout (native topbar + sidebar), only adding the sub-nav
    // and tile grid inside the content area.
    $page = new Launchpad;

    expect($page->getLayout())
        ->toBe('filament-panels::components.layout.index')
        ->not->toBe('filament-panels::components.layout.base');

    Livewire::test(Launchpad::class)->assertOk();
});

it('renders the launchpad sub-nav bar full-width, glued under the topbar, outside the padded content area', function () {
    // The sub-nav (space tabs) is NOT part of the page's own Livewire view
    // anymore: it is a standalone `LaunchpadBar` component, injected via
    // PanelsRenderHook::CONTENT_BEFORE (see LaunchpadPlugin::boot()), which
    // only shows up in a real HTTP render of the full panel layout —
    // Livewire::test() only renders the page component's own view, not the
    // surrounding layout hooks.
    $response = $this->get('/test');

    $response->assertOk();
    $response->assertSee('Início', false);
    $response->assertSee('Clientes', false);

    // The bar must render BEFORE the <main> content region (i.e. via
    // CONTENT_BEFORE, outside the max-width/padded column), not inside it.
    $html = $response->getContent();
    $barPosition = strpos($html, 'fi-launchpad-tab');
    $mainPosition = strpos($html, '<main');

    expect($barPosition)->not->toBeFalse()
        ->and($mainPosition)->not->toBeFalse()
        ->and($barPosition)->toBeLessThan($mainPosition);
});

it('mounts with the first space and its first page active, rendering its sections', function () {
    Livewire::test(Launchpad::class)
        ->assertSet('activeSpace', 'inicio')
        ->assertSet('activePage', 'inicio')
        ->assertSee('Vendas Hoje')
        ->assertSee('Produtos')
        ->assertDontSee('Clientes Activos');
});

it('actually compiles the heroicon svg for both tile variants without erroring', function () {
    // Regression: @svg() was called with a named `style:` argument, which the
    // blade-icons svg(string $name, $class = '', array $attributes = []) helper
    // does not accept — throwing "Unknown named parameter $style" and 500-ing
    // every tile with an icon. The TestPanelProvider seeds a KPI tile
    // (heroicon-o-banknotes) and an icon-only tile (heroicon-o-cube), so a
    // successful render with inline <svg> markup proves both code paths work.
    Livewire::test(Launchpad::class)
        ->assertOk()
        ->assertSeeHtml('<svg')
        ->assertSeeHtml('width:20px;height:20px;flex:none;color:#d1d5db') // KPI variant icon
        ->assertSeeHtml('width:28px;height:28px;color:#9ca3af');          // icon-only variant icon
});

it('renders the kpi variant for tiles with a kpi and the icon-only variant otherwise', function () {
    Livewire::test(Launchpad::class)
        ->assertSee('128') // resolved kpi value for "Vendas Hoje"
        ->assertSee('un')
        ->assertSee('+12% vs ontem')
        ->assertSee('Produtos') // icon-only tile
        ->assertSee('24') // its badge
        ->assertSee('novo'); // its note
});

it('degrades a throwing kpi to an em dash without crashing the page', function () {
    Livewire::test(Launchpad::class)
        ->assertOk()
        ->assertSee('Erro KPI')
        ->assertSee('—');
});

it('switches space, showing its first page', function () {
    Livewire::test(Launchpad::class)
        ->call('selectSpace', 'clientes')
        ->assertSet('activeSpace', 'clientes')
        ->assertSet('activePage', 'clientes')
        ->assertSee('Clientes Activos')
        ->assertDontSee('Vendas Hoje');
});

it('switches page within the same multi-page space', function () {
    Livewire::test(Launchpad::class)
        ->call('selectSpace', 'ponto-de-venda')
        ->assertSet('activePage', 'visao-geral')
        ->assertSee('Abrir Caixa')
        ->assertDontSee('Vendas do Dia')
        ->call('selectPage', 'ponto-de-venda', 'vendas')
        ->assertSet('activeSpace', 'ponto-de-venda')
        ->assertSet('activePage', 'vendas')
        ->assertSee('Vendas do Dia')
        ->assertDontSee('Abrir Caixa');
});

it('navigates to a tile target when opened', function () {
    Livewire::test(Launchpad::class)
        ->call('open', 0, 0)
        ->assertRedirect('/vendas');
});

it('does not crash when opening a tile without a resolvable index', function () {
    Livewire::test(Launchpad::class)
        ->call('open', 99, 99)
        ->assertOk();
});

it('reacts to the launchpad-page-selected event dispatched by the LaunchpadBar component', function () {
    // The page no longer owns the sub-nav UI: the standalone LaunchpadBar
    // component dispatches this event when a space/page is clicked, and the
    // page mirrors it into its own state so the tile grid re-filters.
    Livewire::test(Launchpad::class)
        ->dispatch('launchpad-page-selected', space: 'clientes', page: 'clientes')
        ->assertSet('activeSpace', 'clientes')
        ->assertSet('activePage', 'clientes')
        ->assertSee('Clientes Activos')
        ->assertDontSee('Vendas Hoje');
});

it('falls back to the first page of a space when an unknown page id is selected, without crashing', function () {
    Livewire::test(Launchpad::class)
        ->dispatch('launchpad-page-selected', space: 'ponto-de-venda', page: 'pagina-inexistente')
        ->assertOk()
        ->assertSee('Abrir Caixa')      // falls back to "Visão Geral", the first page
        ->assertDontSee('Vendas do Dia');
});
