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
        Schema::create('course_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->constrained()->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses');
            $table->unsignedBigInteger('course_category_id')->constrained()->onDelete('cascade');
            $table->foreign('course_category_id')->references('id')->on('course_categories');
            $table->integer('credit_load');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_assignments');
    }
};
