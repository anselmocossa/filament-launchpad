<?php

use Filament\Launchpad\Launchpad\KpiResult;

it('only requires a value, everything else defaulting to null/gray/neutral colors', function () {
    $result = KpiResult::make(42);

    expect($result->getValue())->toBe(42)
        ->and($result->getUnit())->toBeNull()
        ->and($result->getTrend())->toBeNull()
        ->and($result->getTrendColor())->toBe('gray')
        ->and($result->getBadge())->toBeNull()
        ->and($result->getBadgeBg())->toBe('#f3f4f6')
        ->and($result->getBadgeColor())->toBe('#374151');
});

it('accepts a string value just as well as an int', function () {
    expect(KpiResult::make('128')->getValue())->toBe('128');
});

it('builds up unit/trend/badge fluently', function () {
    $result = KpiResult::make(120)
        ->unit('enc.')
        ->trend('+3 hoje', 'success')
        ->badge('3 novas', '#fef3c7', '#92400e');

    expect($result->getValue())->toBe(120)
        ->and($result->getUnit())->toBe('enc.')
        ->and($result->getTrend())->toBe('+3 hoje')
        ->and($result->getTrendColor())->toBe('success')
        ->and($result->getBadge())->toBe('3 novas')
        ->and($result->getBadgeBg())->toBe('#fef3c7')
        ->and($result->getBadgeColor())->toBe('#92400e');
});

it('lets trend()/badge() be called with a null text to clear them back out', function () {
    $result = KpiResult::make(1)
        ->trend('algo', 'danger')
        ->badge('algo', '#000', '#fff')
        ->trend(null)
        ->badge(null);

    expect($result->getTrend())->toBeNull()
        ->and($result->getBadge())->toBeNull();
});

it('defaults trend() color to gray and badge() colors to the neutral palette when omitted', function () {
    $result = KpiResult::make(1)->trend('+1')->badge('novo');

    expect($result->getTrendColor())->toBe('gray')
        ->and($result->getBadgeBg())->toBe('#f3f4f6')
        ->and($result->getBadgeColor())->toBe('#374151');
});

it('returns the same instance (static/fluent) from every setter, mirroring Tile/TileGroup', function () {
    $result = KpiResult::make(1);

    expect($result->unit('un'))->toBe($result)
        ->and($result->trend('+1'))->toBe($result)
        ->and($result->badge('novo'))->toBe($result);
});
