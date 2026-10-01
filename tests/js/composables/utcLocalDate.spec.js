import { describe, it, expect } from 'vitest';
import moment from 'moment-timezone';
import { utcDateTimeToLocalDate, isKnownTimezone } from '@/composables/utcLocalDate.js';

/**
 * utcLocalDate.js replaced moment-timezone for one job on the public event
 * page. This pins it to moment's answer (the implementation it replaced) for
 * every hour across DST changes in a spread of zones, and for every input
 * shape the server sends.
 */
const viaMoment = (dateTime, timezone) => (dateTime instanceof Date
    ? moment.utc(dateTime)
    : moment.utc(dateTime, 'YYYY-MM-DD HH:mm:ss')).tz(timezone).format('YYYY-MM-DD');

const ZONES = [
    'UTC', 'America/Los_Angeles', 'America/New_York', 'America/Denver', 'America/Phoenix',
    'America/Chicago', 'America/Anchorage', 'Pacific/Honolulu', 'America/Sao_Paulo',
    'America/St_Johns', 'Europe/London', 'Europe/Berlin', 'Europe/Moscow', 'Asia/Kolkata',
    'Asia/Kathmandu', 'Asia/Shanghai', 'Asia/Tokyo', 'Australia/Sydney', 'Australia/Adelaide',
    'Australia/Lord_Howe', 'Pacific/Auckland', 'Pacific/Chatham', 'Pacific/Kiritimati',
    'Pacific/Pago_Pago', 'US/Eastern', 'US/Pacific',
];

const pad = (n) => String(n).padStart(2, '0');
const stored = (d) => `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:00`;

describe('utcDateTimeToLocalDate matches moment-timezone', () => {
    it.each(ZONES)('every hour of 2025 and 2026 in %s', (zone) => {
        const start = Date.UTC(2025, 0, 1);
        const mismatches = [];
        for (let t = start; t < Date.UTC(2027, 0, 1); t += 3600 * 1000) {
            const value = stored(new Date(t));
            const expected = viaMoment(value, zone);
            if (utcDateTimeToLocalDate(value, zone) !== expected) mismatches.push(value);
        }
        expect(mismatches).toEqual([]);
    });

    it('every input shape the server sends, on both sides of midnight', () => {
        for (const zone of ZONES) {
            for (const value of [
                '2026-10-03 03:00:00', '2026-10-03 00:00:00', '2026-10-03 23:59:59',
                '2026-10-03T03:00:00.000000Z', '2026-10-03T03:00:00Z', '2026-10-03',
                '2026-03-08 10:30:00', '2026-11-01 08:59:00', '1990-06-15 12:00:00', '2099-12-31 23:00:00',
                new Date('2026-10-03T03:00:00Z'), new Date('2026-03-29T01:00:00Z'),
            ]) {
                expect(utcDateTimeToLocalDate(value, zone), `${zone} ${value}`).toBe(viaMoment(value, zone));
            }
        }
    });

    it('knows the same zone names moment does for every zone moment knows', () => {
        const unknown = moment.tz.names().filter((zone) => !isKnownTimezone(zone));
        expect(unknown).toEqual([]);
    });
});
