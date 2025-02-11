<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RewardsRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Update as needed to check for authorization
    }

    public function rules()
    {
        return [
            'total_rewards' => 'required|numeric',
            'cashback' => 'required|numeric',
            'referrals' => 'required|numeric',
            'promotions' => 'required|numeric',
        ];
    }
}

