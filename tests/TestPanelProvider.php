<?php

namespace Filament\Launchpad\Tests;

use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Launchpad\LaunchpadTab;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\Launchpad\TileGroup;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('test')
            ->default()
            ->path('test')
            ->plugin(
                LaunchpadPlugin::make()
                    ->brand(name: 'Loja Demo', initials: 'LD')
                    ->spaces([
                        // Retro-compat coverage: a legacy LaunchpadTab, still
                        // configured with ->groups() — becomes a one-page space.
                        LaunchpadTab::make('Início')->groups([
                            TileGroup::make('Favoritos')->tiles([
                                Tile::make('Vendas Hoje')
                                    ->subtitle('Ponto de Venda')
                                    ->icon('heroicon-o-banknotes')
                                    ->kpi(fn (): string => '128')
                                    ->unit('un')
                                    ->trend('+12% vs ontem', 'success')
                                    ->url('/vendas'),
                                Tile::make('Erro KPI')
                                    ->subtitle('Deve degradar sem rebentar')
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->kpi(fn (): string => throw new \RuntimeException('boom'))
                                    ->trend('n/d', 'gray'),
                            ]),
                            TileGroup::make('Catálogo')->tiles([
                                Tile::make('Produtos')
                                    ->subtitle('Catálogo geral')
                                    ->icon('heroicon-o-cube')
                                    ->badge('24')
                                    ->note('novo')
                                    ->url('/produtos'),
                            ]),
                        ]),
                        LaunchpadTab::make('Clientes')->groups([
                            TileGroup::make('Geral')->tiles([
                                Tile::make('Clientes Activos')
                                    ->subtitle('CRM')
                                    ->icon('heroicon-o-users')
                                    ->kpi(fn (): string => '350')
                                    ->unit('clientes')
                                    ->trend('-2% vs mês passado', 'danger'),
                            ]),
                        ]),
                        // New API coverage: a multi-page space, to exercise
                        // the sub-nav's pages dropdown end-to-end.
                        LaunchpadSpace::make('Ponto de Venda')->pages([
                            LaunchpadPage::make('Visão Geral')->sections([
                                TileGroup::make('Terminal')->tiles([
                                    Tile::make('Abrir Caixa')
                                        ->subtitle('Terminal POS')
                                        ->icon('heroicon-o-computer-desktop'),
                                ]),
                            ]),
                            LaunchpadPage::make('Vendas')->sections([
                                TileGroup::make('Histórico')->tiles([
                                    Tile::make('Vendas do Dia')
                                        ->subtitle('Resumo')
                                        ->icon('heroicon-o-banknotes')
                                        ->kpi('12'),
                                ]),
                            ]),
                        ]),
                    ])
            );
    }
}
