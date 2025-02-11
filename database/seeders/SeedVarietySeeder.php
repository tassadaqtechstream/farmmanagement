<?php

namespace Database\Seeders;

use App\Models\SeedVariety;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeedVarietySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //from the factory
        SeedVariety::factory(20)->create();

    }
}
