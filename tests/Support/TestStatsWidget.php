<?php

namespace Filament\Launchpad\Tests\Support;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * A minimal, real StatsOverviewWidget used across Fase D tests to prove the
 * launchpad/Builder render REAL Filament widgets natively — no fixtures or
 * mocks standing in for the renderer itself.
 */
class TestStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Widget de Teste', '42'),
        ];
    }
}
