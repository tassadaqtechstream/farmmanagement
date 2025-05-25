<?php

namespace Database\Seeders;

use App\Models\SoilType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SoilTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      SoilType::factory(20)->create();
    }
}
