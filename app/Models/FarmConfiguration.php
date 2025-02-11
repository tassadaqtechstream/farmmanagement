<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FarmConfiguration extends Model
{
    use HasFactory;
    protected $fillable = ['farm_id', 'investment_percentage', 'investment_period', 'min_investment_amount', 'is_active'];

    public function userFarms()
    {
        return $this->belongsTo(Project::class,'farm_id');
    }
}
