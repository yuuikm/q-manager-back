<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (Schema::hasColumn('courses', 'max_students')) {
                    $table->dropColumn('max_students');
                }
                if (Schema::hasColumn('courses', 'duration_hours')) {
                    $table->dropColumn('duration_hours');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (!Schema::hasColumn('courses', 'max_students')) {
                    $table->integer('max_students')->nullable();
                }
                if (!Schema::hasColumn('courses', 'duration_hours')) {
                    $table->integer('duration_hours')->nullable();
                }
            });
        }
    }
};


