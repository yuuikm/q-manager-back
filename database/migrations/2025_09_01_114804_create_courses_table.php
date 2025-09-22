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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->longText('content');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->enum('type', ['online', 'self_learning', 'offline'])->default('online');
            $table->string('category');
            $table->string('featured_image')->nullable();
            $table->string('certificate_template')->nullable();
            $table->integer('max_students')->nullable();
            $table->integer('current_students')->default(0);
            $table->integer('duration_hours')->nullable();
            $table->text('requirements')->nullable();
            $table->text('learning_outcomes')->nullable();
            $table->string('zoom_link')->nullable(); // For online courses
            $table->json('schedule')->nullable(); // For offline courses
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('enrollments_count')->default(0);
            $table->integer('completion_rate')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
