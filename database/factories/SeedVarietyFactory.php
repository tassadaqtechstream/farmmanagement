<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SeedVariety>
 */
class SeedVarietyFactory extends Factory
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
                'Maize',
                'Wheat',
                'Rice',
                'Sorghum',
                'Barley',
                'Oats',
                'Rye',
                'Millet',
                'Teff',
                'Quinoa',
                'Buckwheat',
                'Amaranth',
                'Triticale',
                'Spelt',
                'Kamut',
                'Emmer',
                'Einkorn',
                'Durum',
                'Farro',
                'Freekeh',
                'Bulgar',
                'Couscous',
                'Polenta',
                'Cornmeal',
                'Cornstarch',
                'Cornflour',
                'Corn Grits',
                'Corn Bran',
                'Corn Germ',
                'Corn Oil',
                'Corn Syrup',
                'Corn Sugar',
                'Corn Gluten',
                'Corn Starch',
                'Corn Flour',
                'Cornmeal',
                'Corn Bran',
                'Corn Grits',
                'Corn Germ',
                'Corn Oil',
                'Corn Syrup',
                'Corn Sugar',
                'Corn Gluten',
                'Corn Starch',
                'Corn Flour',
                'Cornmeal',
                'Corn Bran',
                'Corn Grits',
                'Corn Germ',
                'Corn Oil',
                'Corn Syrup',
                'Corn Sugar',
                'Corn Gluten',
                'Corn Starch',
                'Corn Flour',
                'Cornmeal',
                'Corn Bran',
                'Corn Grits',
                'Corn Germ',
                'Corn Oil',
                'Corn Syrup',
                'Corn Sugar',
                'Corn Gluten',
                'Corn Starch',
                'Corn Flour',
                'Cornmeal',
                'Corn Bran',
                'Corn Grits',
                'Corn Germ',
                'Corn Oil',
                'Corn Syrup',
                'Corn Sugar',
                'Corn Gluten',
                'Corn Starch',
                'Corn Flour',
                'Cornmeal',
                'Corn Bran',
                'Corn Grits',
                'Corn Germ',
                'Corn Oil',
                'Corn Syrup',
                'Corn Sugar',
                'Corn Gluten',
                'Corn Starch',
                'Corn Flour',
                'Cornmeal',
                'Corn Bran',
                'Corn Grits',
                'Corn Germ',
                'Corn Oil',
                'Corn Syrup',
                'Corn Sugar',
            ]),
        ];
    }
}
