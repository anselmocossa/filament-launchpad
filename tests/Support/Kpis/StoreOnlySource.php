<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * Restricted to the 'store' panel via panels() — proves that a source is
 * only resolved/listed while the current panel (Support\LaunchpadPanel::id())
 * is in its panels() list, and hidden (exactly like an unregistered key)
 * everywhere else.
 */
class StoreOnlySource extends BaseKpiSource
{
    public function panels(): array
    {
        return ['store'];
    }

    public function resolve(): KpiResult
    {
        return KpiResult::make(7);
    }
}
