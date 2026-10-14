<?php

use Filament\Launchpad\Support\Sparkline;

it('returns null for an empty or non-numeric series', function () {
    expect(Sparkline::geometry([]))->toBeNull()
        ->and(Sparkline::geometry(['a', null]))->toBeNull();
});

it('draws an area series from the first to the last value across the full width', function () {
    $geometry = Sparkline::geometry([0, 10, 5]);

    expect($geometry['type'])->toBe('area')
        ->and($geometry['line'])->toBe('M0 27 L50 1 L100 14')
        ->and($geometry['area'])->toBe('M0 27 L50 1 L100 14 L100 28 L0 28 Z');
});

it('draws a single value as a flat line instead of a lone point', function () {
    expect(Sparkline::geometry([7])['line'])->toBe('M0 1 L100 1');
});

it('keeps a series of zeros on the floor', function () {
    expect(Sparkline::geometry([0, 0, 0])['line'])->toBe('M0 27 L50 27 L100 27');
});

it('draws one bar per value, the tallest reaching the top and zeros as a hairline', function () {
    $bars = Sparkline::geometry([0, 4, 8], 'bar')['bars'];

    expect($bars)->toHaveCount(3)
        ->and($bars[2]['y'])->toEqual(1)
        ->and($bars[2]['h'])->toEqual(26)
        ->and($bars[1]['h'])->toEqual(13)
        ->and($bars[0]['h'])->toEqual(0.8)
        ->and($bars[0]['x'])->toBeLessThan($bars[1]['x']);
});
