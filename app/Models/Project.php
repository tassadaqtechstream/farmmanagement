<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * @OA\Schema(
 *     schema="Project",
 *     type="object",
 *     title="Project",
 *     description="Project model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Project ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Project name",
 *         example="Project 1"
 *     ),
 *     @OA\Property(
 *         property="location",
 *         type="string",
 *         description="Project location",
 *         example="Lagos"
 *     ),
 *     @OA\Property(
 *         property="size",
 *         type="string",
 *         description="Project size",
 *         example="1000"
 *     ),
 *     @OA\Property(
 *         property="funding",
 *         type="string",
 *         description="Project funding",
 *         example="1000000"
 *     ),
 *     @OA\Property(
 *         property="annual_return",
 *         type="string",
 *         description="Project annual return",
 *         example="100000"
 *     ),
 *     @OA\Property(
 *         property="gross_yield",
 *         type="string",
 *         description="Project gross yield",
 *         example="100000"
 *     ),
 *     @OA\Property(
 *         property="net_yield",
 *         type="string",
 *         description="Project net yield",
 *         example="100000"
 *     )
 * )
 */
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'size',
        'funding',
        'annual_return',
        'gross_yield',
        'net_yield',
        'amount',
        'image'
    ];

    public function getImageAttribute($value)
    {
        return asset('images/' . $value);
    }
    public function investments()
    {
        return $this->hasMany(Investment::class, 'farm_id');
    }
}
