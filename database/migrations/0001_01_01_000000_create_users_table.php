<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

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

            // OTP Features
            $table->string('otp')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            
            // Advanced Security Features (Rate Limiting / Lockout)
            $table->integer('otp_attempts')->default(0);
            $table->timestamp('otp_locked_until')->nullable();

            // Created_at and updated_at timestamps
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};