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
        Schema::create('application_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->constrained()->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('application_payments');
            $table->string('gender');
            $table->string('lga');
            $table->string('hometown');
            $table->string('hometown_address');
            $table->string('contact_address');
            $table->string('religion');
            $table->string('disability');
            $table->string('other_disability')->nullable();
            $table->string('dob');
            $table->string('sponsor_name');
            $table->string('sponsor_relationship');
            $table->string('sponsor_phone_number');
            $table->string('sponsor_email')->nullable();
            $table->string('sponsor_contact_address');
            $table->boolean('awaiting_result')->default(false);
            $table->json('first_sitting')->nullable();
            $table->json('second_sitting')->nullable();
            $table->string('passport');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_forms');
    }
};
