<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'image',
        'sort_order',
        'is_active',
        'is_featured',
        'is_b2b_visible',
        'meta_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_b2b_visible' => 'boolean',
        'meta_data' => 'array',
    ];

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get all products directly in this category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all products in this category (including through pivot).
     */
    public function allProducts()
    {
        return $this->belongsToMany(Product::class, 'product_categories');
    }
}
