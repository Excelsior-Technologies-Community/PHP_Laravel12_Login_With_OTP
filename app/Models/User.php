<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// User model represents the 'users' table in the database
class User extends Authenticatable
{
    // Enables API token generation (Sanctum) and notification features
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     * These fields can be inserted/updated using create() or update().
     */
    protected $fillable = [
        'name',             // User full name
        'mobile',           // User mobile number (used for login)
        'password',         // Encrypted user password
        'otp',              // One-Time Password for login verification
        'otp_expires_at',   // OTP expiry timestamp
        'otp_attempts',     // Number of wrong OTP attempts (New Feature)
        'otp_locked_until', // Account lockout timestamp (New Feature)
    ];

    /**
     * The attributes that should be hidden when returning JSON responses.
     * This prevents sensitive data from being exposed in API responses.
     */
    protected $hidden = [
        'password', // Hide password from API output
        'otp',      // Hide OTP from API output
    ];

    /**
     * The attributes that should be cast to native types.
     * This automatically converts string dates to Carbon Date instances.
     */
    protected $casts = [
        'otp_expires_at' => 'datetime',
        'otp_locked_until' => 'datetime',
        'otp_attempts' => 'integer',
    ];
}