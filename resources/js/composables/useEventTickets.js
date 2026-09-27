/**
 * An event's ticket tiers, as the editor and the admin review show them.
 * Tiers are stored once on the event (`event.tickets`).
 */
export function savedTiers(event) {
    return event?.tickets ?? [];
}
