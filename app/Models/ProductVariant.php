<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'b2b_price',
        'stock',
        'stock_status',
        'weight',
        'length',
        'width',
        'height',
        'image',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'b2b_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the product that owns the variant.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the attribute values for the variant.
     */
    public function attributeValues()
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_variant_attribute_values', 'variant_id', 'attribute_value_id')
            ->withPivot('attribute_id')
            ->withTimestamps();
    }

    /**
     * Get volume pricing tiers for the variant.
     */
    public function volumePricing()
    {
        return $this->hasMany(ProductVolumePricing::class, 'variant_id');
    }

    /**
     * Check if variant is in stock.
     */
    public function isInStock()
    {
        return $this->stock > 0 && $this->stock_status === 'in_stock';
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get formatted B2B price.
     */
    public function getFormattedB2bPriceAttribute()
    {
        return $this->b2b_price ? '$' . number_format($this->b2b_price, 2) : null;
    }
}
