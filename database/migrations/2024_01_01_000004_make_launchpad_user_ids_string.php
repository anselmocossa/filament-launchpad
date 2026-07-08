<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->stringifyUserId('launchpad_sections', nullable: true);
        $this->stringifyUserId('launchpad_user_cards', nullable: false);
    }

    public function down(): void
    {
        // Intentionally left as a no-op. Converting UUID/string user IDs back
        // to integers would be destructive for applications using UUID users.
    }

    protected function stringifyUserId(string $table, bool $nullable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $wrappedTable = DB::getQueryGrammar()->wrapTable($table);
            $wrappedColumn = DB::getQueryGrammar()->wrap('user_id');

            DB::statement("ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} TYPE varchar(255) USING {$wrappedColumn}::varchar");

            return;
        }

        Schema::table($table, function (Blueprint $table) use ($nullable): void {
            $column = $table->string('user_id');

            if ($nullable) {
                $column->nullable();
            }

            $column->change();
        });
    }
};
