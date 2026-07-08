<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('launchpad_spaces') || Schema::hasColumn('launchpad_spaces', 'panel_id')) {
            return;
        }

        Schema::table('launchpad_spaces', function (Blueprint $table) {
            $table->string('panel_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('launchpad_spaces') || ! Schema::hasColumn('launchpad_spaces', 'panel_id')) {
            return;
        }

        Schema::table('launchpad_spaces', function (Blueprint $table) {
            $table->dropColumn('panel_id');
        });
    }
};
