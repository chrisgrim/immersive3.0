<?php

namespace App\Support;

/**
 * What a public listing response may say of a location. A secret location's
 * street never reaches a visitor, as on the public page (EventController::show).
 * Teams editing a listing read it through their own routes, not this.
 */
class PublicLocation
{
    private const SECRET_FIELDS = ['home', 'street', 'postal_code', 'latitude', 'longitude'];

    /**
     * @param  array<string, mixed>|null  $event  a listing as it is about to go out;
     *                                            null for a search hit the table has lost
     */
    public static function redact(?array $event): ?array
    {
        if (! empty($event['location']['hiddenLocationToggle'])) {
            $event['location'] = array_diff_key($event['location'], array_flip(self::SECRET_FIELDS));
        }

        return $event;
    }
}
