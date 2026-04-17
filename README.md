# PHP_Laravel12_Login_With_OTP

A **Laravel 12 REST API project** that implements **User Registration and Login using Mobile Number & OTP (SMS)**.  

This project demonstrates a **real-world OTP-based authentication system** using **Twilio SMS Gateway** with proper security practices.


## Project Overview

This project focuses on a commonly used authentication flow:

1. User registers using **name, mobile number, and password**

2. User logs in using **mobile number and password**

3. System generates a **6-digit OTP**

4. OTP is sent to the **user’s mobile number via SMS**

5. User verifies OTP

6. OTP is cleared after successful verification (security best practice)


## Tech Stack

- PHP 8.2+

- Laravel 12

- MySQL

- Twilio SMS Gateway

- Postman (API Testing)


---


## Project Structure

```
PHP_Laravel12_Login_With_OTP/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── API/
│   │           └── AuthController.php
│   └── Models/
│       └── User.php
│
├── database/
│   └── migrations/
│       └── xxxx_xx_xx_create_users_table.php
│
├── routes/
│   └── api.php
│
├── .env
└── README.md
```

---



## STEP 1: Create Laravel 12 Project

Create the Laravel 12 project using:

```bash
composer create-project laravel/laravel PHP_Laravel12_Login_With_OTP "12.*"
```

Move into the project directory:

```bash
cd PHP_Laravel12_Login_With_OTP
```

Start the development server:

```bash
php artisan serve
```


---


## STEP 2: Database Configuration (.env)

Update database credentials in .env:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=otp_login_api
DB_USERNAME=root
DB_PASSWORD=
```

Create Database After Using This Command: 

```bash
php artisan serve
```


---



## STEP 3: Modify Default Users Migration (IMPORTANT)


Laravel already provides a default users table migration.

For this project, we replace the default fields with OTP-related fields.


```bash
php artisan make:migration create_users_table
```

File: database/migrations/xxxx_xx_xx_create_users_table.php


Migration Code


```php
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
```

Run migration:


```bash
php artisan migrate
```


---



## STEP 4: Update Default User Model 


```bash
php artisan make:model User
```

**Note**

Laravel already creates the User model by default when you create a new project.

This command is shown for understanding and learning purposes.

app/Models/User.php


```php
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
```    


---



## STEP 5: Install Twilio SDK

```bash
composer require twilio/sdk
```


---


## STEP 6: Create Twilio Account & Configure SMS (.env)

Twilio is used to send OTP to the user’s mobile number via SMS.

---

#### STEP 6.1: Create Twilio Account

Go to **https://www.twilio.com/**

Click Sign Up

Register using:

- Email


- Google account (recommended for interns)


- Verify your email address


- Verify your personal mobile number (Twilio sends OTP)


- Twilio account is now created (Trial account)



#### STEP 6.2: Get Twilio Credentials (SID & Auth Token)

Login to Twilio Console

Go to Dashboard

Copy:

- Account SID


- Auth Token

These are required to authenticate your Laravel application.



#### STEP 6.3: Get SMS-Enabled Twilio Number (IMPORTANT)

In Twilio Console, go to:

- Phone Numbers → Manage → Buy a Number


Select a number that supports:

- SMS

- Buy the number (Twilio provides 1 free number on trial)

- This number will be used as the sender of OTP SMS.



#### STEP 6.4: Verify Mobile Number (Trial Account Requirement)

On Twilio Trial Account:

- SMS can be sent ONLY to verified numbers


To verify a number:

Go to:

- Phone Numbers → Verified Caller IDs


- Click Add a new Caller ID


- Enter the mobile number you want to receive OTP


- Verify using OTP sent by Twilio


- Now OTP SMS can be sent to this number



#### STEP 6.5: Configure Twilio in .env


Add the following to your .env file:

TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxx

TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxx

TWILIO_NUMBER=+1XXXXXXXXXX


**Important Notes**

TWILIO_NUMBER must be an SMS-enabled Twilio number


Trial accounts:

- Can send SMS only to verified mobile numbers


- Add “Sent from Twilio trial account” text automatically


- WhatsApp numbers cannot send normal SMS


- To send SMS to any user number → Twilio account must be upgraded


---



## STEP 7: Create API Controller


```bash
php artisan make:controller API/AuthController
```

Path created:

app/Http/Controllers/API/AuthController.php

Explaination: This controller handles user registration and OTP-based login.

After validating user credentials, it generates and sends an OTP via SMS using Twilio and verifies it with an expiry check.

For security, the OTP is cleared from the database after successful verification.


```php
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
```


---



## STEP 8: Define API Routes

File: routes/api.php


```php
<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

// User registration route (no OTP required)
Route::post('/register', [AuthController::class, 'register']);

// Login route – validates credentials and sends OTP to mobile number
Route::post('/login', [AuthController::class, 'login']);

// OTP verification route – verifies OTP and completes login
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
```


---



## STEP-BY-STEP POSTMAN TESTING


#### 1) Register User 

Endpoint: POST /register

URL:

```bash
http://127.0.0.1:8000/api/register
```

Headers:

Accept: application/json

Content-Type: application/json

Body (raw JSON):

{

  "name": "Test User",

  "mobile": "1234567890",

  "password": "123456"

}

Success Response:

{

 "status": true,

 "message": "Registration successful"

}

User saved in DB


---


#### 2) Login & Receive OTP

Endpoint: POST /login

URL:

```bash
http://127.0.0.1:8000/api/login
```

Body:

{

  "mobile": "1234567890",

  "password": "123456"

}

Success Response:

{

 "status": true,

 "message": "OTP sent to your mobile number"

}

---

#### 3) Verify OTP

Endpoint: POST /verify-otp

URL:

```bash
http://127.0.0.1:8000/api/verify-otp
```

Body:

{

  "mobile": "1234567890",

  "otp": "123456"

}

Success Response:

{

 "status": true,

 "message": "Login successful"

}

OTP cleared from database after successful verification

---


**Additional Notes**

OTP is 6-digit random number, expires in 5 minutes.

Twilio trial account restriction: Can only send SMS to verified numbers.

Use your Twilio SMS-enabled number for sending OTP.


---


## Output

**Register User**

<img width="1384" height="998" alt="Screenshot 2025-12-22 212512" src="https://github.com/user-attachments/assets/ae90cc90-03ec-4844-9f5e-7fbac8c6c67e" />


**Login User**

<img width="1390" height="980" alt="Screenshot 2025-12-22 214232" src="https://github.com/user-attachments/assets/06fea6ca-7d68-42e4-9770-6ac51e5ed36c" />


**OTP Via SMS**

![twilio_sms](https://github.com/user-attachments/assets/02a05ffd-a0c5-43f9-a80e-4aca6195d837)


**Verify OTP**

<img width="1383" height="1001" alt="Screenshot 2025-12-22 214318" src="https://github.com/user-attachments/assets/c7b90ea3-2ee7-46b0-b6c8-99d3522fe0f8" />


---


Your PHP_Laravel12_Login_With_OTP Project is Now Ready!

<<<<<<< HEAD

=======
>>>>>>> development
