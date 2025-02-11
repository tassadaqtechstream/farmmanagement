<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\SowingMethod;
use App\Enums\Crop;
use App\Enums\SeedVariety;
use App\Enums\SoilType;
use App\Enums\IrrigationSource;

class UserFarm extends Model
{
    use HasFactory;

    // Error mass assignement user_id
    protected $guarded = ['id'];
    protected $casts = [
        'sowing_method' => SowingMethod::class,
        'crop' => Crop::class,
        'seed_variety' => SeedVariety::class,
        'soil_type' => SoilType::class,
        'irrigation_type' => IrrigationSource::class,

    ];

    // create all relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }


    public function farmConfiguration()
    {
        return $this->hasMany(FarmConfiguration::class);
    }

    public function activeConfiguration()
    {
        return $this->hasOne(FarmConfiguration::class, 'farm_id')->where('is_active', true);
    }

}
