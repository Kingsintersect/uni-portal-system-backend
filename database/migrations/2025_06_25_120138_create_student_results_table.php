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
        Schema::create('student_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->string('course_code')->nullable(); // Optional, if you want to store course code
            $table->string('course_title')->nullable(); // Optional, if you want to store course title
            $table->integer('credit_load')->default(0); // Default credit load, can be adjusted as needed
            $table->string('quality_point')->default(0); // Default quality point, can be adjusted as needed
            $table->string('level')->nullable(); 
            $table->string('session');
            $table->string('semester')->nullable(); 
            $table->decimal('score', 5, 2);
            $table->string('grade');
            $table->string('remarks')->nullable();
            $table->string('status')->default('published'); // e.g., pending, approved
            $table->string('date_of_result')->nullable(); // Date when the result was released
            $table->string('batch_id')->nullable(); // For tracking import batches
            $table->string('imported_from')->nullable(); // e.g., 'moodle', 'manual', etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students_results');
    }
};
