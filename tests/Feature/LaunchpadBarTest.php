<?php

use Filament\Launchpad\Livewire\LaunchpadBar;
use Livewire\Livewire;

it('mounts with the first configured space and its first page active, rendering the space labels', function () {
    // The bar is tabs/spaces-only: no brand block, no search box. The first
    // space is "Início" (id: inicio), whose single page shares its id.
    Livewire::test(LaunchpadBar::class)
        ->assertOk()
        ->assertSet('activeSpace', 'inicio')
        ->assertSet('activePage', 'inicio')
        ->assertSee('Início')
        ->assertSee('Clientes')
        ->assertSee('Ponto de Venda')
        ->assertDontSee('Launchpad')             // brand block removed
        ->assertDontSee('Pesquisar aplicações'); // search box removed
});

it('goUp walks one level up the breadcrumb path toward the root', function () {
    Livewire::test(LaunchpadBar::class)
        // Deep: Ponto de Venda › Vendas (a non-first page of the space).
        ->call('selectPage', 'ponto-de-venda', 'vendas')
        ->assertSet('activeSpace', 'ponto-de-venda')
        ->assertSet('activePage', 'vendas')
        // Up 1 → the space's first page (Visão Geral).
        ->call('goUp')
        ->assertSet('activeSpace', 'ponto-de-venda')
        ->assertSet('activePage', 'visao-geral')
        // Up 2 → the root space (Início) and its first page.
        ->call('goUp')
        ->assertSet('activeSpace', 'inicio')
        ->assertSet('activePage', 'inicio')
        // Up 3 → already at the root, stays put (no-op).
        ->call('goUp')
        ->assertSet('activeSpace', 'inicio')
        ->assertSet('activePage', 'inicio');
});

it('selects a space and activates its first page, dispatching launchpad-page-selected', function () {
    Livewire::test(LaunchpadBar::class)
        ->call('selectSpace', 'clientes')
        ->assertSet('activeSpace', 'clientes')
        ->assertSet('activePage', 'clientes')
        ->assertDispatched('launchpad-page-selected', space: 'clientes', page: 'clientes');
});

it('selects a specific page inside a multi-page space, dispatching launchpad-page-selected', function () {
    Livewire::test(LaunchpadBar::class)
        ->call('selectPage', 'ponto-de-venda', 'vendas')
        ->assertSet('activeSpace', 'ponto-de-venda')
        ->assertSet('activePage', 'vendas')
        ->assertDispatched('launchpad-page-selected', space: 'ponto-de-venda', page: 'vendas');
});

it('marks the active space using Filament\'s native active topbar item classes', function () {
    Livewire::test(LaunchpadBar::class)
        ->assertSeeHtml('fi-topbar-item')
        ->assertSeeHtml('fi-active');
});

it('only shows a pages dropdown for the space that has more than one page', function () {
    Livewire::test(LaunchpadBar::class)
        ->assertSeeHtml('fi-dropdown')        // native Filament dropdown for the "Ponto de Venda" space
        ->assertSeeHtml('fi-dropdown-list-item')
        ->assertSee('Visão Geral')    // its pages, listed in the dropdown
        ->assertSee('Vendas');
});

it('renders a "Todos os Spaces" shell menu button that lists every space and its pages', function () {
    Livewire::test(LaunchpadBar::class)
        ->assertSeeHtml('Todos os Spaces')
        ->assertSeeHtml('fi-dropdown-header')
        // Every space's label appears (once as a header in the shell menu, and
        // again as its own tab/dropdown trigger further along the sub-nav).
        ->assertSee('Início')
        ->assertSee('Clientes')
        ->assertSee('Ponto de Venda')
        // All of its pages are listed too, including single-page spaces.
        ->assertSee('Visão Geral')
        ->assertSee('Vendas');
});

it('renders a "Mais" overflow button and a measurable, non-scrolling tabs container for priority-nav collapsing', function () {
    Livewire::test(LaunchpadBar::class)
        ->assertSeeHtml('m19.5 8.25-7.5 7.5-7.5-7.5') // heroicon-o-chevron-down's path data
        ->assertSee('Mais')
        ->assertSeeHtml('fi-launchpad-bar-nav')
        ->assertSeeHtml('data-space-id')
        ->assertSeeHtml('launchpadOverflow()');
});

it('exposes the space and page icons to the view data, rendering an icon for the ones that have one', function () {
    $spaces = (new LaunchpadBar)->getSpacesData();

    $pontoDeVenda = collect($spaces)->firstWhere('id', 'ponto-de-venda');
    expect($pontoDeVenda['icon'])->toBe('heroicon-o-shopping-cart');

    $visaoGeral = collect($pontoDeVenda['pages'])->firstWhere('id', 'visao-geral');
    $vendas = collect($pontoDeVenda['pages'])->firstWhere('id', 'vendas');
    expect($visaoGeral['icon'])->toBe('heroicon-o-home')
        ->and($vendas['icon'])->toBeNull();

    // The rendered HTML actually includes the icon svg, both for the plain
    // topbar item ("Ponto de Venda") and the dropdown item ("Visão Geral").
    Livewire::test(LaunchpadBar::class)
        ->assertSeeHtml('fi-topbar-item-icon');
});
