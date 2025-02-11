<?php

namespace App\Enums;


enum SowingMethod: string
{
    case MANUAL = 'manual';
    case MECHANIZED = 'mechanized';
    case HYDROPONIC = 'hydroponic';
}
