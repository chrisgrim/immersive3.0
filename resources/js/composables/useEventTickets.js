/**
 * An event's ticket tiers, as the editor and the admin review show them.
 *
 * Tiers are stored once on the event (`event.tickets`). They also still sit
 * as a copy on every show, so an event whose own set is empty falls back to
 * its first loaded show's copy.
 */
export function savedTiers(event) {
    if (event?.tickets?.length) {
        return event.tickets;
    }

    return event?.shows?.[0]?.tickets ?? [];
}
