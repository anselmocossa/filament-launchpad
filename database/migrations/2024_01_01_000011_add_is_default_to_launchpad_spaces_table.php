<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the built-in "Início" Space. A default Space is seeded on install and
 * can never be deleted — it is the launchpad home every user always sees.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('launchpad_spaces', 'is_default')) {
            Schema::table('launchpad_spaces', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('icon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('launchpad_spaces', 'is_default')) {
            Schema::table('launchpad_spaces', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
