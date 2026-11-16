<?php

namespace App\Support\Projects;

/**
 * What a venue can say it has (resources/data/venue-amenities.json, read by
 * the page too, so both sides know the same list): tiles with an icon, more
 * to pick from a list ("more"), and the venue's own words in a group. One
 * group is "single": only one of its tiles at a time (the age policy).
 */
class VenueAmenities
{
    private static ?array $data = null;

    /** @return array{groups: array<int, array<string, mixed>>, amenities: array<int, array<string, string>>} */
    public static function data(): array
    {
        return self::$data ??= json_decode(file_get_contents(resource_path('data/venue-amenities.json')), true);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_column(self::data()['amenities'], 'key');
    }

    /** The most of a venue's own words kept in one group. */
    public const MAX_CUSTOM_PER_GROUP = 10;

    /** The longest of a venue's own words. */
    public const MAX_LABEL = 40;

    /**
     * Known keys in the list's own order, at most one from a single group,
     * then the venue's own words ({group, label}, for a group that is not
     * single) in the order given: trimmed, at most MAX_LABEL long, once each
     * (ignoring case), and a word that is a known one's label becomes its key.
     *
     * @param  array<int, mixed>|null  $picked
     * @return array<int, string|array{group: string, label: string}>
     */
    public static function clean(?array $picked): array
    {
        $groups = array_column(self::data()['groups'], null, 'key');
        $byLabel = [];
        foreach (self::data()['amenities'] as $amenity) {
            $byLabel[$amenity['group']][mb_strtolower($amenity['label'])] = $amenity['key'];
        }

        $keys = [];
        $custom = [];
        $seen = [];
        foreach ($picked ?? [] as $item) {
            if (is_string($item)) {
                $keys[$item] = true;

                continue;
            }
            if (! is_array($item) || ! is_string($item['group'] ?? null) || ! is_string($item['label'] ?? null)) {
                continue;
            }
            $group = $item['group'];
            if (! isset($groups[$group]) || ($groups[$group]['single'] ?? false)) {
                continue;
            }
            $label = mb_substr(trim(preg_replace('/\s+/u', ' ', $item['label'])), 0, self::MAX_LABEL);
            $lower = mb_strtolower($label);
            if ($label === '') {
                continue;
            }
            if (isset($byLabel[$group][$lower])) {
                $keys[$byLabel[$group][$lower]] = true;

                continue;
            }
            if (isset($seen[$group][$lower]) || count($seen[$group] ?? []) >= self::MAX_CUSTOM_PER_GROUP) {
                continue;
            }
            $seen[$group][$lower] = true;
            $custom[] = ['group' => $group, 'label' => $label];
        }

        $kept = [];
        $single = [];
        foreach (self::data()['amenities'] as $amenity) {
            if (! isset($keys[$amenity['key']])) {
                continue;
            }
            if ($groups[$amenity['group']]['single'] ?? false) {
                if (isset($single[$amenity['group']])) {
                    continue;
                }
                $single[$amenity['group']] = true;
            }
            $kept[] = $amenity['key'];
        }

        return [...$kept, ...$custom];
    }
}
