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
        // First, drop foreign key constraints
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['course_category_id']);
            $table->dropColumn('course_category_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_category_id']);
            $table->dropColumn('document_category_id');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropForeign(['news_category_id']);
            $table->dropColumn('news_category_id');
        });

        // Now drop the incorrectly created pivot tables
        Schema::dropIfExists('course_categories');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('news_categories');

        // Remove unnecessary fields from categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'color', 'icon', 'is_active', 'sort_order']);
        });

        // Create proper pivot tables for many-to-many relationships
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['course_id', 'category_id']);
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['document_id', 'category_id']);
        });

        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['news_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new pivot tables
        Schema::dropIfExists('course_categories');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('news_categories');

        // Recreate the old pivot tables with full category structure
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#667eea');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#667eea');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#667eea');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Add back the removed fields to categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#667eea');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
        });

        // Add back the foreign key columns
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('course_category_id')->nullable()->constrained('course_categories');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('document_category_id')->nullable()->constrained('document_categories');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->foreignId('news_category_id')->nullable()->constrained('news_categories');
        });
    }
};
