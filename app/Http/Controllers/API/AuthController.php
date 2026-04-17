<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * REGISTER (mobile-based)
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'mobile' => 'required|digits:10|unique:users,mobile',
        ]);

        $user = User::create([
            'name' => $request->name,
            'mobile' => $request->mobile,
            'password' => Hash::make('123456'), // default password
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ]);
    }

    /**
     * LOGIN → SEND OTP
     */
    public function login(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10',
        ]);

        $user = User::where('mobile', $request->mobile)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile number not registered'
            ], 404);
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'otp_attempts' => 0
        ]);

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully',
            'otp' => $otp,
            'expires_in' => 5 // minutes, for countdown
        ]);
    }

    /**
     * VERIFY OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('mobile', $request->mobile)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // LOCKOUT TIMER: block 15 min after 3 wrong attempts
        if ($user->otp_attempts >= 3 && $user->otp_locked_until && $user->otp_locked_until > Carbon::now()) {
            $blockedFor = Carbon::parse($user->otp_locked_until)->diffInMinutes(Carbon::now());
            return response()->json([
                'status' => false,
                'message' => "Account blocked. Try again in $blockedFor minutes"
            ], 403);
        }

        if ($user->otp != $request->otp) {
            $user->increment('otp_attempts');

            // set lockout time if attempts >=3
            if ($user->otp_attempts >= 3) {
                $user->otp_locked_until = Carbon::now()->addMinutes(15);
                $user->save();
            }

            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ], 401);
        }

        if ($user->otp_expires_at < Carbon::now()) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired'
            ], 401);
        }

        // Reset OTP and attempts
        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_locked_until' => null
        ]);

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Save login history
        DB::table('login_histories')->insert([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'login_time' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token
        ]);
    }

    /**
     * DASHBOARD
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $loginHistories = DB::table('login_histories')
            ->where('user_id', $user->id)
            ->orderBy('login_time', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'user' => $user,
            'login_history' => $loginHistories
        ]);
    }

    /**
     * LOGOUT (current device)
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * LOGOUT ALL DEVICES
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete(); // all tokens

        return response()->json([
            'status' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }
}