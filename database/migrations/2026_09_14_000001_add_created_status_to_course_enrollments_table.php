<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `course_enrollments` MODIFY COLUMN `status` ENUM('created', 'enrolled', 'in_progress', 'completed', 'dropped') NOT NULL DEFAULT 'created'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `course_enrollments` MODIFY COLUMN `status` ENUM('enrolled', 'in_progress', 'completed', 'dropped') NOT NULL DEFAULT 'enrolled'");
    }
};
