<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * A cached source (cacheFor() = 60s) that also counts real resolve() calls,
 * used by CA-03: within the TTL, a fresh resolver instance (i.e. a
 * different "request") must still hit the Cache facade and NOT bump the
 * counter again.
 */
class CachedSource extends BaseKpiSource
{
    public static int $calls = 0;

    public function cacheFor(): ?int
    {
        return 60;
    }

    public function resolve(): KpiResult
    {
        static::$calls++;

        return KpiResult::make(static::$calls);
    }
}
