<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launchpad_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('launchpad_sections')->cascadeOnDelete();
            $table->string('library_key')->nullable();
            $table->string('widget_key')->nullable();
            $table->string('widget_column_span')->nullable();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('icon')->nullable();
            $table->string('type')->default('kpi'); // kpi|shortcut|widget
            $table->string('kpi_value')->nullable();
            $table->string('kpi_source')->nullable();
            $table->string('unit')->nullable();
            $table->string('trend')->nullable();
            $table->string('trend_color')->nullable(); // success|danger|warning|gray
            $table->string('badge')->nullable();
            $table->string('badge_bg')->nullable();
            $table->string('badge_color')->nullable();
            $table->string('note')->nullable();
            $table->string('target_type')->default('none'); // enum: none|url|resource|page
            $table->string('target_value')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launchpad_cards');
    }
};
