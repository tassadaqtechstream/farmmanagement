<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     title="Transaction",
 *     required={"wallet_id", "amount", "type", "description"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="wallet_id", type="integer", format="int64"),
 *     @OA\Property(property="amount", type="number", format="float"),
 *     @OA\Property(property="type", type="string", enum={"credit", "debit"}),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['wallet_id', 'amount', 'type', 'description'];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
