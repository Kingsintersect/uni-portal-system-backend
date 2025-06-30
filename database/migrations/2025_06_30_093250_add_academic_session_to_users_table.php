<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('academic_session')->default('2023/2024')->after('level');
            $table->string('academic_semester')->default('1')->after('academic_session');
            $table->string('academic_level')->default('100')->after('academic_semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['academic_session', 'academic_semester', 'academic_level']);
        });
    }
};
