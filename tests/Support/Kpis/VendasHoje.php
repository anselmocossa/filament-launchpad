<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;

/**
 * Simple class-based source with a custom label(), used to prove CA-01
 * (registered sources show up in getKpiSourceOptions() by their label, not
 * their raw key) and CA-07-adjacent class-based resolution.
 */
class VendasHoje extends BaseKpiSource
{
    public function label(): string
    {
        return 'Vendas de Hoje';
    }

    public function resolve(): KpiResult
    {
        return KpiResult::make(42)->unit('un');
    }
}
