<?php

namespace App\Enums;

enum CreditKind: string
{
    case Regular = 'regular';
    case Alternate = 'alternate';
    case Understudy = 'understudy';
    case Standby = 'standby';
    case Swing = 'swing';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
