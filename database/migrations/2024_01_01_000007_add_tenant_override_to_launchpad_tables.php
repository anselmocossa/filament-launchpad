<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase H.3 — per-tenant copy-on-write override.
 *
 * The "Windows profile" model: the template (tenant_id null) is the system
 * default; each tenant inherits it and may diverge WITHOUT affecting the
 * template or any other tenant.
 *
 *   origin_id  a tenant's row that OVERRIDES a template row points at it here,
 *              so resolution can show the override in the template's place.
 *              Null on template rows and on a tenant's brand-new rows.
 *   is_hidden  a tenant HID an inherited row (a tombstone that only affects
 *              that tenant); the row carries origin_id + tenant_id + is_hidden.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    protected array $tables = [
        'launchpad_spaces',
        'launchpad_pages',
        'launchpad_sections',
        'launchpad_cards',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'origin_id')) {
                    $blueprint->unsignedBigInteger('origin_id')->nullable()->after('tenant_id')->index();
                }

                // Cards already hide/show per tenant through launchpad_user_cards;
                // the structural tables gain their own tombstone flag.
                if ($table !== 'launchpad_cards' && ! Schema::hasColumn($table, 'is_hidden')) {
                    $blueprint->boolean('is_hidden')->default(false)->after('origin_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'origin_id')) {
                    $blueprint->dropColumn('origin_id');
                }

                if ($table !== 'launchpad_cards' && Schema::hasColumn($table, 'is_hidden')) {
                    $blueprint->dropColumn('is_hidden');
                }
            });
        }
    }
};
