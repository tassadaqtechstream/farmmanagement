<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\User;

class RegisterUserRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Set to true to allow anyone to use this form request
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email') // Check for unique email in users table
            ],
            'phone_number' => [
                'required',
                'string',
                Rule::unique('users', 'phone_number') // Check for unique phone number in users table
            ],
            'user_type' => 'required',
        ];
    }

    /**
     * Customize the error messages for validation.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a valid string.',
            'name.max' => 'Name cannot exceed 255 characters.',

            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',

            'phone_number.required' => 'Phone number is required.',
            'phone_number.string' => 'Phone number must be a valid string.',
            'phone_number.unique' => 'This phone number is already registered.',

            'user_type.required' => 'User type is required.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        // Get the first validation error message
        $errors = $validator->errors()->first();

        // Throw a custom HTTP response exception with the desired format
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'error' => $errors, // Return the first validation error message
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }
}
