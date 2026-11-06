<?php

namespace App\Support;

/**
 * What a public listing response may say of a location. A secret location's
 * street never reaches a visitor, as on the public page (EventController::show).
 * Teams editing a listing read it through their own routes, not this.
 */
class PublicLocation
{
    private const SECRET_FIELDS = ['home', 'street', 'postal_code'];

    /**
     * @param  array<string, mixed>|null  $event  a listing as it is about to go out;
     *                                            null for a search hit the table has lost
     */
    public static function redact(?array $event): ?array
    {
        if (! empty($event['location']['hiddenLocationToggle'])) {
            // A linked venue's name is its address; free text is the organizer's own wording.
            $linked = ! empty($event['location']['venue_id']) ? ['venue'] : [];

            $event['location'] = array_diff_key($event['location'], array_flip([...self::SECRET_FIELDS, 'venue_id', ...$linked]));
        }

        return $event;
    }
}
