<?php

namespace App\Enums;

/** A run's optional label. events.run_type is a plain string, not a MySQL ENUM. */
enum RunType: string
{
    case Workshop = 'workshop';
    case WorldPremiere = 'world_premiere';
    case UsPremiere = 'us_premiere';
    case RegionalPremiere = 'regional_premiere';

    /** As the project page prints it. */
    public function label(): string
    {
        return match ($this) {
            self::Workshop => 'Workshop',
            self::WorldPremiere => 'World premiere',
            self::UsPremiere => 'US premiere',
            self::RegionalPremiere => 'Regional premiere',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
