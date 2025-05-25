<?php

namespace App\Http\Requests;

use App\Models\UserFarm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="InvestmentRequest",
 *     type="object",
 *     required={"user_id", "farm_id", "amount"},
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         description="ID of the user making the investment"
 *     ),
 *     @OA\Property(
 *         property="farm_id",
 *         type="integer",
 *         description="ID of the farm being invested in"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Amount of the investment"
 *     )
 * )
 */

class InvestmentRequest extends FormRequest
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

    /**
     * @OA\Schema(
     *     schema="ValidationError",
     *     type="object",
     *     @OA\Property(
     *         property="message",
     *         type="string",
     *         description="Error message"
     *     ),
     *     @OA\Property(
     *         property="errors",
     *         type="object",
     *         description="Validation errors",
     *         @OA\AdditionalProperties(
     *             type="array",
     *             @OA\Items(type="string")
     *         )
     *     )
     * )
     */

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'farm_id' => 'required|exists:user_farms,id',
            'amount' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $farm = UserFarm::find($this->farm_id);
                    if ($farm) {
                        $activeConfig = $farm->activeConfiguration;
                        if ($activeConfig && $value < $activeConfig->min_investment_amount) {
                            $fail("The investment amount must be greater than the configured minimum investment amount of {$activeConfig->min_investment_amount}.");
                        }
                    }
                },
            ]
        ];
    }
}
