/**
 * Specs for GlobalComponents/passkey-offer.vue, the "Sign in faster next
 * time?" sheet shown once after an email code or social sign-in.
 */
import { vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('@/composables/usePasskeys', () => ({
    addPasskey: vi.fn(),
    passkeysSupported: vi.fn(() => true),
}));

import * as passkeyApi from '@/composables/usePasskeys';
import PasskeyOffer from '@/GlobalComponents/passkey-offer.vue';

const SNOOZE_KEY = 'ei.passkeyOfferSnoozedUntil';

let mounted = [];

const mountOffer = async () => {
    const wrapper = mount(PasskeyOffer);
    mounted.push(wrapper);
    await flushPromises();
    return wrapper;
};

afterEach(() => {
    mounted.forEach((wrapper) => wrapper.unmount());
    mounted = [];
});

beforeEach(() => {
    localStorage.clear();
    window.Laravel = { user: { id: 1 } };
    vi.clearAllMocks();
    passkeyApi.passkeysSupported.mockReturnValue(true);
});

describe('passkey-offer.vue', () => {
    it('offers to save a passkey', async () => {
        await mountOffer();
        expect(document.body.textContent).toContain('Sign in faster next time');
    });

    it('stays hidden while snoozed, or where passkeys cannot work', async () => {
        localStorage.setItem(SNOOZE_KEY, String(Date.now() + 1000000));
        await mountOffer();
        expect(document.body.textContent).not.toContain('Sign in faster next time');

        localStorage.clear();
        mounted.forEach((wrapper) => wrapper.unmount());
        mounted = [];
        passkeyApi.passkeysSupported.mockReturnValue(false);
        await mountOffer();
        expect(document.body.textContent).not.toContain('Sign in faster next time');
    });

    it('"Not now" hides it for about 90 days', async () => {
        await mountOffer();
        [...document.body.querySelectorAll('button')].find((b) => b.textContent.includes('Not now')).click();
        await flushPromises();

        const until = Number(localStorage.getItem(SNOOZE_KEY));
        expect(until).toBeGreaterThan(Date.now() + 89 * 86400000);
        expect(document.body.textContent).not.toContain('Sign in faster next time');
    });

    it('saves a passkey and says so', async () => {
        passkeyApi.addPasskey.mockResolvedValue({ id: '1', name: 'Chrome on Mac' });
        await mountOffer();

        [...document.body.querySelectorAll('button')].find((b) => b.textContent.includes('Save a passkey')).click();
        await flushPromises();

        expect(passkeyApi.addPasskey).toHaveBeenCalled();
        expect(document.body.textContent).toContain("You're all set");
    });

    it('shows why it could not save', async () => {
        passkeyApi.addPasskey.mockRejectedValue(new Error('This device already has a passkey for your account.'));
        await mountOffer();

        [...document.body.querySelectorAll('button')].find((b) => b.textContent.includes('Save a passkey')).click();
        await flushPromises();

        expect(document.body.textContent).toContain('already has a passkey');
    });
});
