<?php

namespace Filament\Launchpad\Support;

use Closure;
use Filament\Launchpad\Launchpad\KpiResult;
use Filament\Launchpad\Launchpad\KpiSource;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Resolves a KpiSource (by its persisted key) into a KpiResult, lazily,
 * memoized per-request, cached per its own cacheFor() TTL, gated by its own
 * authorize(), and degrading gracefully (never throwing) on failure.
 *
 * Lives as a property on LaunchpadPlugin (see kpiResolver()): the plugin
 * instance registered on the panel is itself already a natural per-request
 * singleton (Filament resolves/keeps it via Facades\Filament for the
 * lifetime of the request), so a resolver instance living alongside it gets
 * request-scoped memoization for free without needing a separate container
 * binding.
 */
class KpiResolver
{
    /**
     * @var array<string, KpiResult|null>
     */
    protected array $memo = [];

    /**
     * $factory is only ever invoked once per $key per resolver instance
     * (lazy instantiation + memoization in one place): a cache/memo hit
     * never re-instantiates the source class, and 3 tiles referencing the
     * same key only ever call resolve() once.
     */
    public function resolve(string $key, Closure $factory): ?KpiResult
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->resolveViaFactory($key, $factory);
    }

    protected function resolveViaFactory(string $key, Closure $factory): ?KpiResult
    {
        try {
            /** @var KpiSource|null $source */
            $source = $factory();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($source === null) {
            return null;
        }

        if (! $source->authorize(auth()->user())) {
            return null;
        }

        return $this->resolveSource($key, $source);
    }

    protected function resolveSource(string $key, KpiSource $source): ?KpiResult
    {
        $ttl = $source->cacheFor();

        try {
            if ($ttl !== null) {
                // The cache scope comes from the source (cacheKey()), not the
                // bare key, so tenant-/context-scoped sources can keep their
                // cached values isolated instead of leaking across tenants.
                $scope = method_exists($source, 'cacheKey') ? $source->cacheKey() : $key;

                return Cache::remember("launchpad.kpi.{$scope}", $ttl, fn (): KpiResult => $source->resolve());
            }

            return $source->resolve();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
