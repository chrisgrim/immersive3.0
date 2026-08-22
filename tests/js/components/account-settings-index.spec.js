/**
 * Specs for PageComponents/AccountSettings/index.vue
 *
 * This is the URL-driven tab "shell" — it reads the active tab from
 * window.location.pathname (so a hard refresh/cold visit lands correctly),
 * pushes new URLs on navigation, and listens for popstate to keep browser
 * back/forward in sync. Children are shallow-stubbed throughout so these
 * specs stay focused on the shell's own state machine, not each page's
 * internal fetch/render logic (covered by their own specs).
 *
 * Covers:
 *  - explicitTabFromPath()/tabFromPath(): known tab, no tab, unknown tab.
 *  - Desktop navigation: NavSidebar's `navigate` emit pushes the right URL
 *    and swaps the rendered page component.
 *  - Mobile navigation: back button visibility, and the pushState regression
 *    fixed this session — handleNavigation(null) (the Back case) used to
 *    update the visible section but never call pushState, leaving the URL
 *    stale.
 *  - popstate: browser back/forward re-resolves the tab from the URL.
 */
import { shallowMount } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import AccountSettingsIndex from '@/PageComponents/AccountSettings/index.vue';
import NavSidebar from '@/PageComponents/AccountSettings/Pages/navSidebar.vue';
import PersonalInformation from '@/PageComponents/AccountSettings/Pages/PersonalInformation.vue';
import LoginSecurity from '@/PageComponents/AccountSettings/Pages/LoginSecurity.vue';
import Privacy from '@/PageComponents/AccountSettings/Pages/Privacy.vue';
import Notifications from '@/PageComponents/AccountSettings/Pages/Notifications.vue';

/** Controls window.location.pathname for tabFromPath()/explicitTabFromPath(). */
function setPath(pathname) {
    delete window.location;
    window.location = { pathname };
}

describe('AccountSettings/index.vue', () => {
    beforeEach(() => {
        window.Laravel.isMobile = false;
    });

    describe('URL-driven tab resolution', () => {
        it('defaults to personal-info when no tab segment is in the path', () => {
            setPath('/account-settings');
            const wrapper = shallowMount(AccountSettingsIndex);
            expect(wrapper.findComponent(PersonalInformation).exists()).toBe(true);
        });

        it('renders the matching tab component for a known tab segment', () => {
            setPath('/account-settings/privacy');
            const wrapper = shallowMount(AccountSettingsIndex);
            expect(wrapper.findComponent(Privacy).exists()).toBe(true);
            expect(wrapper.findComponent(PersonalInformation).exists()).toBe(false);
        });

        it('falls back to the default tab for an unrecognized tab segment', () => {
            setPath('/account-settings/not-a-real-tab');
            const wrapper = shallowMount(AccountSettingsIndex);
            expect(wrapper.findComponent(PersonalInformation).exists()).toBe(true);
        });

        it('resolves login-security and notifications tabs correctly', () => {
            setPath('/account-settings/login-security');
            expect(shallowMount(AccountSettingsIndex).findComponent(LoginSecurity).exists()).toBe(true);

            setPath('/account-settings/notifications');
            expect(shallowMount(AccountSettingsIndex).findComponent(Notifications).exists()).toBe(true);
        });
    });

    describe('desktop navigation', () => {
        it('pushes the tab URL and switches the rendered component when NavSidebar emits navigate', async () => {
            setPath('/account-settings');
            const pushStateSpy = vi.spyOn(window.history, 'pushState');
            const wrapper = shallowMount(AccountSettingsIndex);

            await wrapper.findComponent(NavSidebar).vm.$emit('navigate', 'privacy');

            expect(pushStateSpy).toHaveBeenCalledWith({ tab: 'privacy' }, '', '/account-settings/privacy');
            expect(wrapper.findComponent(Privacy).exists()).toBe(true);
        });

        it('ignores navigation to an unknown tab slug', async () => {
            setPath('/account-settings');
            const pushStateSpy = vi.spyOn(window.history, 'pushState');
            const wrapper = shallowMount(AccountSettingsIndex);

            await wrapper.findComponent(NavSidebar).vm.$emit('navigate', 'not-a-real-tab');

            expect(pushStateSpy).not.toHaveBeenCalled();
            expect(wrapper.findComponent(PersonalInformation).exists()).toBe(true);
        });
    });

    describe('mobile navigation', () => {
        beforeEach(() => {
            window.Laravel.isMobile = true;
        });

        it('shows the mobile back button once a section is open', () => {
            setPath('/account-settings/privacy');
            expect(shallowMount(AccountSettingsIndex).find('[aria-label="Back"]').exists()).toBe(true);
        });

        it('does not show a back button on the bare list URL', () => {
            setPath('/account-settings');
            expect(shallowMount(AccountSettingsIndex).find('[aria-label="Back"]').exists()).toBe(false);
        });

        // Regression test: the mobile Back button used to update the visible
        // section but never call pushState, so a refresh or share from the
        // "back" state reopened the sub-page it had just left instead of the
        // list actually shown (fixed by handleNavigation's else branch).
        it('pushes the bare /account-settings URL when the back button is clicked', async () => {
            setPath('/account-settings/privacy');
            const pushStateSpy = vi.spyOn(window.history, 'pushState');
            const wrapper = shallowMount(AccountSettingsIndex);

            await wrapper.find('[aria-label="Back"]').trigger('click');

            expect(pushStateSpy).toHaveBeenCalledWith({ tab: null }, '', '/account-settings');
        });

        it('pushes the tab URL when navigating to a real tab on mobile', async () => {
            setPath('/account-settings');
            const pushStateSpy = vi.spyOn(window.history, 'pushState');
            const wrapper = shallowMount(AccountSettingsIndex);

            await wrapper.findComponent(NavSidebar).vm.$emit('navigate', 'notifications');

            expect(pushStateSpy).toHaveBeenCalledWith({ tab: 'notifications' }, '', '/account-settings/notifications');
        });
    });

    describe('popstate handling', () => {
        it('updates the rendered tab when the URL changes via browser back/forward', async () => {
            setPath('/account-settings');
            const wrapper = shallowMount(AccountSettingsIndex);
            expect(wrapper.findComponent(PersonalInformation).exists()).toBe(true);

            setPath('/account-settings/login-security');
            window.dispatchEvent(new Event('popstate'));
            await wrapper.vm.$nextTick();

            expect(wrapper.findComponent(LoginSecurity).exists()).toBe(true);
        });
    });
});
