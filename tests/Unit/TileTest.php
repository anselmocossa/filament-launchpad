<?php

use Filament\Launchpad\Launchpad\Tile;

it('exposes the kpi variant when a kpi is set', function () {
    $tile = Tile::make('Vendas Hoje')->kpi(fn () => '10');

    expect($tile->hasKpi())->toBeTrue()
        ->and($tile->toArray()['hasKpi'])->toBeTrue()
        ->and($tile->toArray()['kpi'])->toBe('10');
});

it('exposes the icon-only variant when no kpi is set', function () {
    $tile = Tile::make('Produtos');

    expect($tile->hasKpi())->toBeFalse()
        ->and($tile->toArray()['hasKpi'])->toBeFalse()
        ->and($tile->toArray()['kpi'])->toBeNull();
});

it('maps logical trend colors to their design hex values', function () {
    expect(Tile::make('A')->trend('x', 'success')->toArray()['trendColor'])->toBe('#16a34a')
        ->and(Tile::make('B')->trend('x', 'danger')->toArray()['trendColor'])->toBe('#dc2626')
        ->and(Tile::make('C')->trend('x', 'warning')->toArray()['trendColor'])->toBe('#d97706')
        ->and(Tile::make('D')->trend('x', 'gray')->toArray()['trendColor'])->toBe('#6b7280')
        ->and(Tile::make('E')->trend('x', 'unknown')->toArray()['trendColor'])->toBe('#6b7280');
});

it('resolves a plain url target', function () {
    expect(Tile::make('X')->url('/x')->getUrl())->toBe('/x');
});

it('resolves a closure url target', function () {
    expect(Tile::make('X')->url(fn () => '/dynamic')->getUrl())->toBe('/dynamic');
});

it('has no target and does not crash when nothing is configured', function () {
    $tile = Tile::make('Inert');

    expect($tile->getUrl())->toBeNull();
    expect(fn () => $tile->toArray())->not->toThrow(Throwable::class);
});

it('degrades a throwing kpi closure to an em dash instead of crashing', function () {
    $tile = Tile::make('Erro')->kpi(fn () => throw new RuntimeException('boom'));

    expect(fn () => $tile->toArray())->not->toThrow(Throwable::class);
    expect($tile->toArray()['kpi'])->toBe('—');
});

it('carries badge, subtitle, icon and note through to the array', function () {
    $tile = Tile::make('Produtos')
        ->subtitle('Catálogo')
        ->icon('heroicon-o-cube')
        ->badge('24', '#eef2ff', '#3730a3')
        ->note('novo');

    $data = $tile->toArray();

    expect($data['t'])->toBe('Produtos')
        ->and($data['s'])->toBe('Catálogo')
        ->and($data['icon'])->toBe('heroicon-o-cube')
        ->and($data['badge'])->toBe('24')
        ->and($data['badgeBg'])->toBe('#eef2ff')
        ->and($data['badgeColor'])->toBe('#3730a3')
        ->and($data['nota'])->toBe('novo');
});

it('degrades a resource whose route does not exist in this panel to no target, instead of taking the page down', function () {
    // Cards live in one global catalog while resources are registered per
    // panel, so a tile can end up pointing at a resource this panel never
    // registered. getUrl('index') then throws RouteNotFoundException — which,
    // unhandled, replaces the user's home page with a stack trace.
    $resource = new class
    {
        public static function getUrl(string $name = 'index'): string
        {
            throw new Symfony\Component\Routing\Exception\RouteNotFoundException(
                'Route [filament.colaborador.resources.lista-exclusao-avaliacaos.index] not defined.'
            );
        }
    };

    $tile = Tile::make('Lista de Exclusão')->resource($resource::class);

    expect($tile->getUrl())->toBeNull();
});

it('degrades a page whose route does not exist to no target as well', function () {
    $page = new class
    {
        public static function getUrl(): string
        {
            throw new Symfony\Component\Routing\Exception\RouteNotFoundException('nope');
        }
    };

    expect(Tile::make('Página')->page($page::class)->getUrl())->toBeNull();
});

it('degrades a throwing url closure to no target', function () {
    $tile = Tile::make('X')->url(fn () => throw new RuntimeException('boom'));

    expect($tile->getUrl())->toBeNull();
});
