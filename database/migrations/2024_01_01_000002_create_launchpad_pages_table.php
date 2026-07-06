<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launchpad_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('launchpad_spaces')->cascadeOnDelete();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launchpad_pages');
    }
};
