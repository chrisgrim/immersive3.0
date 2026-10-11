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
    { id: 99, name: 'All ages', age: 100 },
];

function makeWrapper(user = { isModerator: false }) {
    return mount(Advisories, {
        global: {
            provide: {
                event: reactive({ advisories: { audience: '' }, contact_levels: [], age_limits: null }),
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

        expect(wrapper.vm.submitData().ageLimit).toEqual(AGES[3]);
    });

    it('takes a typed age on Next without Enter', async () => {
        const wrapper = makeWrapper();
        await flushPromises();
        await openCustom(wrapper);

        await wrapper.find('#age-minimum').setValue('9');
        await wrapper.vm.isValid();

        expect(wrapper.vm.submitData().ageLimit).toEqual(AGES[8]);
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
});
