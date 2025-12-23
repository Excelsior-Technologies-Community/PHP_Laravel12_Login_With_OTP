<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

// User registration route (no OTP required)
Route::post('/register', [AuthController::class, 'register']);

// Login route – validates credentials and sends OTP to mobile number
Route::post('/login', [AuthController::class, 'login']);

// OTP verification route – verifies OTP and completes login
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
