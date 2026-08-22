/**
 * Specs for PageComponents/Profile/Components/hub-event-detail.vue
 *
 * Covers:
 *  - The "Get updates" preference-toggle's checked state matches
 *    event.notifyUpdates with NO inversion (this component is the same
 *    class as the one that shipped an inverted binding elsewhere this
 *    session — verified directly, not assumed).
 *  - Toggling PATCHes the correct URL/payload, optimistic update, and
 *    reverts + re-emits on a rejected request.
 *  - organizer-profile-card / notify block only render when event.organizer
 *    is present.
 *  - share(): clipboard fallback path when navigator.share is unavailable.
 */
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import HubEventDetail from '@/PageComponents/Profile/Components/hub-event-detail.vue';

function makeEvent(overrides = {}) {
    return {
        slug: 'test-event',
        name: 'Test Event',
        images: [],
        largeImagePath: null,
        remaining: { type: 'available', label: '3 shows left' },
        dateRangeLabel: null,
        organizer: { name: 'Acme Events', thumbImagePath: null, slug: 'acme' },
        notifyUpdates: false,
        ...overrides,
    };
}

function mountDetail(props = {}) {
    return mount(HubEventDetail, {
        props: { event: makeEvent(), ...props },
        global: {
            stubs: {
                // Owned/tested by a different spec file — stub it out so this
                // file only exercises hub-event-detail's own logic.
                OrganizerProfileCard: true,
            },
        },
    });
}

describe('hub-event-detail.vue', () => {
    describe('notify toggle', () => {
        it('reflects notifyUpdates: true as checked with no inversion', () => {
            const wrapper = mountDetail({ event: makeEvent({ notifyUpdates: true }) });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(true);
        });

        it('reflects notifyUpdates: false as unchecked with no inversion', () => {
            const wrapper = mountDetail({ event: makeEvent({ notifyUpdates: false }) });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
        });

        it('optimistically flips on change and PATCHes the correct url/payload', async () => {
            window.axios.patch.mockResolvedValueOnce({ data: {} });
            const wrapper = mountDetail({ event: makeEvent({ slug: 'my-event', notifyUpdates: false }) });

            await wrapper.find('input[type="checkbox"]').trigger('change');

            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(true);
            expect(window.axios.patch).toHaveBeenCalledWith('/api/hub/events/my-event/notify-updates', { enabled: true });
            expect(wrapper.emitted('notify-updated')[0]).toEqual([true]);
        });

        it('reverts and re-emits the reverted value when the PATCH is rejected', async () => {
            window.axios.patch.mockRejectedValueOnce(new Error('network error'));
            const wrapper = mountDetail({ event: makeEvent({ notifyUpdates: false }) });

            await wrapper.find('input[type="checkbox"]').trigger('change');
            // Let the rejected promise's catch handler run.
            await nextTick();
            await nextTick();

            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
            const emitted = wrapper.emitted('notify-updated');
            expect(emitted[emitted.length - 1]).toEqual([false]);
        });

        it('disables the toggle while a request is pending', async () => {
            let resolvePatch;
            window.axios.patch.mockReturnValueOnce(new Promise((resolve) => { resolvePatch = resolve; }));
            const wrapper = mountDetail({ event: makeEvent({ notifyUpdates: false }) });

            await wrapper.find('input[type="checkbox"]').trigger('change');
            expect(wrapper.find('input[type="checkbox"]').element.disabled).toBe(true);

            resolvePatch({ data: {} });
            await nextTick();
            await nextTick();
            expect(wrapper.find('input[type="checkbox"]').element.disabled).toBe(false);
        });
    });

    describe('organizer-dependent blocks', () => {
        it('renders the organizer card and notify block when event.organizer is present', () => {
            const wrapper = mountDetail({ event: makeEvent({ organizer: { name: 'Acme', thumbImagePath: null, slug: 'acme' } }) });
            expect(wrapper.findComponent({ name: 'OrganizerProfileCard' }).exists()).toBe(true);
            expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true);
        });

        it('renders neither the organizer card nor the notify block when there is no organizer', () => {
            const wrapper = mountDetail({ event: makeEvent({ organizer: null }) });
            expect(wrapper.findComponent({ name: 'OrganizerProfileCard' }).exists()).toBe(false);
            expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
        });
    });

    describe('share()', () => {
        it('falls back to clipboard copy and shows a confirmation message when navigator.share is unavailable', async () => {
            const originalShare = navigator.share;
            const originalClipboard = navigator.clipboard;
            // jsdom has no navigator.share by default; be explicit regardless.
            Object.defineProperty(navigator, 'share', { value: undefined, configurable: true });
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: vi.fn(() => Promise.resolve()) },
                configurable: true,
            });

            const wrapper = mountDetail({ event: makeEvent({ slug: 'my-event' }) });
            await wrapper.find('button').trigger('click'); // the "Share event" button is the first <button>
            await nextTick();
            await nextTick();

            expect(navigator.clipboard.writeText).toHaveBeenCalledWith(expect.stringContaining('/events/my-event'));
            expect(wrapper.text()).toContain('Link copied!');

            Object.defineProperty(navigator, 'share', { value: originalShare, configurable: true });
            Object.defineProperty(navigator, 'clipboard', { value: originalClipboard, configurable: true });
        });
    });
});
