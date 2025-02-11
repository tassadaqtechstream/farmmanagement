<?php
// app/Enums/SeedVariety.php

namespace App\Enums;

enum SeedVariety: string
{
    case HYBRID = 'hybrid';
    case ORGANIC = 'organic';
    case GMO = 'GMO';
}
