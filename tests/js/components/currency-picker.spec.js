import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import CurrencyPicker from '@/GlobalComponents/currency-picker.vue';
import { CURRENCY_CODES } from '@/composables/useCurrency';

/**
 * The ticket step's searchable currency picker. It lists every current ISO
 * 4217 currency from the browser's own data, with a "Suggested" group on
 * top, and can be driven entirely from the keyboard.
 */
const globalOpts = {
    directives: {
        'click-outside': { beforeMount() {}, mounted() {}, unmounted() {} },
    },
};

let wrapper;

const make = (props = {}) => {
    wrapper = mount(CurrencyPicker, {
        props: { modelValue: 'USD', suggested: ['AUD', 'USD', 'GBP'], ...props },
        global: globalOpts,
    });

    return wrapper;
};

const codesIn = (w) => w.findAll('[data-code]').map((el) => el.attributes('data-code'));
const search = async (w, text) => {
    const input = w.find('input');
    await input.setValue(text);

    return input;
};

afterEach(() => wrapper?.unmount());

describe('currency-picker', () => {
    it('lists the suggested currencies first, then every currency by name', () => {
        const w = make();
        const codes = codesIn(w);

        expect(codes.slice(0, 3)).toEqual(['AUD', 'USD', 'GBP']);
        expect(codes.length).toBe(3 + CURRENCY_CODES.length);
        expect(w.text()).toContain('Suggested');
        expect(w.text()).toContain('All currencies');

        // Alphabetical by NAME: "Afghan Afghani" comes before "Albanian Lek".
        const all = codes.slice(3);
        expect(all.indexOf('AFN')).toBeLessThan(all.indexOf('ALL'));
        expect(w.text()).toContain('Singapore Dollar');
    });

    it('drops invalid suggestions rather than rendering them', () => {
        const w = make({ suggested: ['SGD', '$', null, 'SGD'] });

        expect(codesIn(w).slice(0, 1)).toEqual(['SGD']);
        expect(codesIn(w)[1]).not.toBe('SGD');
    });

    it('filters by name, code or symbol, and hides the suggestions while searching', async () => {
        const w = make();

        await search(w, 'sing');
        expect(codesIn(w)).toEqual(['SGD']);
        expect(w.text()).not.toContain('Suggested');

        await search(w, 'inr');
        expect(codesIn(w)).toEqual(['INR']);

        await search(w, 'taiwan');
        expect(codesIn(w)).toEqual(['TWD']);

        await search(w, '₹');
        expect(codesIn(w)).toContain('INR');
    });

    it('says so when nothing matches', async () => {
        const w = make();
        await search(w, 'narnia');

        expect(codesIn(w)).toEqual([]);
        expect(w.text()).toContain('No currency matches');
    });

    it('emits the chosen code and asks to close on click', async () => {
        const w = make();

        await w.find('[data-code="GBP"]').trigger('click');

        expect(w.emitted('update:modelValue')).toEqual([['GBP']]);
        expect(w.emitted('close')).toHaveLength(1);
    });

    it('picks the first match on Enter, and walks with the arrow keys', async () => {
        const w = make();
        const input = await search(w, 'dollar');

        // "Australian Dollar" is the first dollar alphabetically.
        expect(codesIn(w)[0]).toBe('AUD');
        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'ArrowUp' });
        await input.trigger('keydown', { key: 'Enter' });

        expect(w.emitted('update:modelValue')).toEqual([[codesIn(w)[1]]]);
    });

    it('walks past both copies of a suggested currency', async () => {
        // Afghan Afghani is first alphabetically, so suggesting it puts it at
        // row 0 (Suggested) and row 1 (All currencies). Tracking the highlight
        // by code got stuck bouncing between those two.
        const w = make({ modelValue: 'AFN', suggested: ['AFN'] });
        const input = w.find('input');

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'Enter' });

        expect(w.emitted('update:modelValue')).toEqual([['ALL']]);
    });

    it('closes on Escape without choosing', async () => {
        const w = make();

        await w.find('input').trigger('keydown', { key: 'Escape' });

        expect(w.emitted('close')).toHaveLength(1);
        expect(w.emitted('update:modelValue')).toBeUndefined();
    });

    it('is a bottom sheet with a title and a close cross on a phone', async () => {
        // The site's standard mobile format (the hosting page's Filter Events
        // sheet): teleported to body, dimmed backdrop, title, close cross.
        const original = window.matchMedia;
        window.matchMedia = (q) => ({ matches: true, media: q, addEventListener() {}, removeEventListener() {} });

        try {
            const w = make();
            const dialog = document.body.querySelector('[role="dialog"][aria-label="Choose a currency"]');

            expect(dialog).not.toBeNull();
            expect(dialog.textContent).toContain('Currency');
            expect(dialog.parentElement.className).toContain('fixed inset-0');
            expect(dialog.className).toContain('rounded-t-2xl');

            dialog.querySelector('button[aria-label="Close"]').click();
            await w.vm.$nextTick();
            expect(w.emitted('close')).toHaveLength(1);
        } finally {
            window.matchMedia = original;
        }
    });

    it('marks the current currency', () => {
        const w = make({ modelValue: 'GBP', suggested: [] });

        expect(w.find('[data-code="GBP"]').attributes('aria-selected')).toBe('true');
        expect(w.find('[data-code="USD"]').attributes('aria-selected')).toBe('false');
    });
});
