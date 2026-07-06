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
        ->assertDontSee('Loja Demo')             // brand block removed
        ->assertDontSee('Pesquisar aplicações'); // search box removed
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

it('marks the active space bold/underlined in the accent color', function () {
    Livewire::test(LaunchpadBar::class)
        ->assertSeeHtml('fi-launchpad-tab-active');
});

it('only shows a pages dropdown for the space that has more than one page', function () {
    Livewire::test(LaunchpadBar::class)
        ->assertSeeHtml('▾')          // chevron for the "Ponto de Venda" dropdown
        ->assertSee('Visão Geral')    // its pages, listed in the dropdown
        ->assertSee('Vendas');
});
