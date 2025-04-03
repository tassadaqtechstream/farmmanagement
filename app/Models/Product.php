<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'category_id',
        'sku',
        'barcode',
        'brand',
        'model',
        'price',
        'cost_price',
        'compare_at_price',
        'b2b_price',
        'stock',
        'stock_status',
        'track_inventory',
        'low_stock_threshold',
        'weight',
        'length',
        'width',
        'height',
        'is_b2b_available',
        'b2b_min_quantity',
        'is_bulk_pricing_eligible',
        'featured_image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_active',
        'is_featured',
        'published_at',
        'notes',
        'attributes',
        'meta_data',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'b2b_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_b2b_available' => 'boolean',
        'is_bulk_pricing_eligible' => 'boolean',
        'track_inventory' => 'boolean',
        'published_at' => 'datetime',
        'attributes' => 'array',
        'meta_data' => 'array',
    ];

    /**
     * Get the primary category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all categories for the product.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * Get all images for the product.
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Get all variants for the product.
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get all attributes for the product.
     */
    public function productAttributes()
    {
        return $this->belongsToMany(ProductAttribute::class, 'product_attribute_product', 'product_id', 'attribute_id')
            ->withPivot('attribute_value_id')
            ->withTimestamps();
    }

    /**
     * Get volume pricing tiers for the product.
     */
    public function volumePricing()
    {
        return $this->hasMany(ProductVolumePricing::class);
    }

    /**
     * Get business-specific pricing for the product.
     */
    public function businessPricing()
    {
        return $this->hasMany(BusinessProductPricing::class);
    }

    /**
     * Get related products.
     */
    public function related()
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'related_id')
            ->withPivot('relation_type')
            ->withTimestamps();
    }

    /**
     * Check if product is in stock.
     */
    public function isInStock()
    {
        return $this->stock > 0 && $this->stock_status === 'in_stock';
    }

    /**
     * Check if product is low on stock.
     */
    public function isLowStock()
    {
        return $this->stock <= $this->low_stock_threshold && $this->stock > 0;
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

    /**
     * Calculate discount percentage between price and compare_at_price.
     */
    public function getDiscountPercentageAttribute()
    {
        if (!$this->compare_at_price || $this->compare_at_price <= $this->price) {
            return 0;
        }

        return round(($this->compare_at_price - $this->price) / $this->compare_at_price * 100);
    }
}
