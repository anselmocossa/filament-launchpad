<?php

use Filament\Launchpad\Tests\Support\Cards\Card;
use Filament\Launchpad\Tests\Support\Cards\PedidosPendentesCard;
use Filament\Launchpad\Tests\Support\Cards\RelatorioSemanal;
use Filament\Launchpad\Tests\Support\Cards\SalesTodayCard;

/**
 * key()/defaultTitle() derivation, mirroring BaseKpiSourceTest — including
 * the new-design rule: a class named with the "...Card" generator suffix
 * (e.g. SalesTodayCard) has that suffix stripped BEFORE snake/headline
 * casing, so it never leaks into the persisted library_key or the default
 * title.
 */
it('strips a trailing "Card" suffix before deriving key() and the default title', function () {
    expect(SalesTodayCard::key())->toBe('sales_today')
        ->and((new SalesTodayCard)->toArray()['title'])->toBe('Sales Today');
});

it('keeps the class name unchanged for a class not ending in "Card"', function () {
    expect(RelatorioSemanal::key())->toBe('relatorio_semanal');
});

it('falls back to the untouched basename for a class literally named "Card"', function () {
    expect(Card::key())->toBe('card')
        ->and((new Card)->toArray()['title'])->toBe('Card');
});

it('toArray() produces the full shape the Builder consumes, with sensible defaults when nothing is overridden', function () {
    $preset = new SalesTodayCard;

    expect($preset->toArray())->toBe([
        'key' => 'sales_today',
        'title' => 'Sales Today',
        'subtitle' => null,
        'icon' => null,
        'type' => 'shortcut',
        'kpi_value' => null,
        'unit' => null,
        'trend' => null,
        'trend_color' => null,
        'badge' => null,
        'target_type' => 'none',
        'target_value' => null,
        'kpi_source' => null,
        'widget_key' => null,
    ]);
});

it('toArray() carries every overridden property through untouched', function () {
    $preset = new PedidosPendentesCard;

    expect($preset->toArray())->toBe([
        'key' => 'pedidos_pendentes',
        'title' => 'Pedidos Pendentes',
        'subtitle' => 'Ponto de Venda',
        'icon' => 'heroicon-o-clock',
        'type' => 'kpi',
        'kpi_value' => '0',
        'unit' => 'enc.',
        'trend' => '+3 hoje',
        'trend_color' => 'success',
        'badge' => 'novo',
        'target_type' => 'resource',
        'target_value' => 'OrderResource',
        'kpi_source' => 'pedidos_pendentes',
        'widget_key' => null,
    ]);
});

it('always includes its own key(), overriding whatever title/type/target were set', function () {
    $preset = new RelatorioSemanal;

    expect($preset->toArray()['key'])->toBe('relatorio_semanal')
        ->and($preset->toArray()['type'])->toBe('shortcut')
        ->and($preset->toArray()['target_type'])->toBe('url')
        ->and($preset->toArray()['target_value'])->toBe('/admin/relatorios/semanal');
});
