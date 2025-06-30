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
        Schema::create('application_payments', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('other_name');
            $table->string('level')->nullable();
            $table->foreignId('faculty_id')->constrained('faculty')->onUpdate('cascade');
            $table->foreignId('department_id')->constrained('department')->onUpdate('cascade');
            $table->string('nationality');
            $table->string('state');
            $table->string('phone_number');
            $table->string('email');
            $table->string('password');
            $table->string('reference');
            $table->integer('amount');
            $table->string('reg_number')->unique()->nullable();
            $table->boolean('is_applied')->default(false);
            $table->text('reason_for_denial')->nullable();
            $table->enum('admission_status', ['admitted', 'not admitted', 'pending'])->default('pending');
            $table->boolean('accpetance_fee_payment_status')->default(false);
            $table->integer('tuition_amount_paid')->default(0);
            $table->boolean('tuition_payment_status')->default(false);
            $table->boolean('application_payment_status')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_payments');
    }
};
