<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeedVariety extends Model
{
    use HasFactory;
    protected $table = 'seed_varities';
    protected $guarded = ['id'];

}
