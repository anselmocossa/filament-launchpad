<?php

use Filament\Launchpad\Tests\Support\Kpis\CachedSource;
use Filament\Launchpad\Tests\Support\Kpis\Kpi;
use Filament\Launchpad\Tests\Support\Kpis\TopUserKpi;
use Filament\Launchpad\Tests\Support\Kpis\VendasHoje;

/**
 * key()/label() derivation, including the new-design rule: a class named
 * with the "...Kpi" generator suffix (e.g. TopUserKpi) has that suffix
 * stripped BEFORE snake/headline-casing, so it never leaks into the
 * persisted key or the admin-facing label.
 */
it('strips a trailing "Kpi" suffix before deriving key() and label()', function () {
    expect(TopUserKpi::key())->toBe('top_user')
        ->and((new TopUserKpi)->label())->toBe('Top User');
});

it('keeps the class name unchanged for a class not ending in "Kpi"', function () {
    expect(VendasHoje::key())->toBe('vendas_hoje')
        ->and(CachedSource::key())->toBe('cached_source');
});

it('falls back to the untouched basename for a class literally named "Kpi"', function () {
    expect(Kpi::key())->toBe('kpi')
        ->and((new Kpi)->label())->toBe('Kpi');
});

it('defaults panels() to an empty array, meaning visible on every panel', function () {
    expect((new TopUserKpi)->panels())->toBe([]);
});
