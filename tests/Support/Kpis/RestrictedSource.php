<?php

namespace Filament\Launchpad\Tests\Support\Kpis;

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Always unauthorized — proves CA-06: the value is hidden (not merely
 * degraded to a placeholder) when authorize() returns false.
 */
class RestrictedSource extends BaseKpiSource
{
    public function authorize(?Authenticatable $user): bool
    {
        return false;
    }

    public function resolve(): KpiResult
    {
        return KpiResult::make(999);
    }
}
