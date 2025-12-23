<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Twilio\Rest\Client;

// Controller responsible for registration, login, and OTP verification
class AuthController extends Controller
{
    /**
     * REGISTER USER (NO OTP REQUIRED)
     * Stores user details with encrypted password
     */
    public function register(Request $request)
    {
        // Validate incoming request data
        $request->validate([
            'name' => 'required|string',
            'mobile' => 'required|digits:10|unique:users,mobile',
            'password' => 'required|min:6',
        ]);

        // Create a new user record
        User::create([
            'name' => $request->name,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password), // Encrypt password
        ]);

        // Return success response
        return response()->json([
            'status' => true,
            'message' => 'Registration successful'
        ]);
    }

    /**
     * LOGIN → GENERATE & SEND OTP VIA SMS
     * Validates credentials and sends OTP to user mobile number
     */
    public function login(Request $request)
    {
        // Validate login inputs
        $request->validate([
            'mobile' => 'required|digits:10',
            'password' => 'required',
        ]);

        // Fetch user using mobile number
        $user = User::where('mobile', $request->mobile)->first();

        // Check if user exists and password matches
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Generate a random 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP and expiry time in database
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Send OTP via Twilio SMS
        try {
            $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
            $twilio->messages->create(
                '+91' . $user->mobile, // User mobile number with country code
                [
                    'from' => env('TWILIO_NUMBER'), // SMS-enabled Twilio number
                    'body' => "Your OTP is: $otp. It will expire in 5 minutes."
                ]
            );
        } catch (\Exception $e) {
            // Handle SMS sending failure
            return response()->json([
                'status' => false,
                'message' => 'OTP sending failed: ' . $e->getMessage()
            ], 500);
        }

        // OTP sent successfully
        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your mobile number'
        ]);
    }

    /**
     * VERIFY OTP
     * Validates OTP and completes login process
     */
    public function verifyOtp(Request $request)
    {
        // Validate OTP input
        $request->validate([
            'mobile' => 'required|digits:10',
            'otp' => 'required|digits:6',
        ]);

        // Verify OTP and check expiry
        $user = User::where('mobile', $request->mobile)
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>=', Carbon::now())
            ->first();

        // If OTP is invalid or expired
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired OTP'
            ], 401);
        }

        // Clear OTP after successful verification (security best practice)
        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        // Return login success response
        return response()->json([
            'status' => true,
            'message' => 'Login successful'
        ]);
    }
}
