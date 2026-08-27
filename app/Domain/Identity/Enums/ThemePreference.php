<?php

namespace App\Domain\Identity\Enums;

enum ThemePreference: string
{
    case SYSTEM = 'SYSTEM';
    case LIGHT = 'LIGHT';
    case DARK = 'DARK';
}
