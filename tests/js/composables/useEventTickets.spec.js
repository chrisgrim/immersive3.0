import { describe, expect, it } from 'vitest';
import { savedTiers } from '@/composables/useEventTickets';

const eventSet = [{ name: 'GA', ticket_price: '25.00', currency: 'USD' }];
const showCopy = [{ name: 'Stale', ticket_price: '10.00', currency: 'USD' }];

describe('savedTiers', () => {
    it('prefers the event set over a show copy', () => {
        expect(savedTiers({ tickets: eventSet, shows: [{ tickets: showCopy }] })).toBe(eventSet);
    });

    it('falls back to the first show copy when the event set is empty', () => {
        expect(savedTiers({ tickets: [], shows: [{ tickets: showCopy }] })).toBe(showCopy);
    });

    it('falls back to the first show copy when the event set is missing', () => {
        expect(savedTiers({ shows: [{ tickets: showCopy }] })).toBe(showCopy);
    });

    it('returns an empty list when there are no tiers anywhere', () => {
        expect(savedTiers({ tickets: [], shows: [] })).toEqual([]);
        expect(savedTiers({})).toEqual([]);
        expect(savedTiers(null)).toEqual([]);
        // dates.vue replaces shows with bare {date} rows after the schedule assistant runs.
        expect(savedTiers({ shows: [{ date: '2026-10-01' }] })).toEqual([]);
    });
});
