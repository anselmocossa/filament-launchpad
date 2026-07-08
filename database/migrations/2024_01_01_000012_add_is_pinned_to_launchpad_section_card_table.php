<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A card placed in a section by the admin is either:
 *   - PINNED (is_pinned = true)  → injected for EVERY user, always visible,
 *     cannot be removed/reordered by the user (fixed).
 *   - AVAILABLE (is_pinned = false) → sits in the catalog; a user may add it
 *     to their own home and then reorder/remove their own copy.
 *
 * Existing rows default to pinned so current installs keep showing every card
 * to everyone (no behavioural change on upgrade).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('launchpad_section_card', 'is_pinned')) {
            Schema::table('launchpad_section_card', function (Blueprint $table) {
                $table->boolean('is_pinned')->default(true)->after('sort');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('launchpad_section_card', 'is_pinned')) {
            Schema::table('launchpad_section_card', function (Blueprint $table) {
                $table->dropColumn('is_pinned');
            });
        }
    }
};
