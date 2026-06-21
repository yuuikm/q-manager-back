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
        Schema::table('document_purchases', function (Blueprint $table) {
            $table->string('payment_status')->default('created')->after('status');
            // payment_status: 'created', 'contract', 'paid'
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('payment_status')->default('created')->after('status');
            // payment_status: 'created', 'contract', 'paid'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_purchases', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
