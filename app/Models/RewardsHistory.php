<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardsHistory extends Model
{
    use HasFactory;

   protected $table = 'rewards_history';
    protected $fillable = [
        'user_id',
        'total_rewards',
        'cashback',
        'referrals',
        'promotions',
        'type'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
