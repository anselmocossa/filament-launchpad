<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * Named with the generator's "Kpi" suffix on purpose — proves
 * BaseKpiSource::key()/label() strip a trailing "Kpi" before deriving,
 * e.g. TopUserKpi => key 'top_user', label 'Top User'.
 */
class TopUserKpi extends BaseKpiSource
{
    public function resolve(): KpiResult
    {
        return KpiResult::make(1);
    }
}
