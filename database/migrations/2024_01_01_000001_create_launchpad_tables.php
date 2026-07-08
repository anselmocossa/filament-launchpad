<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launchpad_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('launchpad_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('launchpad_spaces')->cascadeOnDelete();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('launchpad_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('launchpad_pages')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('title');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('launchpad_cards', function (Blueprint $table) {
            $table->id();
            $table->string('library_key')->nullable();
            $table->string('widget_key')->nullable();
            $table->string('widget_column_span')->nullable();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('icon')->nullable();
            $table->string('type')->default('kpi');
            $table->string('kpi_value')->nullable();
            $table->string('kpi_source')->nullable();
            $table->string('unit')->nullable();
            $table->string('trend')->nullable();
            $table->string('trend_color')->nullable();
            $table->string('badge')->nullable();
            $table->string('badge_bg')->nullable();
            $table->string('badge_color')->nullable();
            $table->string('note')->nullable();
            $table->string('target_type')->default('none');
            $table->string('target_value')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('launchpad_section_card', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('launchpad_sections')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('launchpad_cards')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->boolean('is_pinned')->default(true);
            $table->timestamps();

            $table->unique(['section_id', 'card_id']);
        });

        Schema::create('launchpad_user_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->foreignId('section_id')->constrained('launchpad_sections')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('launchpad_cards')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'section_id', 'card_id']);
        });

        Schema::create('launchpad_role_visibility', function (Blueprint $table) {
            $table->id();
            $table->string('visible_type');
            $table->unsignedBigInteger('visible_id');
            $table->unsignedBigInteger('role_id')->index();

            $table->index(['visible_type', 'visible_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launchpad_role_visibility');
        Schema::dropIfExists('launchpad_user_cards');
        Schema::dropIfExists('launchpad_section_card');
        Schema::dropIfExists('launchpad_cards');
        Schema::dropIfExists('launchpad_sections');
        Schema::dropIfExists('launchpad_pages');
        Schema::dropIfExists('launchpad_spaces');
    }
};
