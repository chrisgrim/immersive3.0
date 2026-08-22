import { mount } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';

/**
 * Stub the Login child so this spec stays focused on nav-profile's own
 * conditional rendering and doesn't drag in the (heavy) auth/login component.
 * nav-profile imports it via '../../Auth/login.vue' which resolves to the
 * '@/Auth/login.vue' alias.
 */
vi.mock('@/Auth/login.vue', () => ({
    default: {
        name: 'LoginStub',
        emits: ['close'],
        template: '<div data-test="login-stub" />',
    },
}));

import NavProfile from '@/PageComponents/Nav/nav-profile.vue';

// The component uses a locally-registered `v-click-outside` directive
// (vClickOutside). That resolves fine on its own, but we also pass a no-op
// global directive for any inherited usage to be safe.
const clickOutsideStub = {
    mounted() {},
    beforeMount() {},
    unmounted() {},
};

function makeWrapper(user) {
    return mount(NavProfile, {
        props: { user },
        global: {
            directives: { 'click-outside': clickOutsideStub },
            // Stub Teleport so the modal renders inline (no real body teleport,
            // which trips test-utils' app.onUnmount cleanup path).
            stubs: { teleport: true, Teleport: true },
        },
    });
}

const baseUser = {
    id: 42,
    name: 'Jane',
    hexColor: '#abcdef',
    organizer: null,
};

describe('nav-profile.vue', () => {
    describe('guest (no user)', () => {
        let wrapper;
        beforeEach(() => {
            wrapper = makeWrapper(null);
        });

        it('renders the generic "Submit Your Experience" register link', () => {
            const link = wrapper.find('a[href="/register?create=true"]');
            expect(link.exists()).toBe(true);
            expect(link.text()).toContain('Submit Your Experience');
        });

        it('shows the user icon (no avatar image / gravatar) for a guest', () => {
            expect(wrapper.find('img').exists()).toBe(false);
            expect(wrapper.find('svg use').exists()).toBe(true);
        });

        it('opens a menu with a Login / Sign Up entry when toggled', async () => {
            // toggle dropdown open by clicking the avatar circle
            await wrapper.find('.cursor-pointer').trigger('click');
            const menu = wrapper.find('ul');
            expect(menu.exists()).toBe(true);
            expect(menu.text()).toContain('Login / Sign Up');
            // guest menu has no Logout / Communities entries
            expect(menu.text()).not.toContain('Logout');
            expect(menu.text()).not.toContain('Communities');
        });

        it('uses the default grey avatar color for a guest', () => {
            const avatar = wrapper.find('.cursor-pointer');
            // jsdom normalizes #717171 -> rgb(113, 113, 113)
            expect(avatar.attributes('style')).toContain('rgb(113, 113, 113)');
        });
    });

    describe('logged-in user without organizer', () => {
        let wrapper;
        beforeEach(() => {
            wrapper = makeWrapper({ ...baseUser, organizer: null });
        });

        it('renders the "Submit Your Experience" hosting link', () => {
            const link = wrapper.find('a[href="/hosting/getting-started"]');
            expect(link.exists()).toBe(true);
        });

        it('uses the user hexColor for the avatar background', () => {
            const avatar = wrapper.find('.cursor-pointer');
            // jsdom normalizes #abcdef -> rgb(171, 205, 239)
            expect(avatar.attributes('style')).toContain('rgb(171, 205, 239)');
        });

        it('menu shows Profile linked to the user id and a "List Your Event" option', async () => {
            await wrapper.find('.cursor-pointer').trigger('click');
            const menu = wrapper.find('ul');
            expect(menu.find('a[href="/users/42"]').exists()).toBe(true);
            expect(menu.text()).toContain('List Your Event');
            // not an organizer -> no Organizations link
            expect(menu.find('a[href="/teams"]').exists()).toBe(false);
            expect(menu.text()).toContain('Log out');
        });

        it('does not show the Admin Dashboard link for a non-curator', async () => {
            await wrapper.find('.cursor-pointer').trigger('click');
            expect(wrapper.find('a[href="/admin/dashboard"]').exists()).toBe(false);
        });

        it('does not show an Inbox link when the user has no messages', async () => {
            await wrapper.find('.cursor-pointer').trigger('click');
            expect(wrapper.find('a[href="/inbox"]').exists()).toBe(false);
        });
    });

    describe('logged-in organizer / curator with messages', () => {
        let wrapper;
        beforeEach(() => {
            wrapper = makeWrapper({
                ...baseUser,
                organizer: { name: 'Acme Immersive Co' },
                isCurator: true,
                hasMessages: true,
                unread: true,
            });
        });

        it('renders the organizer name link instead of the submit link', () => {
            const link = wrapper.find('a[href="/hosting/events"]');
            expect(link.exists()).toBe(true);
            expect(link.text()).toContain('Acme Immersive Co');
        });

        it('shows the unread indicator dot on the avatar', () => {
            expect(wrapper.find('.bg-default-red').exists()).toBe(true);
        });

        it('menu shows Inbox, Organizations, and Admin Dashboard', async () => {
            await wrapper.find('.cursor-pointer').trigger('click');
            const menu = wrapper.find('ul');
            expect(menu.find('a[href="/inbox"]').exists()).toBe(true);
            expect(menu.find('a[href="/teams"]').exists()).toBe(true);
            expect(menu.find('a[href="/admin/dashboard"]').exists()).toBe(true);
            // organizer -> NOT "List Your Event"
            expect(menu.find('a[href="/hosting/getting-started"]').exists()).toBe(false);
        });
    });

    describe('avatar variants', () => {
        it('renders a <picture>/<img> when the user has a thumbImagePath', () => {
            const wrapper = makeWrapper({
                ...baseUser,
                thumbImagePath: 'avatars/jane.webp',
            });
            expect(wrapper.find('picture').exists()).toBe(true);
            expect(wrapper.find('picture img').exists()).toBe(true);
        });

        it('falls back to the gravatar image when no thumbImagePath but a gravatar exists', () => {
            const wrapper = makeWrapper({
                ...baseUser,
                thumbImagePath: null,
                gravatar: 'https://gravatar.example/x.png',
            });
            const img = wrapper.find('img');
            expect(img.exists()).toBe(true);
            expect(img.attributes('src')).toBe('https://gravatar.example/x.png');
        });
    });

    describe('dropdown toggle', () => {
        it('toggles the menu open and closed on repeated avatar clicks', async () => {
            const wrapper = makeWrapper(baseUser);
            const avatar = wrapper.find('.cursor-pointer');

            expect(wrapper.find('ul').exists()).toBe(false);
            await avatar.trigger('click');
            expect(wrapper.find('ul').exists()).toBe(true);
            await avatar.trigger('click');
            expect(wrapper.find('ul').exists()).toBe(false);
        });
    });
    describe('logout separation', () => {
        it('puts a divider directly above Logout so it is not flush with the nav items', async () => {
            const wrapper = makeWrapper(baseUser);
            await wrapper.find('.cursor-pointer').trigger('click');

            const items = Array.from(wrapper.find('ul').element.children);
            const logoutIndex = items.findIndex((el) => el.textContent.trim() === 'Log out');
            expect(logoutIndex).toBeGreaterThan(0);

            const divider = items[logoutIndex - 1];
            expect(divider.className).toContain('border-t');
            expect(divider.textContent.trim()).toBe('');
        });

        it('does not add a divider to the single-item guest menu', async () => {
            const wrapper = makeWrapper(null);
            await wrapper.find('.cursor-pointer').trigger('click');

            expect(wrapper.find('ul').element.querySelector('.border-t')).toBeNull();
        });
    });
});
