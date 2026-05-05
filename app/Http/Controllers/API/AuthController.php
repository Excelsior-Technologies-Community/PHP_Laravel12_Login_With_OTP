<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    private function sendTwilioSms($mobile, $otp)
    {
        try {
            $sid = env('TWILIO_SID');
            $token = env('TWILIO_AUTH_TOKEN');
            $from = env('TWILIO_PHONE_NUMBER');
            $to = "+91" . $mobile;

            $client = new Client($sid, $token);
            $client->messages->create($to, [
                'from' => $from,
                'body' => "Your login OTP is $otp. It will expire in 5 minutes."
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Twilio Error: " . $e->getMessage());
            return false;
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'mobile' => 'required|digits:10|unique:users,mobile',
        ]);

        $user = User::create([
            'name' => $request->name,
            'mobile' => $request->mobile,
            'password' => Hash::make('123456'),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ]);
    }

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

        if ($user->otp_attempts >= 3 && $user->otp_locked_until && $user->otp_locked_until > Carbon::now()) {
            $blockedFor = Carbon::parse($user->otp_locked_until)->diffInMinutes(Carbon::now());
            return response()->json([
                'status' => false,
                'message' => "Account blocked. Try again in $blockedFor minutes"
            ], 403);
        }

        $otp = rand(100000, 999999);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'otp_attempts' => 0
        ]);

        $this->sendTwilioSms($request->mobile, $otp);

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully to your mobile'
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10',
        ]);

        $user = User::where('mobile', $request->mobile)->first();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        if ($user->updated_at && Carbon::parse($user->updated_at)->diffInSeconds(Carbon::now()) < 60) {
            return response()->json([
                'status' => false,
                'message' => 'Please wait 60 seconds before requesting a new OTP'
            ], 429);
        }

        $otp = rand(100000, 999999);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $this->sendTwilioSms($request->mobile, $otp);

        return response()->json([
            'status' => true,
            'message' => 'New OTP sent successfully'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('mobile', $request->mobile)->first();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        if ($user->otp_attempts >= 3 && $user->otp_locked_until && $user->otp_locked_until > Carbon::now()) {
            return response()->json(['status' => false, 'message' => 'Account blocked temporarily'], 403);
        }

        if ($user->otp != $request->otp) {
            $user->increment('otp_attempts');

            if ($user->otp_attempts >= 3) {
                $user->otp_locked_until = Carbon::now()->addMinutes(15);
                $user->save();
            }

            return response()->json(['status' => false, 'message' => 'Invalid OTP'], 401);
        }

        if ($user->otp_expires_at < Carbon::now()) {
            return response()->json(['status' => false, 'message' => 'OTP expired'], 401);
        }

        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_locked_until' => null
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        DB::table('login_histories')->insert([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
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

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['status' => true, 'message' => 'Logged out successfully']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['status' => true, 'message' => 'Logged out from all devices']);
    }
}