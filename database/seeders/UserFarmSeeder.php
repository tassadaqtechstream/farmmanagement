<?php

namespace Database\Seeders;

use App\Models\UserFarm;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserFarmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //insert 20 records with dynamic name

        for ($i = 1; $i <= 20; $i++) {
            UserFarm::create([
                'user_id' => 1,
                'name' => "Farm $i",
                'location' => "Location $i",
                'size' => 100,
                'status' => 'active',
            ]);
        }
       
    }
}
