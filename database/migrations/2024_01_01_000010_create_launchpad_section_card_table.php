<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns Cards into a reusable catalog: previously a Card belonged to exactly
 * one Section (`launchpad_cards.section_id`); from here on a Card is global
 * (lives in /admin/cards) and Sections reference cards through this pivot,
 * so the SAME card can appear in several sections (authentic Fiori model).
 *
 * up() is 3 steps, each guarded so re-running (or running against a fresh
 * install that never had section_id) is a safe no-op:
 *   1. create the pivot table;
 *   2. backfill one pivot row per existing card that still has a
 *      section_id, carrying over its sort;
 *   3. drop the now-redundant section_id column (+ its FK) from
 *      launchpad_cards.
 *
 * down() reverses it: re-adds section_id (nullable), copies back the first
 * pivot row of each card, then drops the pivot table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('launchpad_section_card')) {
            Schema::create('launchpad_section_card', function (Blueprint $table) {
                $table->id();
                $table->foreignId('section_id')->constrained('launchpad_sections')->cascadeOnDelete();
                $table->foreignId('card_id')->constrained('launchpad_cards')->cascadeOnDelete();
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->unique(['section_id', 'card_id']);
            });
        }

        if (Schema::hasColumn('launchpad_cards', 'section_id')) {
            DB::table('launchpad_cards')
                ->whereNotNull('section_id')
                ->orderBy('id')
                ->each(function (object $card): void {
                    DB::table('launchpad_section_card')->insertOrIgnore([
                        'section_id' => $card->section_id,
                        'card_id' => $card->id,
                        'sort' => $card->sort ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('launchpad_cards', function (Blueprint $table) {
                $table->dropForeign(['section_id']);
                $table->dropColumn('section_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('launchpad_cards', 'section_id')) {
            Schema::table('launchpad_cards', function (Blueprint $table) {
                $table->foreignId('section_id')->nullable()->after('id')->constrained('launchpad_sections')->cascadeOnDelete();
            });

            if (Schema::hasTable('launchpad_section_card')) {
                DB::table('launchpad_section_card')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('card_id')
                    ->each(function ($rows, $cardId): void {
                        DB::table('launchpad_cards')
                            ->where('id', $cardId)
                            ->update(['section_id' => $rows->first()->section_id]);
                    });
            }
        }

        Schema::dropIfExists('launchpad_section_card');
    }
};
