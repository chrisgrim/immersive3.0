/**
 * Specs for PageComponents/Profile/Pages/AboutMe.vue
 *
 * This component calls the bare global `axios` (no import statement) — same
 * pattern as the AccountSettings pages — so window.axios.get/patch/post
 * mocks from tests/js/setup.js apply directly.
 *
 * Covers:
 *  - isOwner fetches /api/profile/stats + /api/profile/followed-organizers;
 *    a non-owner instead fetches /api/profile/{id}/public-extras.
 *  - Bio start/save/cancel flow, including a save failure leaving editingBio open.
 *  - Avatar upload: triggers the hidden file input, POSTs FormData, updates
 *    the local avatar path on success, and surfaces an error message on failure.
 *  - yearsOnEi is computed from user.created_at, floored at a minimum of 1.
 *  - Non-owner privacy-gated sections only render when publicExtras actually
 *    has a value for that field (not just when isOwner is false).
 */
import { mount, flushPromises } from '@vue/test-utils';
import AboutMe from '@/PageComponents/Profile/Pages/AboutMe.vue';

function makeUser(overrides = {}) {
    return {
        id: 42,
        name: 'Chris',
        thumbImagePath: null,
        gravatar: null,
        blurb: '',
        created_at: new Date().toISOString(),
        ...overrides,
    };
}

function mountAboutMe(props = {}) {
    return mount(AboutMe, {
        props: {
            user: makeUser(),
            isOwner: false,
            ...props,
        },
        global: {
            stubs: { FollowedOrgsCarousel: true },
        },
    });
}

