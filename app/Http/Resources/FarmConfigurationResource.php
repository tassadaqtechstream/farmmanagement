<?php

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;

class FarmConfigurationResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="FarmConfigurationResource",
     *     type="object",
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         example=1
     *     ),
     *     @OA\Property(
     *         property="name",
     *         type="string",
     *         example="John Doe's Farm"
     *     ),
     *     @OA\Property(
     *         property="investment_percentage",
     *         type="number",
     *         format="float",
     *         example=15.5
     *     ),
     *     @OA\Property(
     *         property="investment_period",
     *         type="integer",
     *         example=12
     *     ),
     *     @OA\Property(
     *         property="min_investment_amount",
     *         type="number",
     *         format="float",
     *         example=1000.0
     *     ),
     *     @OA\Property(
     *         property="is_active",
     *         type="boolean",
     *         example=true
     *     )
     * )
     */

    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'name' => $this->userFarms ? $this->userFarms->name : null, // Accessing the name from the related UserFarm
            'investment_percentage' => $this->investment_percentage,
            'investment_period' => $this->investment_period,
            'min_investment_amount' => $this->min_investment_amount,
            'is_active' => $this->is_active
        ];
    }
}
