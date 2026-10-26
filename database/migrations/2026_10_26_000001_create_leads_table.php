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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 50);
            $table->string('email', 100)->nullable();
            $table->text('question');
            $table->string('source', 50)->default('chat_faq');
            $table->string('status', 30)->default('new'); // new, in_progress, contacted, closed
            $table->boolean('is_faq_added')->default(false);
            $table->foreignId('faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('is_faq_added');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
