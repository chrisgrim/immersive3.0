import { describe, it, expect } from 'vitest';
import { phoneDashes } from '@/composables/phoneDashes';

describe('phoneDashes', () => {
    it('ten plain digits get the US dashes; one more or one less and they go again', () => {
        expect(phoneDashes('8324')).toBe('8324');
        expect(phoneDashes('8324438072')).toBe('832-443-8072');
        expect(phoneDashes('832-443-80721')).toBe('83244380721');
        expect(phoneDashes('832-443-807')).toBe('832443807');
        // A number from elsewhere is never forced into US shape.
        expect(phoneDashes('912345678')).toBe('912345678');
        expect(phoneDashes('020-7946-0958')).toBe('020-7946-0958');
    });

    it('leaves anything else as typed', () => {
        expect(phoneDashes('+44 20 7946 0958')).toBe('+44 20 7946 0958');
        expect(phoneDashes('(832) 443')).toBe('(832) 443');
        expect(phoneDashes('083244380721')).toBe('083244380721');
        expect(phoneDashes('')).toBe('');
    });

    it('never takes letters, and a + only first', () => {
        expect(phoneDashes('832abc')).toBe('832');
        expect(phoneDashes('call me')).toBe(' ');
        expect(phoneDashes('+44 20+7946')).toBe('+44 207946');
    });
});
