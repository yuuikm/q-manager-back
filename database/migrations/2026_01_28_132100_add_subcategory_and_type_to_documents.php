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
            // Add subcategory foreign key (nullable for backward compatibility)
            $table->foreignId('subcategory_id')->nullable()->constrained('document_subcategories')->onDelete('set null');
            
            // Add document type enum (nullable for backward compatibility)
            $table->enum('document_type', [
                'Документированные процедуры',
                'Карты основных процессов',
                'Карты поддерживающих процессов',
                'Карты управляющих процессов',
                'Руководство по качеству',
                'Производственные инструкции',
                'Руководство по надлежащей производственной практике',
            ])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn(['subcategory_id', 'document_type']);
        });
    }
};
