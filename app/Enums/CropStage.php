<?php

namespace App\Enums;

enum CropStage: string
{
    case SaplingStage = 'sapling_stage';
    case VegetativeGrowth = 'vegetative_growth';
    case FloweringStage = 'flowering_stage';
    case FruitSetting = 'fruit_setting';
    case FruitDevelopment = 'fruit_development';
    case Harvesting = 'harvesting';
    case PostHarvestRipening = 'post_harvest_ripening';

    public function label(): string
    {
        return match ($this) {
            self::SaplingStage => 'Sapling Stage',
            self::VegetativeGrowth => 'Vegetative Growth',
            self::FloweringStage => 'Flowering Stage',
            self::FruitSetting => 'Fruit Setting',
            self::FruitDevelopment => 'Fruit Development',
            self::Harvesting => 'Harvesting',
            self::PostHarvestRipening => 'Post-Harvest Ripening',
        };
    }
}
