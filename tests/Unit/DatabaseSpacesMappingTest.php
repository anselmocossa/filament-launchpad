<?php

use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Space;

it('maps a space with two pages and kpi/shortcut cards from the database into DTOs', function () {
    $space = Space::create(['label' => 'Ponto de Venda', 'icon' => 'heroicon-o-computer-desktop', 'sort' => 1]);

    $page1 = $space->pages()->create(['label' => 'Visão Geral', 'icon' => 'heroicon-o-home', 'sort' => 1]);
    $section1 = $page1->sections()->create(['title' => 'Terminal', 'sort' => 1]);
    $section1->cards()->create([
        'title' => 'Vendas Hoje',
        'subtitle' => 'Resumo diário',
        'icon' => 'heroicon-o-banknotes',
        'type' => 'kpi',
        'kpi_value' => '128',
        'unit' => 'un',
        'trend' => '+12% vs ontem',
        'trend_color' => 'success',
        'badge' => 'novo',
        'badge_bg' => '#ecfdf5',
        'badge_color' => '#065f46',
        'target_type' => 'url',
        'target_value' => '/vendas',
        'sort' => 1,
    ]);

    $page2 = $space->pages()->create(['label' => 'Atalhos', 'sort' => 2]);
    $section2 = $page2->sections()->create(['title' => 'Geral', 'sort' => 1]);
    $section2->cards()->create([
        'title' => 'Abrir Caixa',
        'subtitle' => 'Terminal POS',
        'icon' => 'heroicon-o-computer-desktop',
        'type' => 'shortcut',
        'note' => 'novo',
        'target_type' => 'resource',
        'target_value' => 'App\\Filament\\Resources\\SaleResource',
        'sort' => 1,
    ]);

    $plugin = LaunchpadPlugin::make();
    $spaces = $plugin->getSpaces();

    expect($spaces)->toHaveCount(1);

    /** @var LaunchpadSpace $mappedSpace */
    $mappedSpace = $spaces[0];
    expect($mappedSpace)->toBeInstanceOf(LaunchpadSpace::class)
        ->and($mappedSpace->getLabel())->toBe('Ponto de Venda')
        ->and($mappedSpace->getId())->toBe((string) $space->id)
        ->and($mappedSpace->getIcon())->toBe('heroicon-o-computer-desktop')
        ->and($mappedSpace->getPages())->toHaveCount(2);

    $mappedPage1 = $mappedSpace->getPages()[0];
    expect($mappedPage1->getLabel())->toBe('Visão Geral')
        ->and($mappedPage1->getIcon())->toBe('heroicon-o-home')
        ->and($mappedPage1->getSections())->toHaveCount(1);

    $kpiTile = $mappedPage1->getSections()[0]->getTiles()[0];
    $kpiArray = $kpiTile->toArray();

    expect($kpiArray['t'])->toBe('Vendas Hoje')
        ->and($kpiArray['s'])->toBe('Resumo diário')
        ->and($kpiArray['icon'])->toBe('heroicon-o-banknotes')
        ->and($kpiArray['hasKpi'])->toBeTrue()
        ->and($kpiArray['kpi'])->toBe('128')
        ->and($kpiArray['unit'])->toBe('un')
        ->and($kpiArray['trend'])->toBe('+12% vs ontem')
        ->and($kpiArray['trendColor'])->toBe('#16a34a')
        ->and($kpiArray['badge'])->toBe('novo')
        ->and($kpiArray['href'])->toBe('/vendas');

    $mappedPage2 = $mappedSpace->getPages()[1];
    expect($mappedPage2->getIcon())->toBeNull();

    $shortcutTile = $mappedPage2->getSections()[0]->getTiles()[0];
    $shortcutArray = $shortcutTile->toArray();

    expect($shortcutArray['t'])->toBe('Abrir Caixa')
        ->and($shortcutArray['hasKpi'])->toBeFalse()
        ->and($shortcutArray['kpi'])->toBeNull()
        ->and($shortcutArray['nota'])->toBe('novo');
});
