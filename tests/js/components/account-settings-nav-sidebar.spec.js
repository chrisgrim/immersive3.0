/**
 * Specs for AccountSettings/Pages/navSidebar.vue
 *
 * Covers:
 *  - Renders exactly the 4 tabs: personal-info / login-security / privacy /
 *    notifications, plus a 5th "API keys" tab for moderators/admins or once
 *    MCP_PUBLIC is on (window.Laravel.mcpPublic).
 *  - Highlights whichever item matches the currentTab prop.
 *  - Clicking an item emits 'navigate' with that item's tab key.
 *
 * Distinct from Profile/Pages/navSidebar.vue (3 items, different set) —
 * covered separately in profile-nav-sidebar.spec.js.
 */
import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import AccountSettingsNavSidebar from '@/PageComponents/AccountSettings/Pages/navSidebar.vue';

describe('AccountSettings/Pages/navSidebar.vue', () => {
    it('renders personal information, login & security, privacy, and notifications, in that order', () => {
        const wrapper = mount(AccountSettingsNavSidebar, { props: { currentTab: 'personal-info' } });
        const labels = wrapper.findAll('button').map((b) => b.text());

        expect(labels).toEqual(['Personal information', 'Login & security', 'Privacy', 'Notifications']);
    });

    it('hides the API keys tab for a regular user with the public flag off', () => {
        window.Laravel.user.isModerator = false;
        window.Laravel.mcpPublic = false;
        const wrapper = mount(AccountSettingsNavSidebar, { props: { currentTab: 'personal-info' } });

        expect(wrapper.findAll('button').map((b) => b.text())).not.toContain('API keys');
    });

    it('shows the API keys tab for a moderator', () => {
        window.Laravel.user.isModerator = true;
        const wrapper = mount(AccountSettingsNavSidebar, { props: { currentTab: 'personal-info' } });
        const labels = wrapper.findAll('button').map((b) => b.text());

        expect(labels).toEqual(['Personal information', 'Login & security', 'Privacy', 'Notifications', 'API keys']);
    });

    it('shows the API keys tab for a regular user once MCP_PUBLIC is on', () => {
        window.Laravel.user.isModerator = false;
        window.Laravel.mcpPublic = true;
        const wrapper = mount(AccountSettingsNavSidebar, { props: { currentTab: 'personal-info' } });

        expect(wrapper.findAll('button').map((b) => b.text())).toContain('API keys');
    });

    it('emits navigate with "api-keys" when a moderator clicks the API keys tab', async () => {
        window.Laravel.user.isModerator = true;
        const wrapper = mount(AccountSettingsNavSidebar, { props: { currentTab: 'personal-info' } });

        await wrapper.findAll('button')[4].trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([['api-keys']]);
    });

    it('highlights only the button matching currentTab', () => {
        const wrapper = mount(AccountSettingsNavSidebar, { props: { currentTab: 'privacy' } });
        const buttons = wrapper.findAll('button');

        expect(buttons[0].classes()).not.toContain('bg-neutral-100');
        expect(buttons[1].classes()).not.toContain('bg-neutral-100');
        expect(buttons[2].classes()).toContain('bg-neutral-100');
        expect(buttons[3].classes()).not.toContain('bg-neutral-100');
    });

    it('emits navigate with the clicked item\'s tab key', async () => {
        const wrapper = mount(AccountSettingsNavSidebar, { props: { currentTab: 'personal-info' } });

        await wrapper.findAll('button')[3].trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([['notifications']]);
    });

    it('highlights nothing when currentTab does not match any item (default null)', () => {
        const wrapper = mount(AccountSettingsNavSidebar);

        wrapper.findAll('button').forEach((button) => {
            expect(button.classes()).not.toContain('bg-neutral-100');
        });
    });
});