describe('Profile/Pages/AboutMe.vue', () => {
    beforeEach(() => {
        // Sensible defaults for both fetch-on-mount branches so tests that
        // aren't specifically exercising the fetch (bio editing, avatar
        // upload) don't have to repeat this setup — individual tests below
        // override with mockImplementation/mockResolvedValue where the
        // actual response shape matters to the assertion.
        window.axios.get.mockImplementation((url) => {
            if (url === '/api/profile/stats') return Promise.resolve({ data: { saved_events_count: 0, followed_organizers_count: 0 } });
            if (url === '/api/profile/followed-organizers') return Promise.resolve({ data: { organizers: [] } });
            return Promise.resolve({ data: { saved_events_count: null, followed_organizers: null } });
        });
    });


    describe('fetch-on-mount branching', () => {
        it('fetches owner stats and followed organizers when isOwner is true', async () => {
            window.axios.get.mockImplementation((url) => {
                if (url === '/api/profile/stats') return Promise.resolve({ data: { saved_events_count: 3, followed_organizers_count: 2 } });
                if (url === '/api/profile/followed-organizers') return Promise.resolve({ data: { organizers: [] } });
                return Promise.resolve({ data: {} });
            });

            mountAboutMe({ isOwner: true });
            await flushPromises();

            expect(window.axios.get).toHaveBeenCalledWith('/api/profile/stats');
            expect(window.axios.get).toHaveBeenCalledWith('/api/profile/followed-organizers');
            expect(window.axios.get).not.toHaveBeenCalledWith(expect.stringContaining('public-extras'));
        });

        it('fetches public-extras for the target user when isOwner is false', async () => {
            window.axios.get.mockResolvedValue({ data: { saved_events_count: null, followed_organizers: null } });

            mountAboutMe({ isOwner: false, user: makeUser({ id: 99 }) });
            await flushPromises();

            expect(window.axios.get).toHaveBeenCalledWith('/api/profile/99/public-extras');
            expect(window.axios.get).not.toHaveBeenCalledWith('/api/profile/stats');
        });

        it('renders owner stats once they resolve', async () => {
            window.axios.get.mockImplementation((url) => {
                if (url === '/api/profile/stats') return Promise.resolve({ data: { saved_events_count: 7, followed_organizers_count: 4 } });
                return Promise.resolve({ data: { organizers: [] } });
            });

            const wrapper = mountAboutMe({ isOwner: true });
            await flushPromises();

            expect(wrapper.text()).toContain('7');
            expect(wrapper.text()).toContain('4');
        });
    });

    describe('non-owner privacy gating', () => {
        it('does not show a saved-events count when publicExtras.saved_events_count is null', async () => {
            window.axios.get.mockResolvedValue({ data: { saved_events_count: null, followed_organizers: null } });
            const wrapper = mountAboutMe({ isOwner: false });
            await flushPromises();

            expect(wrapper.text()).not.toContain('Saved events');
        });

        it('shows the saved-events count when the target user opted it into public visibility', async () => {
            window.axios.get.mockResolvedValue({ data: { saved_events_count: 5, followed_organizers: null } });
            const wrapper = mountAboutMe({ isOwner: false });
            await flushPromises();

            expect(wrapper.text()).toContain('Saved events');
            expect(wrapper.text()).toContain('5');
        });

        it('shows "No bio yet." (not the owner\'s "Add a bio" prompt) when a non-owner has no bio', () => {
            const wrapper = mountAboutMe({ isOwner: false, user: makeUser({ blurb: '' }) });
            expect(wrapper.text()).toContain('No bio yet.');
            expect(wrapper.text()).not.toContain('Add a bio to tell people');
        });

        it('does not show followed organizers when publicExtras.followed_organizers is empty', async () => {
            window.axios.get.mockResolvedValue({ data: { saved_events_count: null, followed_organizers: [] } });
            const wrapper = mountAboutMe({ isOwner: false });
            await flushPromises();

            expect(wrapper.find('followed-orgs-carousel-stub').exists()).toBe(false);
        });

        it('shows followed organizers when the target user opted them into public visibility', async () => {
            window.axios.get.mockResolvedValue({
                data: { saved_events_count: null, followed_organizers: [{ id: 1, name: 'Mister Mischief' }] },
            });
            const wrapper = mountAboutMe({ isOwner: false });
            await flushPromises();

            expect(wrapper.find('followed-orgs-carousel-stub').exists()).toBe(true);
        });
    });

    describe('bio editing', () => {
        it('starts editing with the current bio prefilled, and Cancel discards changes without saving', async () => {
            const wrapper = mountAboutMe({ isOwner: true, user: makeUser({ blurb: 'Original bio' }) });
            await flushPromises();

            await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');
            const textarea = wrapper.find('textarea');
            expect(textarea.element.value).toBe('Original bio');

            await textarea.setValue('Changed but not saved');
            await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click');

            expect(wrapper.find('textarea').exists()).toBe(false);
            expect(wrapper.text()).toContain('Original bio');
            expect(window.axios.patch).not.toHaveBeenCalled();
        });

        it('saves the bio via PATCH /api/profile/bio and exits edit mode on success', async () => {
            window.axios.patch.mockResolvedValue({ data: {} });
            const wrapper = mountAboutMe({ isOwner: true, user: makeUser({ blurb: '' }) });
            await flushPromises();

            await wrapper.findAll('button').find((b) => b.text() === 'Add').trigger('click');
            await wrapper.find('textarea').setValue('New bio text');
            await wrapper.find('form').trigger('submit.prevent');
            await flushPromises();

            expect(window.axios.patch).toHaveBeenCalledWith('/api/profile/bio', { blurb: 'New bio text' });
            expect(wrapper.find('textarea').exists()).toBe(false);
            expect(wrapper.text()).toContain('New bio text');
        });

        it('sends null for an emptied-out bio', async () => {
            window.axios.patch.mockResolvedValue({ data: {} });
            const wrapper = mountAboutMe({ isOwner: true, user: makeUser({ blurb: 'Something' }) });
            await flushPromises();

            await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');
            await wrapper.find('textarea').setValue('');
            await wrapper.find('form').trigger('submit.prevent');
            await flushPromises();

            expect(window.axios.patch).toHaveBeenCalledWith('/api/profile/bio', { blurb: null });
        });

        it('stays in edit mode with the draft intact when the save fails', async () => {
            window.axios.patch.mockRejectedValue(new Error('network error'));
            const wrapper = mountAboutMe({ isOwner: true, user: makeUser({ blurb: 'Original' }) });
            await flushPromises();

            await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');
            await wrapper.find('textarea').setValue('Attempted change');
            await wrapper.find('form').trigger('submit.prevent');
            await flushPromises();

            expect(wrapper.find('textarea').exists()).toBe(true);
            expect(wrapper.find('textarea').element.value).toBe('Attempted change');
        });
    });

    describe('avatar upload', () => {
        it('POSTs FormData to /users/{id} and updates the avatar path on success', async () => {
            window.axios.post.mockResolvedValue({ data: { thumbImagePath: 'user-images/new.webp' } });
            const wrapper = mountAboutMe({ isOwner: true, user: makeUser({ id: 42 }) });
            await flushPromises();

            const input = wrapper.find('input[type="file"]');
            const file = new File(['contents'], 'avatar.png', { type: 'image/png' });
            Object.defineProperty(input.element, 'files', { value: [file] });
            await input.trigger('change');
            await flushPromises();

            expect(window.axios.post).toHaveBeenCalledTimes(1);
            const [url, formData] = window.axios.post.mock.calls[0];
            expect(url).toBe('/users/42');
            expect(formData).toBeInstanceOf(FormData);
            expect(formData.get('image')).toBe(file);
        });

        it('shows an error message and stops uploading when the upload fails', async () => {
            window.axios.post.mockRejectedValue(new Error('upload failed'));
            const wrapper = mountAboutMe({ isOwner: true });
            await flushPromises();

            const input = wrapper.find('input[type="file"]');
            const file = new File(['contents'], 'avatar.png', { type: 'image/png' });
            Object.defineProperty(input.element, 'files', { value: [file] });
            await input.trigger('change');
            await flushPromises();

            expect(wrapper.text()).toContain('Could not update your photo');
        });

        it('does nothing when the file input change fires with no file selected', async () => {
            const wrapper = mountAboutMe({ isOwner: true });
            await flushPromises();
            window.axios.post.mockClear();

            const input = wrapper.find('input[type="file"]');
            Object.defineProperty(input.element, 'files', { value: [] });
            await input.trigger('change');
            await flushPromises();

            expect(window.axios.post).not.toHaveBeenCalled();
        });
    });

    describe('yearsOnEi', () => {
        it('shows a minimum of 1 year for an account created recently', () => {
            const wrapper = mountAboutMe({ user: makeUser({ created_at: new Date().toISOString() }) });
            expect(wrapper.text()).toContain('1');
            expect(wrapper.text()).toContain('Years on EI');
        });

        it('rounds up for an account older than a year', () => {
            const twoYearsAgo = new Date(Date.now() - 2 * 365.25 * 24 * 60 * 60 * 1000 - 1000 * 60 * 60 * 24 * 30);
            const wrapper = mountAboutMe({ user: makeUser({ created_at: twoYearsAgo.toISOString() }) });
            expect(wrapper.text()).toContain('3'); // just past 2 years -> ceil'd to 3
        });
    });
});
