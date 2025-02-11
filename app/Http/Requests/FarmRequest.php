<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Enum;
use App\Enums\IrrigationSource;
use App\Enums\SoilType;
use App\Enums\SowingMethod;
use App\Enums\SeedVariety;
use App\Enums\Crop;

class FarmRequest extends FormRequest
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
            'location' => 'required|string',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'size' => 'required|string',
            'irrigation_source' => ['required'],
            'soil_type' => ['required'],
            'sowing_method' => ['required'],
            'seed_variety' => ['required'],
            'crop' => ['required'],
            'sowing_date' => 'required|date',
            'name' => 'nullable|string',
            'farm_configuration' => 'required',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }
}
