<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));
    }

    public function sendSms($to, $message)
    {
        $from = env('TWILIO_FROM');
        return $this->client->messages->create($to, [
            'from' => $from,
            'body' => $message
        ]);
    }
}
