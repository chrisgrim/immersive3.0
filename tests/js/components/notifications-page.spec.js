/**
 * Specs for PageComponents/Notifications/index.vue — the in-app notification
 * FEED page (distinct from AccountSettings/Pages/Notifications.vue, the
 * mail-preferences page, covered separately).
 *
 * Covers:
 *  - Fetch-on-mount populates the list and clears loading.
 *  - Empty state and load-error state (with retry).
 *  - markRead: optimistic update, POSTs the right URL, reverts on failure.
 *  - markAllRead: optimistic update, POSTs read-all, reverts on failure.
 *  - "Mark all as read" button only shows when something is unread.
 *  - Load more: appends the next page and stops offering once
 *    next_page_url is null.
 *  - The header's settings link points at the mail-preferences page.
 *  - The saved_search_new_events type's link/message (its own branch, not
 *    the event_slug/event_name fields the other two types use — this is
 *    the exact regression Codex caught in review: it used to always link
 *    to `/events/undefined` and show the generic fallback message).
 */
import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, beforeEach } from 'vitest';
import NotificationsPage from '@/PageComponents/Notifications/index.vue';

function notification(overrides = {}) {
    return {
        id: 1,
        read_at: null,
        created_at: new Date().toISOString(),
        data: { type: 'saved_event_new_dates', event_name: 'Test Event', event_slug: 'test-event' },
        ...overrides,
    };
}

beforeEach(() => {
    window.axios.get.mockResolvedValue({
        data: { notifications: { data: [], next_page_url: null } },
    });
});

async function mountLoaded(notifications = [], nextPageUrl = null) {
    window.axios.get.mockResolvedValue({
        data: { notifications: { data: notifications, next_page_url: nextPageUrl } },
    });
    const wrapper = mount(NotificationsPage);
    await flushPromises();
    return wrapper;
}

describe('Notifications/index.vue', () => {
    it('fetches on mount and renders the list', async () => {
        const wrapper = await mountLoaded([notification({ id: 1 }), notification({ id: 2 })]);

        expect(window.axios.get).toHaveBeenCalledWith('/api/notifications');
        expect(wrapper.findAll('.divide-y a').length).toBe(2);
        expect(wrapper.text()).not.toContain('Loading');
    });

    it('links to the notification-settings page', async () => {
        const wrapper = await mountLoaded([]);

        const settingsLink = wrapper.find('a[href="/account-settings/notifications"]');
        expect(settingsLink.exists()).toBe(true);
    });

    it('shows the empty state when there are no notifications', async () => {
        const wrapper = await mountLoaded([]);

        expect(wrapper.text()).toContain('No notifications yet');
    });

    it('shows an error state with a retry button when the fetch fails', async () => {
        window.axios.get.mockRejectedValue(new Error('network error'));
        const wrapper = mount(NotificationsPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Could not load your notifications');

        window.axios.get.mockResolvedValue({
            data: { notifications: { data: [notification()], next_page_url: null } },
        });
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Test Event');
    });

    it('shows "Mark all as read" only when something is unread', async () => {
        const allRead = await mountLoaded([notification({ read_at: new Date().toISOString() })]);
        expect(allRead.text()).not.toContain('Mark all as read');

        const hasUnread = await mountLoaded([notification({ read_at: null })]);
        expect(hasUnread.text()).toContain('Mark all as read');
    });

    it('marks a single notification read optimistically and posts the right URL', async () => {
        const wrapper = await mountLoaded([notification({ id: 42, read_at: null })]);
        window.axios.post.mockResolvedValue({ data: {} });

        await wrapper.find('.divide-y a').trigger('click');

        expect(window.axios.post).toHaveBeenCalledWith('/api/notifications/42/read');
        // The unread dot should be gone now that read_at is optimistically set.
        expect(wrapper.find('.bg-blue-600').exists()).toBe(false);
    });

    it('reverts a single mark-read on failure', async () => {
        const wrapper = await mountLoaded([notification({ id: 42, read_at: null })]);
        window.axios.post.mockRejectedValue(new Error('failed'));

        await wrapper.find('.divide-y a').trigger('click');
        await flushPromises();

        expect(wrapper.find('.bg-blue-600').exists()).toBe(true);
    });

    it('does not re-post for an already-read notification', async () => {
        const wrapper = await mountLoaded([notification({ id: 42, read_at: new Date().toISOString() })]);

        await wrapper.find('.divide-y a').trigger('click');

        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('marks all read optimistically and reverts only the previously-unread ones on failure', async () => {
        const wrapper = await mountLoaded([
            notification({ id: 1, read_at: null }),
            notification({ id: 2, read_at: new Date().toISOString() }),
        ]);
        window.axios.post.mockRejectedValue(new Error('failed'));

        await wrapper.find('button').trigger('click'); // "Mark all as read"
        await flushPromises();

        expect(window.axios.post).toHaveBeenCalledWith('/api/notifications/read-all');
        // Both should be back to their original unread/read state after revert.
        expect(wrapper.find('.bg-blue-600').exists()).toBe(true);
    });

    it('links a saved_search_new_events notification to its search url, not /events/undefined', async () => {
        const wrapper = await mountLoaded([notification({
            id: 1,
            data: {
                type: 'saved_search_new_events',
                saved_search_name: 'Comedy in LA',
                event_count: 3,
                url: '/index/search?city=Los+Angeles%2C+CA',
            },
        })]);

        const link = wrapper.find('.divide-y a');
        expect(link.attributes('href')).toBe('/index/search?city=Los+Angeles%2C+CA');
        expect(link.attributes('href')).not.toContain('undefined');
    });

    it('shows the match-count message for a saved_search_new_events notification, singular and plural', async () => {
        const singular = await mountLoaded([notification({
            id: 1,
            data: { type: 'saved_search_new_events', saved_search_name: 'Comedy in LA', event_count: 1, url: '/index/search' },
        })]);
        expect(singular.text()).toContain('1 new event matches "Comedy in LA"');

        const plural = await mountLoaded([notification({
            id: 1,
            data: { type: 'saved_search_new_events', saved_search_name: 'Comedy in LA', event_count: 3, url: '/index/search' },
        })]);
        expect(plural.text()).toContain('3 new events match "Comedy in LA"');
    });

    it('loads more, appends results, and hides the button once next_page_url is null', async () => {
        // read_at set so "Mark all as read" doesn't also render — otherwise
        // it'd be the first <button> in the DOM instead of "Load more".
        const wrapper = await mountLoaded([notification({ id: 1, read_at: new Date().toISOString() })], '/api/notifications?page=2');
        expect(wrapper.text()).toContain('Load more');

        window.axios.get.mockResolvedValue({
            data: { notifications: { data: [notification({ id: 2 })], next_page_url: null } },
        });
        const loadMoreButton = wrapper.findAll('button').find((b) => b.text().includes('Load more'));
        await loadMoreButton.trigger('click');
        await flushPromises();

        expect(window.axios.get).toHaveBeenCalledWith('/api/notifications?page=2');
        expect(wrapper.findAll('.divide-y a').length).toBe(2);
        expect(wrapper.text()).not.toContain('Load more');
    });
});
