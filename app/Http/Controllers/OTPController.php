<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class OTPController extends BaseController
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    public function verify_otp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|exists:users,phone_number|starts_with:251|digits:12',
            'otp' => 'required',
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();

        if (! $user) {
            abort(404, 'User not found!');
        }

        if ($user->verified_at != null) {
            abort(400, 'User already verified.');
        }

        //dd($user->otp);
        $decryptedOtp = Crypt::decryptString($user->otp);

        if ($request['otp'] == $decryptedOtp) {

            $to = Carbon::parse($user->otp_sent_at);

            if ($to->diffInMinutes(Carbon::now()) > 15) {
                abort(400, 'The code you entered has expired. Please resend code to verify your number.');
            }

            $user->update([
                'verified_at' => Carbon::now(),
            ]);

            $token = Auth::guard('api')->login($user);

            return response()
                ->json([
                    'message' => 'User verified successfully',
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ], 201);
        } else {
            abort(400, 'Your otp is not correct!');
        }
    }

    public function resend_otp(Request $request)
    {
        $otp = random_int(100000, 999999);
        $user = User::where('phone_number', $request->phone_number)->first();

        if ($user->verified_at !== null) {
            abort(400, 'User already verified!');
        }

        if (! empty($user)) {

            if ($request->has('email')) {
                $this->emailService->sendEmail(
                    $request->email,
                    'Password Reset OTP',
                    6478497,
                    [
                        'otp' => $otp,
                        'name' => $user->name ?? 'User',
                    ]
                );
            }

            $this->sendSMS($request->phone, $otp, $request->appKey);
            $encryptedOtp = Crypt::encryptString($otp);
            $user->update([
                'password' => Hash::make($otp),
                'otp' => $encryptedOtp,
                'verified_at' => null,
                'otp_sent_at' => now(),
            ]);

            return response()->json([
                'message' => 'OTP sent successfully!'. $otp,
            ]);
        } else {
            abort(404, 'User not found!');
        }
    }
}
