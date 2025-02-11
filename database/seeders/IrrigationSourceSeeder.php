<?php

namespace Database\Seeders;

use App\Models\IrrigationSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IrrigationSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      // run factory
        IrrigationSource::factory(20)->create();
    }
}
