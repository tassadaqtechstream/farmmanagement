<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_rewards',
        'cashback',
        'referrals',
        'promotions'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
