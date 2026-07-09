<?php

use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;
use Filament\Launchpad\Support\KpiResolver;

/**
 * A source whose cacheKey() and value both depend on an external "tenant"
 * holder — stands in for a tenant-scoped KPI. A fresh KpiResolver per call
 * bypasses the per-request memo so we exercise the Cache layer directly.
 */
function scopedSource(object $holder): BaseKpiSource
{
    return new class($holder) extends BaseKpiSource
    {
        public function __construct(public object $holder) {}

        public function cacheFor(): ?int
        {
            return 60;
        }

        public function cacheKey(): string
        {
            return 'vendas:'.$this->holder->tenant;
        }

        public function resolve(): KpiResult
        {
            return KpiResult::make($this->holder->value);
        }
    };
}

it('scopes the cache by the source cacheKey so values never leak across tenants', function () {
    $holder = new class
    {
        public string $tenant = 'A';

        public int $value = 1;
    };

    // Tenant A → 1, cached under vendas:A
    expect((int) (new KpiResolver)->resolve('vendas', fn () => scopedSource($holder))->getValue())->toBe(1);

    // Tenant B → 2: different cacheKey, so it must NOT read A's cached value
    $holder->tenant = 'B';
    $holder->value = 2;
    expect((int) (new KpiResolver)->resolve('vendas', fn () => scopedSource($holder))->getValue())->toBe(2);

    // Back to A within TTL: returns A's CACHED value (1), not the live 999
    $holder->tenant = 'A';
    $holder->value = 999;
    expect((int) (new KpiResolver)->resolve('vendas', fn () => scopedSource($holder))->getValue())->toBe(1);
});
