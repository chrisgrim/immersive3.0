<?php

namespace App\Enums;

/**
 * The credit groups, kept here and nowhere else: credits.department is a plain
 * string, so adding or renaming a group needs no migration of existing rows.
 * A game master is cast.
 */
enum CreditDepartment: string
{
    case Writing = 'writing';
    case Creative = 'creative';
    case Cast = 'cast';
    case Production = 'production';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
