<?php

namespace App\Models;

use App\Enums\CropStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'cron_types';

    //inverse relation with UserFarm
    public function userFarm(){
        return $this->hasMany(UserFarm::class, 'crop', 'id');
    }
}
