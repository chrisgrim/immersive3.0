import { describe, it, expect } from 'vitest';
import { wheelchairLevel } from '@/composables/wheelchairAccess';

describe('wheelchairLevel', () => {
    it('reads the three-way answer when the old yes/no agrees', () => {
        expect(wheelchairLevel({ wheelchairAccess: 'partial', wheelchairReady: false })).toBe('partial');
        expect(wheelchairLevel({ wheelchairAccess: 'full', wheelchairReady: true })).toBe('full');
    });

    it('falls back to the old yes/no for an event saved before the change', () => {
        expect(wheelchairLevel({ wheelchairReady: true })).toBe('full');
        expect(wheelchairLevel({ wheelchairReady: 0 })).toBe('none');
        expect(wheelchairLevel({})).toBeNull();
        expect(wheelchairLevel(null)).toBeNull();
    });

    it('lets the old yes/no win when the two disagree (written by a rollback)', () => {
        expect(wheelchairLevel({ wheelchairAccess: 'partial', wheelchairReady: true })).toBe('full');
        expect(wheelchairLevel({ wheelchairAccess: 'full', wheelchairReady: false })).toBe('none');
    });
});
