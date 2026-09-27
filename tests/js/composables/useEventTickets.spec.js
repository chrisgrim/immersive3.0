import { describe, expect, it } from 'vitest';
import { savedTiers } from '@/composables/useEventTickets';

const eventSet = [{ name: 'GA', ticket_price: '25.00', currency: 'USD' }];
const showCopy = [{ name: 'Stale', ticket_price: '10.00', currency: 'USD' }];

describe('savedTiers', () => {
    it('returns the event set', () => {
        expect(savedTiers({ tickets: eventSet, shows: [{ tickets: showCopy }] })).toBe(eventSet);
    });

    it('never reads a leftover show copy', () => {
        expect(savedTiers({ tickets: [], shows: [{ tickets: showCopy }] })).toEqual([]);
        expect(savedTiers({ shows: [{ tickets: showCopy }] })).toEqual([]);
    });

    it('returns an empty list when there are no tiers', () => {
        expect(savedTiers({ tickets: [], shows: [] })).toEqual([]);
        expect(savedTiers({})).toEqual([]);
        expect(savedTiers(null)).toEqual([]);
    });
});
