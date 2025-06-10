<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreHarvestListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_farm_id',
        'cron_type_id',
        'seed_variety_id',
        'title',
        'location',
        'estimated_yield',
        'price_per_kg',
        'harvest_date',
        'quality_grade',
        'minimum_order',
        'organic_certified',
        'description',
        'terms_conditions',
        'status',
        'reserved_quantity',
        'images',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'harvest_date' => 'date',
        'organic_certified' => 'boolean',
        'images' => 'array',
        'estimated_yield' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userFarm()
    {
        return $this->belongsTo(UserFarm::class, 'user_farm_id');
    }

    public function cronType()
    {
        return $this->belongsTo(Crop::class, 'cron_type_id');
    }

    public function seedVariety()
    {
        return $this->belongsTo(SeedVariety::class, 'seed_variety_id');
    }

    public function bookings()
    {
        return $this->hasMany(PreHarvestBooking::class, 'listing_id');
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->estimated_yield - $this->reserved_quantity;
    }

    public function getDaysToHarvestAttribute()
    {
        return now()->diffInDays($this->harvest_date, false);
    }

    public function getHarvestProgressAttribute()
    {
        $totalDays = now()->startOfDay()->diffInDays($this->harvest_date);
        $remainingDays = max(0, $this->days_to_harvest);

        if ($totalDays <= 0) return 100;

        return max(0, min(100, round((($totalDays - $remainingDays) / $totalDays) * 100)));
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')
            ->where('harvest_date', '>', now());
    }

    public function scopeByCropType($query, $cropTypeId)
    {
        return $query->where('cron_type_id', $cropTypeId);
    }

    public function scopeByLocation($query, $location)
    {
        return $query->where('location', 'like', "%{$location}%");
    }

    public function scopeByFarm($query, $farmId)
    {
        return $query->where('user_farm_id', $farmId);
    }
}
