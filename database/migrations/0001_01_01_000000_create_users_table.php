<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Anonymous migration class (Laravel 12 standard)
return new class extends Migration {

    /**
     * Run the migrations.
     * This method creates the users table.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {

            // Primary key (auto-increment ID)
            $table->id();

            // User full name
            $table->string('name');

            // Mobile number (unique for each user)
            $table->string('mobile')->unique();

            // Encrypted password
            $table->string('password');

            // OTP for login verification (nullable because OTP is temporary)
            $table->string('otp')->nullable();

            // OTP expiration time (used to validate OTP timeout)
            $table->timestamp('otp_expires_at')->nullable();

            // Created_at and updated_at timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * This method drops the users table.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
