<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change type column from enum to JSON
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // For MySQL, we need to drop the enum and add JSON
            DB::statement('ALTER TABLE courses MODIFY type JSON NULL');
            
            // Migrate existing data: convert single enum value to JSON array
            $courses = DB::table('courses')->get();
            foreach ($courses as $course) {
                if ($course->type) {
                    // If type is already a string (old enum value), convert to JSON array
                    $typeArray = json_decode($course->type, true);
                    if (!is_array($typeArray)) {
                        // It's a plain string, convert to array
                        DB::table('courses')
                            ->where('id', $course->id)
                            ->update(['type' => json_encode([$course->type])]);
                    }
                }
            }
        } else {
            // For other databases, use Schema builder
            Schema::table('courses', function (Blueprint $table) {
                $table->json('type')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Convert JSON array back to single enum value (take first element)
            $courses = DB::table('courses')->get();
            foreach ($courses as $course) {
                if ($course->type) {
                    $typeArray = json_decode($course->type, true);
                    if (is_array($typeArray) && count($typeArray) > 0) {
                        // Take first type from array
                        DB::table('courses')
                            ->where('id', $course->id)
                            ->update(['type' => $typeArray[0]]);
                    }
                }
            }
            
            // Change back to enum
            DB::statement("ALTER TABLE courses MODIFY type ENUM('online', 'self_learning', 'offline') DEFAULT 'online'");
        } else {
            Schema::table('courses', function (Blueprint $table) {
                $table->enum('type', ['online', 'self_learning', 'offline'])->default('online')->change();
            });
        }
    }
};

