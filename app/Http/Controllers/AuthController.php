<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePasswordRequest;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => 'required|unique:users,phone_number|starts_with:251|digits:12',
            'password' => 'required|string|min:4',
        ]);

        $user = User::create([
            'full_name' => $request->full_name ?? 'Subscriber',
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'username' => $this->generateUniqueUsername($request->full_name ?? 'User'),
            'status' => 1,
        ]);

        return $user;
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where(function ($query) use ($request) {
            if (filter_var($request->username, FILTER_VALIDATE_EMAIL)) {
                $query->where('email', $request->username);
            } elseif (is_numeric($request->username)) {
                $query->where('phone_number', $request->username);
            } else {
                $query->where('username', $request->username);
            }
        })->first();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the user is active
        if (! $user->status) {
            return response()->json(['message' => 'Account is inactive. Please register again.'], 403);
        }

        // Build credentials for validation
        $credentials = ['password' => $request->password];
        if (filter_var($request->username, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $request->username;
        } elseif (is_numeric($request->username)) {
            $credentials['phone_number'] = $request->username;
        } else {
            $credentials['username'] = $request->username;
        }

        if (! Auth::validate($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 400);
        }

        if ($user->verified_at === null) {
            $otp = $this->generateOTP($user);

            app(OTPController::class)->sendSMS(
                $user->phone_number,
                $otp,
                'Login Verification'
            );
            // Test
            return response()->json([
                'message' => 'Account not verified. OTP sent to your phone.',
                'otp' => $otp, // remove in production
            ], 403);
        }

        if (! $token = Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 400);
        }

        activity()
            ->causedBy(Auth::user())
            ->log('logged in')
            ->subject(Auth::user());

        return $this->respondWithToken($token);
    }

    /**
     * Generate OTP, encrypt and store it on the user,
     * set otp_sent_at and replace user's password with hashed OTP.
     *
     * @param \App\Models\User $user
     * @return string Plain OTP (for sending)
     */
    private function generateOTP(User $user): string
    {
        $otp = random_int(100000, 999999);

        $encryptedOtp = Crypt::encryptString((string) $otp);

        $user->update([
            'otp' => $encryptedOtp,
            'otp_sent_at' => now(),
            'password' => Hash::make($otp),
        ]);

        return (string) $otp;
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        return User::find(Auth::id())->load('roles');
    }

    // update_profile
    public function update_profile(Request $request)
    {
        $request->validate([
            'full_name' => 'nullable|string',
            'phone_number' => 'nullable|unique:users,phone_number,' . Auth::id(),
            'email' => 'nullable|email|unique:users,email,' . Auth::id(),
            'username' => 'nullable|string|unique:users,username,' . Auth::id(),
        ]);
        $user = User::find(Auth::id());

        if (! empty($user)) {
            $user->update([
                'full_name' => $request->full_name ?? $user->full_name,
                'email' => $request->email ?? $user->email,
                'phone_number' => $request->phone_number ?? $user->phone_number,
            ]);

            return User::find(Auth::id());
        } else {
            abort(404, 'Invalid Token.');
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {

        Auth::logout();

        activity()
            ->causedBy(Auth::user())
            ->log('logged out');

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
    }

    public function create_password(CreatePasswordRequest $request)
    {
        $user = User::find(Auth::id());

        if ($user->status === 0) {
            abort(400, 'Password already created!');
        }
        if (! empty($user)) {
            $user->update([
                'password' => Hash::make($request->password),
                'status' => 0,
            ]);

            return User::find(Auth::id());
        } else {
            abort(404, 'User not found!');
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string  $token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $user = User::find(Auth::id());

        return response()->json([
            'access_token' => $token,
            'user' => [
                'is_verified' => $user->verified_at !== null,
                'role' => $user->roles->first()->name ?? null,
            ],
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
        ]);
    }

    public function generateUniqueUsername($name)
    {
        $username = Str::slug($name);
        $existingUser = User::where('username', $username)->first();

        if ($existingUser) {
            $username .= rand(1000, 9999);
        }

        while (User::where('username', $username)->exists()) {
            $username = Str::slug($name) . rand(1000, 1000);
        }

        return $username;
    }

    // change profile image
    public function update_profile_image(Request $request)
    {
        $request->validate([
            'profile_image' => 'required|mimes:png,jpeg,jpg,svg,gif,bmp,bmp,tiff,webp',
        ]);

        $user = User::find(Auth::id());

        if (! empty($user)) {

            if ($request->hasFile('profile_image') && $request->file('profile_image')->isValid()) {
                $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
            }

            return User::find(Auth::id());
        } else {
            abort(404, 'Invalid Token.');
        }
    }

    // remove profile image
    public function remove_profile_image()
    {
        $user = User::find(Auth::id());

        if (! empty($user)) {
            $user->clearMediaCollection('profile_image');

            return User::find(Auth::id());
        } else {
            abort(404, 'Invalid Token.');
        }
    }
}
