<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * A source that draws a small bar chart in the tile in place of its trend line.
 */
class SparklineSource extends BaseKpiSource
{
    public function resolve(): KpiResult
    {
        return KpiResult::make(120)
            ->trend('+20 este mês', 'success')
            ->sparkline([40, 60, 80, 100], 'bar', '#16a34a');
    }
}
