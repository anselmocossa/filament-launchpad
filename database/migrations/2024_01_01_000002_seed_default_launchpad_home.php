<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantees exactly one default "Home" Space exists and is non-deletable.
 *
 * Idempotent and upgrade-safe:
 *   - if a default Space already exists, nothing happens;
 *   - if Spaces exist but none is default, the lowest-sorted one is promoted
 *     (so an existing home like "Home" simply becomes the protected default);
 *   - if there are no Spaces at all, a fresh "Home" Space + Page + Section
 *     is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('launchpad_spaces') || ! Schema::hasColumn('launchpad_spaces', 'is_default')) {
            return;
        }

        if (DB::table('launchpad_spaces')->where('is_default', true)->exists()) {
            return;
        }

        $existing = DB::table('launchpad_spaces')->orderBy('sort')->orderBy('id')->first();

        if ($existing !== null) {
            DB::table('launchpad_spaces')->where('id', $existing->id)->update(['is_default' => true]);

            return;
        }

        $now = now();

        $spaceId = DB::table('launchpad_spaces')->insertGetId([
            'label' => 'Home',
            'icon' => 'heroicon-o-home',
            'is_default' => true,
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pageId = DB::table('launchpad_pages')->insertGetId([
            'space_id' => $spaceId,
            'label' => 'Home',
            'icon' => 'heroicon-o-home',
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('launchpad_sections')->insert([
            'page_id' => $pageId,
            'title' => 'Home',
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Non-destructive: the default Space is intentionally left in place.
    }
};
