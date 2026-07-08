<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional owner for personal Home sections.
 *
 * user_id = null keeps the existing admin/global sections.
 * user_id = <id> makes the section visible/editable only for that user.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('launchpad_sections') || Schema::hasColumn('launchpad_sections', 'user_id')) {
            return;
        }

        Schema::table('launchpad_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('page_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('launchpad_sections') || ! Schema::hasColumn('launchpad_sections', 'user_id')) {
            return;
        }

        Schema::table('launchpad_sections', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
