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
     * Snake-cased class basename with a trailing "Kpi" stripped first, e.g.
     * TopUserKpi => 'top_user', EncomendasPendentes => 'encomendas_pendentes'.
     * Override when the persisted kpi_source key must differ from this.
     */
    public static function key(): string
    {
        return Str::of(static::bareBasename())->snake()->toString();
    }

    /**
     * Defaults to a headline-cased version of the class basename with a
     * trailing "Kpi" stripped first, e.g. TopUserKpi => 'Top User',
     * VendasHoje => 'Vendas Hoje'. Override for a translated/custom label.
     */
    public function label(): string
    {
        return Str::of(static::bareBasename())->headline()->toString();
    }

    public function cacheFor(): ?int
    {
        return null;
    }

    /**
     * Cache scope for this source's value (used by KpiResolver together with
     * cacheFor() to key the cache entry). Defaults to the source key. Override
     * in tenant- or context-scoped sources to append the scope so a cached
     * value never leaks across tenants, e.g.:
     *   return static::key().':'.tenancy()->tenant()?->id;
     */
    public function cacheKey(): string
    {
        return static::key();
    }

    public function authorize(?Authenticatable $user): bool
    {
        return true;
    }

    /**
     * Empty array = visible on every panel. Override to restrict this
     * source to specific panel ids, e.g. return ['admin'];
     *
     * @return array<int, string>
     */
    public function panels(): array
    {
        return [];
    }

    /**
     * The class basename with a single trailing "Kpi" removed, e.g.
     * TopUserKpi => 'TopUser'. A class literally named "Kpi" (or one not
     * ending in "Kpi" at all) is returned unchanged — key()/label() never
     * derive from an empty string.
     */
    protected static function bareBasename(): string
    {
        $basename = class_basename(static::class);

        if ($basename !== 'Kpi' && Str::endsWith($basename, 'Kpi')) {
            return Str::of($basename)->beforeLast('Kpi')->toString();
        }

        return $basename;
    }

    abstract public function resolve(): KpiResult;
}
