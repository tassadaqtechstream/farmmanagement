<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IrrigationSource>
 */
class IrrigationSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'River',
                'Lake',
                'Dam',
                'Borehole',
                'Well',
                'Rainwater',
                'Pond',
                'Reservoir',
                'Springs',
                'Canal',
                'Drip Irrigation',
                'Flood Irrigation',
                'Furrow Irrigation',
                'Sprinkler Irrigation',
                'Subsurface Irrigation',
                'Surface Irrigation',
                'Center Pivot Irrigation',
                'Lateral Move Irrigation',
                'Manual Irrigation',
                'Mechanized Irrigation',
            ]),
        ];
    }
}
