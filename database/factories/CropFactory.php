<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Crop>
 */
class CropFactory extends Factory
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
                'Rice',
                'Wheat',
                'Maize',
                'Barley',
                'Sorghum',
                'Millet',
                'Oats',
                'Rye',
                'Triticale',
                'Buckwheat',
                'Quinoa',
                'Amaranth',
                'Teff',
                'Spelt',
                'Kamut',
                'Emmer',
                'Einkorn',
                'Durum',
                'Farro',
                'Freekeh',
                'Bulgur',
                'Couscous',
                'Polenta',
                'Cornmeal',
                'Semolina',
                'Bran',
                'Germ',
                'Endosperm',
                'Whole Grain',
                'Refined Grain',
                'Enriched Grain',
                'Fortified Grain',
                'Sprouted Grain',
                'Gluten-Free Grain',
                'Ancient Grain',
                'Heritage Grain',
                'Heirloom Grain',
                'Hybrid Grain',
                'Organic Grain',
                'Non-GMO Grain',
                'Conventional Grain',
                'Whole Wheat',
                'Whole Rye',
                'Whole Oats',
                'Whole Barley',
                'Whole Corn',
                'Whole Rice',
                'Whole Millet',
                'Whole Sorghum',
                'Whole Teff',
                'Whole Quinoa',
                'Whole Amaranth',
                'Whole Buckwheat',
                'Whole Spelt',
                'Whole Kamut',
                'Whole Einkorn',
                'Whole Emmer',
                'Whole Durum',
                'Whole Farro',
                'Whole Freekeh',
                'Whole Bulgur',
                'Whole Couscous',
                'Whole Polenta',
                'Whole Cornmeal',
                'Whole Semolina',
                'Whole Bran',
                'Whole Germ',
                'Whole Endosperm',
                'Whole Grain',
                'Whole Refined Grain',
                'Whole Enriched Grain',
                'Whole Fortified Grain',
                'Whole Sprouted Grain',
                'Whole Gluten-Free Grain',
                'Whole Ancient Grain',
                'Whole Heritage Grain',
                'Whole Heirloom Grain',
                'Whole Hybrid Grain',
                'Whole Organic Grain',
                'Whole Non-GMO Grain',
                'Whole Conventional Grain',
                'Whole Wheat Grain',
                'Whole Rye Grain',
                'Whole Oats Grain',
            ])
        ];
    }
}
