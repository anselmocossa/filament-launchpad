<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * Named literally "Kpi" (nothing left after stripping the "Kpi" suffix) —
 * edge case proving BaseKpiSource::key()/label() fall back to the untouched
 * basename instead of deriving from an empty string.
 */
class Kpi extends BaseKpiSource
{
    public function resolve(): KpiResult
    {
        return KpiResult::make(1);
    }
}
