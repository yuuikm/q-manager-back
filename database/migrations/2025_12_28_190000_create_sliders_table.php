<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('title')->nullable();
            $blueprint->text('description')->nullable();
            $blueprint->string('image_path');
            $blueprint->string('link_url')->nullable();
            $blueprint->integer('order')->default(0);
            $blueprint->boolean('is_active')->default(true);
            $blueprint->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};
