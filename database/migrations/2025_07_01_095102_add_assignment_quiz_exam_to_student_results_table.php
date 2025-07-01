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
        Schema::table('student_results', function (Blueprint $table) {
            $table->string('assignment')->default('0')->nullable()->after('semester'); // Adding assignment column
            $table->string('quiz')->default('0')->nullable()->after('assignment'); // Adding quiz column
            $table->string('exam')->default('0')->nullable()->after('quiz'); 
            $table->string('bonus_points_applied')->default('0')->nullable()->after('exam'); // Adding bonus points column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->dropColumn(['assignment', 'quiz', 'exam']); // Dropping the added columns
        });
    }
};
