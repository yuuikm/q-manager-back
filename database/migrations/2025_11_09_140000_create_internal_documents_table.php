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
        if (!Schema::hasTable('internal_documents')) {
            Schema::create('internal_documents', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('file_path');
                $table->string('file_name');
                $table->string('file_type');
                $table->integer('file_size');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_documents');
    }
};

