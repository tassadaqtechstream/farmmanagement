<?php
// app/Enums/SoilType.php

namespace App\Enums;

enum SoilType: string
{
    case CLAY = 'clay';
    case LOAM = 'loam';
    case SANDY = 'sandy';
}
