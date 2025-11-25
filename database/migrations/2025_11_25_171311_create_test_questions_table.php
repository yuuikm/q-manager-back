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
        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->onDelete('cascade');
            $table->text('question');
            $table->string('type')->default('single_choice'); // single_choice, multiple_choice, true_false, text
            $table->json('options')->nullable(); // Array of answer options
            $table->text('correct_answer');
            $table->integer('points')->default(1);
            $table->text('explanation')->nullable();
            $table->integer('order')->default(0); // Question order in the test
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_questions');
    }
};
