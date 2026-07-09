<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * Counts how many times resolve() actually runs (via a static counter, so
 * it survives being instantiated more than once). Used by CA-02's
 * memoization proof: 3 tiles referencing this source in the same request
 * must only bump the counter once.
 */
class CountingSource extends BaseKpiSource
{
    public static int $calls = 0;

    public function resolve(): KpiResult
    {
        static::$calls++;

        return KpiResult::make(static::$calls);
    }
}
