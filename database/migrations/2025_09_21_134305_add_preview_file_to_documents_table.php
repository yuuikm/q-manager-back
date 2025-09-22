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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('preview_file_path')->nullable()->after('file_path');
            $table->string('preview_file_name')->nullable()->after('file_name');
            $table->integer('preview_file_size')->nullable()->after('file_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['preview_file_path', 'preview_file_name', 'preview_file_size']);
        });
    }
};