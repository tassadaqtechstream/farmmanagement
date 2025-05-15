<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $products = [
            [
                'name' => 'Mangoes',
                'sku' => '1000',
                'price' => 1000.00,
                'stock' => 0,
                'category_name' => 'test category 1',
            ],
            [
                'name' => 'New testing',
                'sku' => 'test-34',
                'price' => 90.00,
                'stock' => 50,
                'category_name' => 'Wheat',
            ],
            [
                'name' => 'rhodes grass',
                'sku' => '1100',
                'price' => 1000.00,
                'stock' => 2000,
                'category_name' => 'Grass',
            ],
            [
                'name' => 'Testing',
                'sku' => 'tes-323',
                'price' => 80.00,
                'stock' => 100,
                'category_name' => 'Rice',
            ],
        ];

        foreach ($products as $product) {
            $category = \App\Models\Category::where('name', $product['category_name'])->first();

            DB::table('products')->insert([
                'name' => $product['name'],
                'slug' => Str::slug($product['name']),
                'short_description' => 'Short description for ' . $product['name'],
                'description' => 'Full description for ' . $product['name'],
                'category_id' => $category ? $category->id : null,
                'sku' => $product['sku'],
                'barcode' => null,
                'brand' => null,
                'model' => null,

                'price' => $product['price'],
                'cost_price' => null,
                'compare_at_price' => null,
                'b2b_price' => null,

                'stock' => $product['stock'],
                'stock_status' => $product['stock'] == 0 ? 'out_of_stock' : 'in_stock',
                'track_inventory' => true,
                'low_stock_threshold' => 5,

                'weight' => null,
                'length' => null,
                'width' => null,
                'height' => null,

                'is_b2b_available' => false,
                'b2b_min_quantity' => 1,
                'is_bulk_pricing_eligible' => false,

                'featured_image' => null,
                'meta_title' => $product['name'],
                'meta_description' => 'Meta description for ' . $product['name'],
                'meta_keywords' => 'product, ecommerce',

                'is_active' => true,
                'is_featured' => false,
                'published_at' => $now,

                'notes' => null,
                'attributes' => json_encode([]),
                'meta_data' => json_encode([]),

                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
