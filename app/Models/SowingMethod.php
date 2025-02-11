<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SowingMethod extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    //inverse relation with UserFarm
    public function userFarm(){
        return $this->hasMany(UserFarm::class, 'sowing_method', 'id');
    }
}
