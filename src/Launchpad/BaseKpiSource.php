<?php

namespace Filament\Launchpad\Launchpad;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * Optional base class for KpiSource implementations. Reduces boilerplate for
 * the common case: a stable, auto-derived key(), no caching, visible to
 * everyone — a concrete source then only has to implement resolve() (and
 * override label()/cacheFor()/authorize() when it needs to).
 *
 * The make:launchpad-kpi stub (a separate agent's responsibility) is
 * expected to extend this class.
 */
abstract class BaseKpiSource implements KpiSource
{
    /**
     * Snake-cased class basename, e.g. EncomendasPendentes => 'encomendas_pendentes'.
     * Override when the persisted kpi_source key must differ from this.
     */
    public static function key(): string
    {
        return Str::of(class_basename(static::class))->snake()->toString();
    }

    /**
     * Defaults to a headline-cased version of the class basename, e.g.
     * VendasHoje => 'Vendas Hoje'. Override for a translated/custom label.
     */
    public function label(): string
    {
        return Str::of(class_basename(static::class))->headline()->toString();
    }

    public function cacheFor(): ?int
    {
        return null;
    }

    public function authorize(?Authenticatable $user): bool
    {
        return true;
    }

    abstract public function resolve(): KpiResult;
}
