<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;
use RuntimeException;

/**
 * Always throws from resolve() — proves CA-05: the resolver must degrade
 * this to an absent value instead of a 500.
 */
class ThrowingSource extends BaseKpiSource
{
    public function resolve(): KpiResult
    {
        throw new RuntimeException('boom');
    }
}
