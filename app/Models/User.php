<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// User model represents the 'users' table in the database
class User extends Authenticatable
{
    // Enables notification features (SMS, email, etc.)
    use Notifiable;

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
    ];

    /**
     * The attributes that should be hidden when returning JSON responses.
     * This prevents sensitive data from being exposed in API responses.
     */
    protected $hidden = [
        'password', // Hide password from API output
        'otp',      // Hide OTP from API output
    ];
}
    