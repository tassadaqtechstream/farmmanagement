<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FarmConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'farm_id' => 'required|exists:user_farms,id',
            'investment_percentage' => 'required|numeric|min:0',
            'investment_period' => 'required|integer|min:0',
            'min_investment_amount' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ];
    }
}
