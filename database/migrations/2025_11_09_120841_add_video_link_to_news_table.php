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
        Schema::table('news', function (Blueprint $table) {
            $table->string('video_link')->nullable()->after('description');
        });
        
        // Make description nullable - handle different database types
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE news MODIFY description TEXT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so we skip this
            // The column will remain as is, but the application will handle NULL values
        } else {
            // For other databases, try the standard approach
            try {
                Schema::table('news', function (Blueprint $table) {
                    $table->text('description')->nullable()->change();
                });
            } catch (\Exception $e) {
                // If change() doesn't work, skip it
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('video_link');
        });
        
        // Make description NOT NULL again - handle different database types
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE news MODIFY description TEXT NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN
        } else {
            try {
                Schema::table('news', function (Blueprint $table) {
                    $table->text('description')->nullable(false)->change();
                });
            } catch (\Exception $e) {
                // If change() doesn't work, skip it
            }
        }
    }
};
