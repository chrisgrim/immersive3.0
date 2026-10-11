/**
 * Specs for Creation/Core/Pages/advisories.vue, the age requirement.
 *
 * Everyone picks from the usual ages as buttons, or Custom to type any
 * youngest age from 1 to 21.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, beforeEach } from 'vitest';
import { reactive } from 'vue';
import Advisories from '@/PageComponents/Creation/Core/Pages/advisories.vue';

const AGES = [
    ...Array.from({ length: 21 }, (_, i) => ({ id: i + 1, name: `${i + 1} +`, age: i + 1 })),
    // Listed first, the way the API would sort a row stored with age 0.
];
AGES.unshift({ id: 99, name: 'All ages', age: 0 });
const age = (n) => AGES.find((row) => row.name === `${n} +`);

function makeWrapper(user = { isModerator: false }, saved = null) {
    return mount(Advisories, {
        global: {
            provide: {
                event: reactive({ advisories: { audience: '' }, contact_levels: [], age_limits: saved }),
                user,
            },
        },
    });
}

async function openCustom(wrapper) {
    await wrapper.findAll('p').find((p) => p.text() === 'Custom').trigger('click');
    await flushPromises();
}

function backButton(wrapper) {
    return wrapper.findAll('button').find((b) => b.text() === 'Use standard age requirements');
}

beforeEach(() => {
    window.axios.get.mockImplementation((url) =>
        Promise.resolve({ data: url === '/api/agelimits' ? AGES : [] }));
});

describe('advisories.vue age requirement', () => {
    it.each([false, true])('shows the usual ages and Custom to everyone (moderator: %s)', async (isModerator) => {
        const wrapper = makeWrapper({ isModerator });
        await flushPromises();

        expect(wrapper.find('#age-minimum').exists()).toBe(false);
        const text = wrapper.text();
        for (const name of ['Age Requirement', '10 +', '13 +', '16 +', '18 +', '21 +', 'All ages', 'Custom']) {
            expect(text).toContain(name);
        }
        expect(text).not.toContain('12 +');
    });

    it('Custom shows only a blank box, "and up" and the way back', async () => {
        const wrapper = makeWrapper();
        await flushPromises();
        await openCustom(wrapper);

        const input = wrapper.find('#age-minimum');
        expect(input.element.value).toBe('');
        expect(input.attributes('placeholder')).toBeUndefined();
        const text = wrapper.text();
        expect(text).toContain('Custom Age Requirement');
        expect(text).toContain('and up');
        expect(text).not.toContain('All ages');
        expect(text).not.toContain('Set');
        expect(backButton(wrapper).exists()).toBe(true);
    });

    it('takes a typed age on Enter, such as 4', async () => {
        const wrapper = makeWrapper();
        await flushPromises();
        await openCustom(wrapper);

        await wrapper.find('#age-minimum').setValue('4');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.vm.submitData().ageLimit).toEqual(age(4));
    });

    it('takes a typed age on Next without Enter', async () => {
        const wrapper = makeWrapper();
        await flushPromises();
        await openCustom(wrapper);

        await wrapper.find('#age-minimum').setValue('9');
        await wrapper.vm.isValid();

        expect(wrapper.vm.submitData().ageLimit).toEqual(age(9));
    });

    it('refuses an age past 21', async () => {
        const wrapper = makeWrapper();
        await flushPromises();
        await openCustom(wrapper);

        await wrapper.find('#age-minimum').setValue('30');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.text()).toContain('Please type a whole age from 1 to 21');
        expect(wrapper.vm.submitData().ageLimit).toBeNull();
    });

    it('forgets a typed age and its error on "Use standard age requirements"', async () => {
        const wrapper = makeWrapper();
        await flushPromises();
        await openCustom(wrapper);

        await wrapper.find('#age-minimum').setValue('30');
        await wrapper.find('form').trigger('submit');
        await backButton(wrapper).trigger('click');
        await wrapper.vm.isValid();

        expect(wrapper.vm.submitData().ageLimit).toBeNull();
        expect(wrapper.text()).not.toContain('Please type a whole age');
        expect(wrapper.find('#age-minimum').exists()).toBe(false);
    });

    it('does not save a valid typed age after going back', async () => {
        const wrapper = makeWrapper();
        await flushPromises();
        await openCustom(wrapper);

        await wrapper.find('#age-minimum').setValue('9');
        await backButton(wrapper).trigger('click');
        await wrapper.vm.isValid();

        expect(wrapper.vm.submitData().ageLimit).toBeNull();
    });

    it('lists the buttons in order, All ages after 21 +', async () => {
        const wrapper = makeWrapper();
        await flushPromises();

        const labels = wrapper.findAll('.grid p').map((p) => p.text());
        expect(labels).toEqual(['10 +', '13 +', '16 +', '18 +', '21 +', 'All ages', 'Custom']);
    });

    it('keeps a saved custom age, and removing it shows the buttons', async () => {
        const wrapper = makeWrapper({ isModerator: false }, age(9));
        await flushPromises();

        expect(wrapper.vm.submitData().ageLimit).toEqual(age(9));
        expect(wrapper.text()).toContain('9 +');
        expect(wrapper.text()).not.toContain('Custom');

        await wrapper.find('.cursor-pointer.bg-white').trigger('click');

        expect(wrapper.vm.submitData().ageLimit).toBeNull();
        expect(wrapper.findAll('.grid p').map((p) => p.text())).toContain('Custom');
        expect(wrapper.find('#age-minimum').exists()).toBe(false);
    });
});
