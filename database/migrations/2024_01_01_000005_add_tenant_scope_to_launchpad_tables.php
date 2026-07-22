<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase H — tenant scope.
 *
 * `tenant_id` mirrors the semantics `panel_id` already established in
 * Space::scopeForPanel(): NULL means "belongs to everyone" (the parent's
 * global template), a value means "belongs to that tenant only". Existing
 * rows therefore become the parent template for free, with no data copy.
 *
 * `launchpad_user_cards` is generalised in place (not renamed, not duplicated)
 * into the overlay table for BOTH scopes:
 *   - is_hidden = false → a card ADDED by that scope
 *   - is_hidden = true  → a parent card HIDDEN by that scope (tombstone)
 *
 * Uniqueness moves to a derived `scope_key` because the natural key now has
 * nullable parts, and NULLs are distinct in a UNIQUE index on PostgreSQL —
 * `NULLS NOT DISTINCT` would fix it but is PG15+ and not portable to the
 * MySQL/SQLite installs this published plugin also has to serve.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    protected array $tenantTables = [
        'launchpad_spaces' => 'panel_id',
        'launchpad_pages' => 'space_id',
        'launchpad_sections' => 'page_id',
    ];

    public function up(): void
    {
        foreach ($this->tenantTables as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($after): void {
                $blueprint->string('tenant_id')->nullable()->after($after)->index();
            });
        }

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

        // The old unique key assumed user_id was always present. Drop it before
        // relaxing the column, then re-key on scope_key. Wrapped because the
        // index name differs across drivers and may already be gone.
        $this->dropUniqueIfExists('launchpad_user_cards', 'launchpad_user_cards_user_id_section_id_card_id_unique');

        // Backfill BEFORE the NOT NULL is relaxed, so every legacy row carries a
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

            // Tenant-scoped and tombstone rows have no meaning in the pre-Phase H
            // schema and would violate the restored NOT NULL / unique key.
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

        foreach (array_keys($this->tenantTables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('tenant_id');
            });
        }
    }

    /**
     * Legacy rows are all user-scoped, so their key is 't:|u:<user_id>' — the
     * same shape LaunchpadScope::key() produces in PHP.
     */
    protected function legacyScopeKeyExpression(): string
    {
        $concat = DB::connection()->getDriverName() === 'sqlite'
            ? "'t:|u:' || user_id"
            : "CONCAT('t:|u:', user_id)";

        return $concat;
    }

    /**
     * Follows the driver split established by ..._000004_make_launchpad_user_ids_string:
     * on PostgreSQL a targeted ALTER COLUMN, everywhere else Blueprint::change().
     * A plain change() on pgsql would have to restate the column type, which is
     * exactly what that earlier migration went out of its way to avoid.
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
            // Already absent, or named differently by this driver — the
            // subsequent unique index is what actually matters.
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
