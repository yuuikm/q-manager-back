<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_materials')) {
            Schema::table('course_materials', function (Blueprint $table) {
                if (Schema::hasColumn('course_materials', 'duration_minutes')) {
                    $table->dropColumn('duration_minutes');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_materials')) {
            Schema::table('course_materials', function (Blueprint $table) {
                if (!Schema::hasColumn('course_materials', 'duration_minutes')) {
                    $table->integer('duration_minutes')->nullable();
                }
            });
        }
    }
};


