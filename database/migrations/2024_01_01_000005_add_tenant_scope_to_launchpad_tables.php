<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase H — multi-tenant launchpad (scope + copy-on-write override), in one
 * migration.
 *
 * `tenant_id` mirrors the semantics `panel_id` already established in
 * Space::scopeForPanel(): NULL means "belongs to everyone" (the shared
 * template), a value means "belongs to that tenant only". Existing rows become
 * the template for free, with no data copy.
 *
 * `origin_id` links a tenant's OVERRIDE row back to the template row it
 * replaces (copy-on-write); `is_hidden` on the structural tables is a
 * per-tenant tombstone.
 *
 * `launchpad_user_cards` is generalised in place (not renamed) into the overlay
 * table for both the tenant and user layers. Uniqueness moves to a derived
 * `scope_key` because the natural key now has nullable parts, and NULLs are
 * distinct in a UNIQUE index on PostgreSQL — `NULLS NOT DISTINCT` would fix it
 * but is PG15+ and not portable to the MySQL/SQLite installs this published
 * plugin also has to serve.
 *
 * Every step is guarded (hasColumn / try-catch) so the migration is idempotent
 * and safe to re-run on a partially-migrated schema.
 */
return new class extends Migration
{
    /**
     * Structural tables that gain tenant scope, in the column each new column
     * anchors after.
     *
     * @var array<string, string>
     */
    protected array $structuralTables = [
        'launchpad_spaces' => 'panel_id',
        'launchpad_pages' => 'space_id',
        'launchpad_sections' => 'page_id',
    ];

    public function up(): void
    {
        // Structural tables: tenant_id + origin_id + is_hidden.
        foreach ($this->structuralTables as $table => $after) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $after): void {
                if (! Schema::hasColumn($table, 'tenant_id')) {
                    $blueprint->string('tenant_id')->nullable()->after($after)->index();
                }
                if (! Schema::hasColumn($table, 'origin_id')) {
                    $blueprint->unsignedBigInteger('origin_id')->nullable()->index();
                }
                if (! Schema::hasColumn($table, 'is_hidden')) {
                    $blueprint->boolean('is_hidden')->default(false);
                }
            });
        }

        // Cards: tenant_id + origin_id (no is_hidden — cards hide/show per
        // tenant through launchpad_user_cards).
        if (Schema::hasTable('launchpad_cards')) {
            Schema::table('launchpad_cards', function (Blueprint $blueprint): void {
                if (! Schema::hasColumn('launchpad_cards', 'tenant_id')) {
                    $blueprint->string('tenant_id')->nullable()->after('id')->index();
                }
                if (! Schema::hasColumn('launchpad_cards', 'origin_id')) {
                    $blueprint->unsignedBigInteger('origin_id')->nullable()->index();
                }
            });
        }

        // Overlay table.
        if (! Schema::hasTable('launchpad_user_cards')) {
            return;
        }

        if (! Schema::hasColumn('launchpad_user_cards', 'tenant_id')) {
            Schema::table('launchpad_user_cards', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('user_id')->index();
                $table->boolean('is_hidden')->default(false)->after('card_id');
                $table->string('scope_key')->nullable()->after('tenant_id')->index();
            });
        }

        $this->dropUniqueIfExists('launchpad_user_cards', 'launchpad_user_cards_user_id_section_id_card_id_unique');

        // Backfill BEFORE relaxing NOT NULL, so every legacy row carries a
        // scope_key and the new unique index can be created without collisions.
        DB::table('launchpad_user_cards')
            ->whereNull('scope_key')
            ->update(['scope_key' => DB::raw($this->legacyScopeKeyExpression())]);

        $this->setUserIdNullable('launchpad_user_cards', nullable: true);

        $this->createUniqueIfMissing(
            'launchpad_user_cards',
            ['scope_key', 'section_id', 'card_id'],
            'launchpad_user_cards_scope_section_card_unique',
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('launchpad_user_cards')) {
            $this->dropUniqueIfExists('launchpad_user_cards', 'launchpad_user_cards_scope_section_card_unique');

            DB::table('launchpad_user_cards')
                ->where(function ($query): void {
                    $query->whereNull('user_id')->orWhere('is_hidden', true);
                })
                ->delete();

            if (Schema::hasColumn('launchpad_user_cards', 'tenant_id')) {
                Schema::table('launchpad_user_cards', function (Blueprint $table): void {
                    $table->dropColumn(['tenant_id', 'is_hidden', 'scope_key']);
                });
            }

            $this->setUserIdNullable('launchpad_user_cards', nullable: false);

            $this->createUniqueIfMissing(
                'launchpad_user_cards',
                ['user_id', 'section_id', 'card_id'],
                'launchpad_user_cards_user_id_section_id_card_id_unique',
            );
        }

        if (Schema::hasTable('launchpad_cards')) {
            Schema::table('launchpad_cards', function (Blueprint $blueprint): void {
                foreach (['tenant_id', 'origin_id'] as $column) {
                    if (Schema::hasColumn('launchpad_cards', $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }

        foreach (array_keys($this->structuralTables) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['tenant_id', 'origin_id', 'is_hidden'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * Legacy rows are all user-scoped, so their key is 't:|u:<user_id>' — the
     * same shape LaunchpadScope::key() produces in PHP.
     */
    protected function legacyScopeKeyExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "'t:|u:' || user_id"
            : "CONCAT('t:|u:', user_id)";
    }

    /**
     * Follows the driver split established by ..._000004_make_launchpad_user_ids_string:
     * on PostgreSQL a targeted ALTER COLUMN, everywhere else Blueprint::change().
     */
    protected function setUserIdNullable(string $table, bool $nullable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $wrappedTable = DB::getQueryGrammar()->wrapTable($table);
            $wrappedColumn = DB::getQueryGrammar()->wrap('user_id');
            $action = $nullable ? 'DROP NOT NULL' : 'SET NOT NULL';

            DB::statement("ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} {$action}");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($nullable): void {
            $blueprint->string('user_id')->nullable($nullable)->change();
        });
    }

    protected function dropUniqueIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropUnique($index);
            });
        } catch (Throwable) {
            // Already absent, or named differently by this driver.
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function createUniqueIfMissing(string $table, array $columns, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $index): void {
                $blueprint->unique($columns, $index);
            });
        } catch (Throwable) {
            // Already present.
        }
    }
};
