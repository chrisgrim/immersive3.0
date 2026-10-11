/**
 * Specs for Creation/Core/Pages/advisories.vue, the age requirement.
 *
 * Organizers pick from the usual ages as buttons; moderators and admins get
 * "All ages" plus a box to type any youngest age from 1 to 21.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, beforeEach } from 'vitest';
import { reactive } from 'vue';
import Advisories from '@/PageComponents/Creation/Core/Pages/advisories.vue';

const AGES = [
    ...Array.from({ length: 21 }, (_, i) => ({ id: i + 1, name: `${i + 1} +`, age: i + 1 })),
    { id: 99, name: 'All ages', age: 100 },
];

function makeWrapper(user) {
    return mount(Advisories, {
        global: {
            provide: {
                event: reactive({ advisories: { audience: '' }, contact_levels: [], age_limits: null }),
                user,
            },
        },
    });
}

beforeEach(() => {
    window.axios.get.mockImplementation((url) =>
        Promise.resolve({ data: url === '/api/agelimits' ? AGES : [] }));
});

describe('advisories.vue age requirement', () => {
    it('shows organizers the usual ages as buttons, with no typing box', async () => {
        const wrapper = makeWrapper({ isModerator: false });
        await flushPromises();

        expect(wrapper.find('#age-minimum').exists()).toBe(false);
        const text = wrapper.text();
        for (const name of ['6 +', '8 +', '10 +', '12 +', '13 +', '16 +', '18 +', '21 +', 'All ages']) {
            expect(text).toContain(name);
        }
        expect(text).not.toContain('9 +');
    });

    it('lets moderators type any age, such as 9', async () => {
        const wrapper = makeWrapper({ isModerator: true });
        await flushPromises();

        await wrapper.find('#age-minimum').setValue('9');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.vm.submitData().ageLimit).toEqual(AGES[8]);
    });

    it('refuses an age past 21', async () => {
        const wrapper = makeWrapper({ isModerator: true });
        await flushPromises();

        await wrapper.find('#age-minimum').setValue('30');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.text()).toContain('Please type a whole age from 1 to 21');
        expect(wrapper.vm.submitData().ageLimit).toBeNull();
    });
});
