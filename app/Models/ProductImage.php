<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'alt_text',
        'sort_order',
    ];

    /**
     * Get the product that owns the image.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the values for this attribute.
     */
    public function values()
    {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id');
    }

    /**
     * Get the products that use this attribute.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attribute_product', 'attribute_id', 'product_id')
            ->withPivot('attribute_value_id')
            ->withTimestamps();
    }
}
