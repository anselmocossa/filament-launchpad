<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * A source meant to feed a "shortcut" (atalho) tile's badge/trend, not a
 * KPI value — proves CA-04 (badge/trend fall back to the source's result
 * even on a non-'kpi' typed card).
 */
class BadgeShortcutSource extends BaseKpiSource
{
    public function resolve(): KpiResult
    {
        return KpiResult::make(3)
            ->badge('3 pendentes', '#fef3c7', '#92400e')
            ->trend('+1 hoje', 'warning');
    }
}
