<?php

namespace Filament\Launchpad\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Remembers which launchpad tables and columns exist.
 *
 * The plugin checks the schema (does `launchpad_spaces` have `tenant_id`?
 * `origin_id`?) so it keeps working on installs that have not run the newer
 * migrations. Asking the database every time costs one catalog query per
 * check: on SQL Server each one joins sys.columns, sys.types and friends, and
 * a single home page asked five of them on every request.
 *
 * The answer only changes when a migration runs, so it is kept in the
 * application cache (one read per request instead of one query per check)
 * and forgotten whenever migrations finish. Only tables that exist are
 * cached: a missing table is checked again next time, so creating it never
 * needs a flush to be seen.
 */
final class LaunchpadSchema
{
    public const CACHE_KEY = 'filament-launchpad:schema:v1';

    /**
     * Container key of the in-memory copy. It lives in the application
     * container, not in a static, so a fresh application (a new test, a new
     * Octane worker app) never sees what an earlier one remembered.
     */
    private const MEMORY = 'filament-launchpad.schema';

    public static function hasTable(string $table, ?string $connection = null): bool
    {
        return self::columns($table, $connection) !== null;
    }

    public static function hasColumn(string $table, string $column, ?string $connection = null): bool
    {
        $columns = self::columns($table, $connection);

        return $columns !== null && in_array(strtolower($column), $columns, true);
    }

    /**
     * Column names of $table in lower case, or null when the table does not exist.
     *
     * @return array<int, string>|null
     */
    public static function columns(string $table, ?string $connection = null): ?array
    {
        $connection ??= DB::getDefaultConnection();
        $key = $connection.'|'.$table;

        $memory = self::memory();

        if ($memory->offsetExists($key)) {
            return $memory[$key];
        }

        $builder = DB::connection($connection)->getSchemaBuilder();

        if (! $builder->hasTable($table)) {
            return null;
        }

        $memory[$key] = array_map('strtolower', $builder->getColumnListing($table));
        self::writeCache($memory->getArrayCopy());

        return $memory[$key];
    }

    /**
     * Forget everything. Called when migrations finish; call it yourself after
     * changing a launchpad table outside a migration.
     */
    public static function flush(): void
    {
        app()->forgetInstance(self::MEMORY);

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // No usable cache store: the in-memory copy above was all there was.
        }
    }

    /**
     * @return \ArrayObject<string, array<int, string>>
     */
    private static function memory(): \ArrayObject
    {
        $app = app();

        if (! $app->bound(self::MEMORY)) {
            $app->instance(self::MEMORY, new \ArrayObject(self::readCache()));
        }

        return $app->make(self::MEMORY);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function readCache(): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (\Throwable) {
            return [];
        }

        return is_array($cached) ? $cached : [];
    }

    /**
     * @param  array<string, array<int, string>>  $columns
     */
    private static function writeCache(array $columns): void
    {
        try {
            Cache::forever(self::CACHE_KEY, $columns);
        } catch (\Throwable) {
            // A cache that cannot be written only costs the catalog queries again.
        }
    }
}
