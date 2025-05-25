<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
      'user_id',
        'order_number',
        'status',
        'total',
        'shipping_address',
        'billing_address',
        'payment_status',
        'tracking_number',
        'shipping_method',
        'created_at',
        'updated_at',
        'payment_method'
    ];
    protected $casts = [
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    // all relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
    public function documents()
    {
        return $this->hasMany(OrderDocument::class);
    }
    public function business()
    {
        return $this->belongsTo(Business::class);
    }



}
