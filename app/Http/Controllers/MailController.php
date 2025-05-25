<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function sendTestEmail()
    {
        $details = [
            'title' => 'Test Email from Laravel',
            'body' => 'This is a test email sent using iPage SMTP settings.'
        ];

        Mail::raw($details['body'], function ($message) {
            $message->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                ->to('engr.tassadaq@gmail.com')
                ->subject('Test Email');
        });

        return 'Email sent successfully!';
    }
}
