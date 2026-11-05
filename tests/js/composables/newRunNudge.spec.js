import { wantsNewRun } from '@/composables/newRunNudge';

const published = { status: 'p', closingDate: '2026-03-01 23:59:00' };

describe('wantsNewRun', () => {
    it('asks when the city changes, not when it is only respelled in case', () => {
        expect(wantsNewRun(published, 'Location', { location: { city: 'Boston' } }, 'Chicago')).toBe(true);
        expect(wantsNewRun(published, 'Location', { location: { city: 'chicago' } }, 'Chicago')).toBe(false);
    });

    it('asks for a date more than 90 days after the last one, and only then', () => {
        expect(wantsNewRun(published, 'Dates', { dateArray: ['2026-05-30'] })).toBe(false);
        expect(wantsNewRun(published, 'Dates', { dateArray: ['2026-02-01', '2026-06-01'] })).toBe(true);
    });

    it('stays quiet on other steps, and on anything not published', () => {
        expect(wantsNewRun(published, 'Name', { location: { city: 'Boston' } }, 'Chicago')).toBe(false);
        expect(wantsNewRun({ ...published, status: 'd' }, 'Location', { location: { city: 'Boston' } }, 'Chicago')).toBe(false);
    });
});
