<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BaseController extends Controller
{
    public function sendSMSMessageRequest(Request $request)
    {
        $phone_number = $request->phone_number;
        $message = $request->message;

        return $this->sendSMSMessage($phone_number, $message);
    }

    public function sendSMS($phone_number, $otp, $appKey = '')
    {
        $message = $otp.' is your verification code.'.' '.$appKey;

        return $this->sendSMSMessage($phone_number, $message);
    }

    public function sendSMSMessage($phone_number, $message)
    {
        // dd($phone, $message);
        $url = '';
        $token = config('app.sms_token');
        $from = '';
        // $sender = 'SpotlightAcademy';
        $callback = '';

        $body = [
            'from' => $from,
            // "sender" => $sender,
            'to' => $phone_number,
            'message' => $message,
            'callback' => $callback,
        ];

        try {
            $response = Http::withToken($token)
                ->post($url, $body);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['acknowledge'] == 'success') {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Message sent successfully',
                        'data' => $response,
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Message not sent',
                        'data' => $data,
                    ]);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Message not sent',
                    'data' => $response->json(),
                ]);
            }
        } catch (RequestException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Message not sent',
                'data' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Message not sent',
                'data' => $e->getMessage(),
            ]);
        }
    }

    public function smsCallback(Request $request)
    {
        $status = $request->status;
        $messageId = $request->messageId;
        $phone_number = $request->phone_number;
        $message = $request->message;

        if ($status == 'DELIVERED') {
            return response()->json([
                'status' => $status,
                'messageId' => $messageId,
                'phone_number' => $phone_number,
                'message' => $message,
            ]);
        } else {
            return response()->json([
                'status' => $status,
                'messageId' => $messageId,
                'phone_number' => $phone_number,
                'message' => $message,
            ]);
        }
    }
}
