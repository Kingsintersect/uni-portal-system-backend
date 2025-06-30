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
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->constrained()->onDelete('cascade');
            $table->foreign('faculty_id')->references('id')->on('faculties');
            $table->unsignedBigInteger('department_id')->constrained()->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->string('level');
            $table->string('semester');
            $table->string('short_code')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_categories');
    }
};
