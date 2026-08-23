/**
 * Specs for Profile/Pages/navSidebar.vue
 *
 * Covers:
 *  - Renders exactly the 3 own-profile-only tabs: about / events /
 *    search-preferences.
 *  - Highlights whichever item matches the currentTab prop.
 *  - Clicking an item emits 'navigate' with that item's tab key.
 */
import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import ProfileNavSidebar from '@/PageComponents/Profile/Pages/navSidebar.vue';

describe('Profile/Pages/navSidebar.vue', () => {
    it('renders about, events, and search-preferences, in that order', () => {
        const wrapper = mount(ProfileNavSidebar, { props: { currentTab: 'about' } });
        const labels = wrapper.findAll('button').map((b) => b.text());

        expect(labels).toEqual(['About me', 'Liked events', 'Saved searches']);
    });

    it('highlights only the button matching currentTab', () => {
        const wrapper = mount(ProfileNavSidebar, { props: { currentTab: 'events' } });
        const buttons = wrapper.findAll('button');

        expect(buttons[0].classes()).not.toContain('md:bg-neutral-100');
        expect(buttons[1].classes()).toContain('md:bg-neutral-100');
        expect(buttons[2].classes()).not.toContain('md:bg-neutral-100');
    });

    it('emits navigate with the clicked item\'s tab key', async () => {
        const wrapper = mount(ProfileNavSidebar, { props: { currentTab: 'about' } });

        await wrapper.findAll('button')[2].trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([['search-preferences']]);
    });

    it('defaults currentTab to "about" when the prop is omitted', () => {
        const wrapper = mount(ProfileNavSidebar);

        expect(wrapper.findAll('button')[0].classes()).toContain('md:bg-neutral-100');
    });

    it('hides the "about" row on mobile — it just navigates back to the current screen there', () => {
        const wrapper = mount(ProfileNavSidebar, { props: { currentTab: 'events' } });
        const buttons = wrapper.findAll('button');

        expect(buttons[0].classes()).toContain('hidden');
        expect(buttons[1].classes()).not.toContain('hidden');
        expect(buttons[2].classes()).not.toContain('hidden');
    });
});
