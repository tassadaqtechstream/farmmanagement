<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $categories = [
            ['name' => 'Grains', 'description' => 'grains'],
            ['name' => 'Nuts', 'description' => 'Nuts'],
            ['name' => 'Green Cofee', 'description' => 'Green Cofee'],
            ['name' => 'Rice', 'description' => 'Rice'],
            ['name' => 'Corn', 'description' => 'Corn'],
            ['name' => 'Wheat', 'description' => 'Wheat'],
            ['name' => 'Barley', 'description' => 'Barley'],
            ['name' => 'Grass', 'description' => 'Grass'],
            ['name' => 'test category 1', 'description' => 'test category 1'],
        ];

        foreach ($categories as $index => $cat) {
            DB::table('categories')->insert([
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'description' => $cat['description'],
                'parent_id' => null,
                'image' => null,
                'sort_order' => $index + 1,
                'is_active' => 1,
                'is_featured' => 0,
                'is_b2b_visible' => 1,
                'meta_data' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

