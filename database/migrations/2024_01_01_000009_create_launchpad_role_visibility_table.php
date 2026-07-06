<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic pivot recording which Spatie roles may see a given launchpad
 * item (Space/Page/Section/Card). Deliberately has NO foreign key to the
 * `roles` table: the migration must run even when spatie/laravel-permission
 * is not installed at all (SOFT integration — see LaunchpadVisibility and
 * HasLaunchpadVisibility). An item with zero rows here is unrestricted
 * (everyone sees it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launchpad_role_visibility', function (Blueprint $table) {
            $table->id();
            $table->string('visible_type');
            $table->unsignedBigInteger('visible_id');
            $table->unsignedBigInteger('role_id');

            $table->index(['visible_type', 'visible_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launchpad_role_visibility');
    }
};
