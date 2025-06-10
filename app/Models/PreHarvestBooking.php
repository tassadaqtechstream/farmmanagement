<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreHarvestBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'buyer_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'quantity',
        'total_price',
        'special_requests',
        'status',
        'deposit_amount',
        'deposit_paid',
        'payment_method',
        'transaction_id',
        'confirmed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'total_price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'deposit_paid' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function listing()
    {
        return $this->belongsTo(PreHarvestListing::class, 'listing_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    // Process payment through wallet
    public function processWalletPayment()
    {
        $buyerWallet = $this->buyer->wallet;

        if (!$buyerWallet || $buyerWallet->cash_balance < $this->deposit_amount) {
            throw new \Exception('Insufficient wallet balance');
        }

        // Deduct from buyer's wallet
        $buyerWallet->decrement('cash_balance', $this->deposit_amount);

        // Add to seller's wallet
        $sellerWallet = $this->listing->user->wallet;
        if ($sellerWallet) {
            $sellerWallet->increment('cash_balance', $this->deposit_amount);
        }

        $this->update([
            'deposit_paid' => true,
            'transaction_id' => 'TXN_' . time() . '_' . $this->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return true;
    }
}
