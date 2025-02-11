<?php


// app/Enums/IrrigationSource.php

namespace App\Enums;

enum IrrigationSource: string
{
    case WELL = 'well';
    case CANAL = 'canal';
    case RAINFED = 'rainfed';
}

