<?php

namespace Filament\Launchpad\Launchpad;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Contract for a "live source" feeding a launchpad tile — a class-based
 * replacement/complement for the legacy LaunchpadPlugin::kpiSources()
 * closures (Phase G). A source is pure: it never receives the Card or the
 * panel, only produces a KpiResult when asked.
 *
 * key() is stable and is what gets persisted on launchpad_cards.kpi_source
 * — renaming a class is safe as long as key() keeps returning the same
 * string, moving the class file is always safe.
 */
interface KpiSource
{
    /**
     * Stable identifier stored on Card::$kpi_source. Called statically (the
     * class is not instantiated just to learn its key), so registration by
     * class-string stays lazy.
     */
    public static function key(): string;

    /**
     * Human-readable label shown in the admin's kpi_source Select.
     */
    public function label(): string;

    /**
     * Performs the actual query/computation. Called at most once per
     * request per key by Support\KpiResolver, regardless of how many tiles
     * reference this source.
     */
    public function resolve(): KpiResult;

    /**
     * TTL in seconds for Cache::remember(), or null to never cache (resolve
     * fresh on every request/memoization miss).
     */
    public function cacheFor(): ?int;

    /**
     * Whether the given (possibly guest) user may see this source's value.
     * Returning false hides the value entirely — it degrades, it never
     * throws.
     */
    public function authorize(?Authenticatable $user): bool;
}
