<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user personalisation layer. Each row is a catalog card that a specific
 * user added to a section of their own home, with their own ordering. Users
 * only ever touch their own rows; the admin's pinned cards live in
 * launchpad_section_card and are never copied here.
 *
 * user_id is a plain unsigned big int (no FK) so the package installs without
 * assuming the host's users table name/PK type.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('launchpad_user_cards')) {
            return;
        }

        Schema::create('launchpad_user_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('section_id')->constrained('launchpad_sections')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('launchpad_cards')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'section_id', 'card_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launchpad_user_cards');
    }
};
