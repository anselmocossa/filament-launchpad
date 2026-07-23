<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase H.2 — tenant ownership for cards.
 *
 * Cards were a globally shared catalogue. Once a shopkeeper can manage the full
 * launchpad tree, an unscoped catalogue would let one store edit or delete a
 * card another store — or the parent's template — depends on. `tenant_id`
 * mirrors the same "null means everyone" rule already used on
 * spaces/pages/sections: a null card is the parent's template card, a valued
 * one belongs to that store alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('launchpad_cards') || Schema::hasColumn('launchpad_cards', 'tenant_id')) {
            return;
        }

        Schema::table('launchpad_cards', function (Blueprint $table): void {
            $table->string('tenant_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('launchpad_cards') || ! Schema::hasColumn('launchpad_cards', 'tenant_id')) {
            return;
        }

        Schema::table('launchpad_cards', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
