/**
 * Specs for PageComponents/Profile/index.vue
 *
 * Same URL-driven "shell" pattern as AccountSettings/index.vue, plus an
 * isOwner gate: 'events'/'search-preferences' are own-profile-only tabs, but
 * ProfilesController::show() renders the same shell for ANY viewer, so the
 * gate has to be enforced client-side. Children are shallow-stubbed
 * throughout to keep these specs focused on the shell's own state machine.
 *
 * Covers:
 *  - isOwner tab gate (regression: a non-owner used to see the VIEWER's own
 *    Liked Events/Saved Searches data, populated from /api/hub/* which is
 *    scoped to the authenticated user, not the profile being viewed — with
 *    no page chrome identifying whose data it even was).
 *  - Sidebar (NavSidebar) only renders for the owner.
 *  - URL sync: NavSidebar's `navigate` emit pushes the right URL, and
 *    popstate re-resolves the tab (still respecting the isOwner gate).
 */
import { shallowMount } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import ProfileIndex from '@/PageComponents/Profile/index.vue';
import NavSidebar from '@/PageComponents/Profile/Pages/navSidebar.vue';
import AboutMe from '@/PageComponents/Profile/Pages/AboutMe.vue';
import Events from '@/PageComponents/Profile/Pages/Events.vue';
import SavedSearches from '@/PageComponents/Profile/Pages/SavedSearches.vue';

/** Controls window.location.pathname for explicitTabFromPath()/resolveTab(). */
function setPath(pathname) {
    delete window.location;
    window.location = { pathname };
}

const baseUser = { id: 42, name: 'Jane' };

describe('Profile/index.vue', () => {
    beforeEach(() => {
        // currentTab === 'events'/'search-preferences' triggers an immediate
        // fetchEvents()/fetchSearches() watcher — give both a safe response
        // shape so those code paths never crash a test that reaches them.
        window.axios.get.mockImplementation((url) => {
            if (url === '/api/hub/events') {
                return Promise.resolve({ data: { events: { data: [], total: 0, per_page: 24, current_page: 1, last_page: 1 } } });
            }
            if (url === '/api/hub/saved-searches') {
                return Promise.resolve({ data: { searches: [] } });
            }
            return Promise.resolve({ data: {} });
        });
    });

    describe("isOwner tab gate (regression: a non-owner used to see the viewer's own data)", () => {
        it('falls back to About Me for a non-owner even when the URL requests the events tab', () => {
            setPath('/users/42/events');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: false } });

            expect(wrapper.findComponent(AboutMe).exists()).toBe(true);
            expect(wrapper.findComponent(Events).exists()).toBe(false);
        });

        it('falls back to About Me for a non-owner even when the URL requests the search-preferences tab', () => {
            setPath('/users/42/search-preferences');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: false } });

            expect(wrapper.findComponent(AboutMe).exists()).toBe(true);
            expect(wrapper.findComponent(SavedSearches).exists()).toBe(false);
        });

        it('honors the URL tab for the actual owner', () => {
            setPath('/users/42/events');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: true } });

            expect(wrapper.findComponent(Events).exists()).toBe(true);
            expect(wrapper.findComponent(AboutMe).exists()).toBe(false);
        });

        it('defaults to About Me for the owner on the bare profile URL', () => {
            setPath('/users/42');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: true } });

            expect(wrapper.findComponent(AboutMe).exists()).toBe(true);
        });
    });

    describe('sidebar isOwner gating', () => {
        it('does not render the sidebar/nav for a non-owner', () => {
            setPath('/users/42');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: false } });

            expect(wrapper.findComponent(NavSidebar).exists()).toBe(false);
        });

        it('renders the sidebar/nav for the owner', () => {
            setPath('/users/42');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: true } });

            expect(wrapper.findComponent(NavSidebar).exists()).toBe(true);
        });
    });

    describe('URL sync', () => {
        it('pushes the tab URL when NavSidebar emits navigate to a real tab', async () => {
            setPath('/users/42');
            const pushStateSpy = vi.spyOn(window.history, 'pushState');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: true } });

            await wrapper.findComponent(NavSidebar).vm.$emit('navigate', 'events');

            expect(pushStateSpy).toHaveBeenCalledWith({ tab: 'events' }, '', '/users/42/events');
        });

        it('pushes the bare profile URL when the Back button is clicked from a tab', async () => {
            // NavSidebar itself only renders when currentTab === 'about' (see
            // the template's v-if) — on any other tab the sidebar slot shows
            // the Back button + list instead, so that's the real path to
            // handleNavigation('about') here, via handleBack().
            setPath('/users/42/events');
            const pushStateSpy = vi.spyOn(window.history, 'pushState');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: true } });

            await wrapper.find('[aria-label="Back"]').trigger('click');

            expect(pushStateSpy).toHaveBeenCalledWith({ tab: 'about' }, '', '/users/42');
        });

        it('updates the rendered tab when the URL changes via browser back/forward', async () => {
            setPath('/users/42');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: true } });
            expect(wrapper.findComponent(AboutMe).exists()).toBe(true);

            setPath('/users/42/events');
            window.dispatchEvent(new Event('popstate'));
            await wrapper.vm.$nextTick();

            expect(wrapper.findComponent(Events).exists()).toBe(true);
        });

        it('popstate still respects the isOwner gate for a non-owner', async () => {
            setPath('/users/42');
            const wrapper = shallowMount(ProfileIndex, { props: { user: baseUser, isOwner: false } });

            setPath('/users/42/events');
            window.dispatchEvent(new Event('popstate'));
            await wrapper.vm.$nextTick();

            expect(wrapper.findComponent(AboutMe).exists()).toBe(true);
            expect(wrapper.findComponent(Events).exists()).toBe(false);
        });
    });
});
