<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    protected $twilio;

    public function __construct(TwilioService $twilio)
    {
        $this->twilio = $twilio;
    }

    public function sendSms(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string'
        ]);

        $to = $request->input('to');
        $to = $this->processPhoneNumber($to);
        $message = 'your registration code is: ' . $request->input('message');

        $this->twilio->sendSms($to, $message);

        return response()->json(['message' => 'SMS sent successfully']);
    }

    public function sendOtp($to, $otp)
    {
        $to = $this->processPhoneNumber($to);
        if(!$to){
            Log::info('Invalid phone number');
            return response()->json(['message' => 'Invalid phone number']);
        }
        $message = "Your registration code is: $otp";
        $this->twilio->sendSms($to, $message);
        return response()->json(['message' => 'OTP sent successfully']);
    }

    protected function processPhoneNumber($phoneNumber)
    {
        if (substr($phoneNumber, 0, 2) === '03') {
            return '+92' . substr($phoneNumber, 1);
        }
        return '';
    }
}
