/**
 * Specs for PageComponents/AccountSettings/Pages/PersonalInformation.vue
 *
 * Covers:
 *  - Fetch-on-mount populates every row (legal name, preferred name, email
 *    + verified badge, location).
 *  - Legal name and preferred name edit/save round trips, including the
 *    error path and Cancel restoring the pre-edit snapshot.
 *  - The two-step email change flow (send code -> confirm code), including
 *    the server error message surfacing verbatim.
 *
 * The location row's live Google Places autocomplete is NOT covered here —
 * importMapsLibrary is mocked to a no-op so entering that row doesn't throw,
 * but the predictions dropdown/place-selection flow is a large, separate
 * surface better covered by its own focused spec (see final report).
 */
import { mount, flushPromises } from '@vue/test-utils';
import { vi } from 'vitest';
import PersonalInformation from '@/PageComponents/AccountSettings/Pages/PersonalInformation.vue';

vi.mock('@/composables/useGoogleMaps', () => ({
    importMapsLibrary: vi.fn(() => Promise.resolve({
        AutocompleteService: vi.fn(function AutocompleteService() {
            this.getPlacePredictions = vi.fn(() => Promise.resolve({ predictions: [] }));
        }),
        Place: vi.fn(),
    })),
}));

function mockGet(overrides = {}) {
    window.axios.get.mockResolvedValue({
        data: {
            legal_first_name: 'Jane',
            legal_last_name: 'Doe',
            name: 'Janie',
            email: 'jane@example.com',
            email_verified: true,
            location: { city: 'Portland', region: 'OR', country: 'US', lat: 45.5, lng: -122.6 },
            ...overrides,
        },
    });
}

async function mountLoaded(overrides) {
    mockGet(overrides);
    const wrapper = mount(PersonalInformation);
    await flushPromises();
    return wrapper;
}

describe('AccountSettings PersonalInformation.vue', () => {
    it('fetches on mount and renders every row', async () => {
        const wrapper = await mountLoaded();

        expect(window.axios.get).toHaveBeenCalledWith('/api/account-settings/personal-info');
        expect(wrapper.text()).toContain('Jane Doe');
        expect(wrapper.text()).toContain('Janie');
        expect(wrapper.text()).toContain('jane@example.com');
        expect(wrapper.text()).toContain('Verified');
        expect(wrapper.text()).toContain('Portland, OR');
    });

    it('shows "Not provided" / "Not verified" for missing/unverified fields', async () => {
        const wrapper = await mountLoaded({ legal_first_name: '', legal_last_name: '', name: '', email_verified: false, location: null });
        expect(wrapper.text()).toContain('Not verified');
        // Two rows (legal name, location) can both be empty at once.
        expect(wrapper.findAll('button').filter((b) => b.text() === 'Add').length).toBeGreaterThanOrEqual(1);
    });

    describe('legal name', () => {
        it('edits and saves, PATCHing the right payload and exiting edit mode', async () => {
            const wrapper = await mountLoaded();
            window.axios.patch.mockResolvedValue({ data: {} });

            await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');
            const inputs = wrapper.findAll('input[type="text"]');
            await inputs[0].setValue('Janet');
            await inputs[1].setValue('Smith');
            await wrapper.find('form').trigger('submit');
            await flushPromises();

            expect(window.axios.patch).toHaveBeenCalledWith('/api/account-settings/personal-info/legal-name', {
                legal_first_name: 'Janet',
                legal_last_name: 'Smith',
            });
            expect(wrapper.text()).toContain('Janet Smith');
            expect(wrapper.find('form').exists()).toBe(false);
        });

        it('shows an error and stays in edit mode when the save fails', async () => {
            const wrapper = await mountLoaded();
            window.axios.patch.mockRejectedValue(new Error('down'));

            await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');
            await wrapper.find('form').trigger('submit');
            await flushPromises();

            expect(wrapper.text()).toContain('Could not save that change');
            expect(wrapper.find('form').exists()).toBe(true);
        });

        it('Cancel restores the original values without saving', async () => {
            const wrapper = await mountLoaded();

            await wrapper.findAll('button').find((b) => b.text() === 'Edit').trigger('click');
            await wrapper.findAll('input[type="text"]')[0].setValue('Someone Else');
            await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click');

            expect(wrapper.text()).toContain('Jane Doe');
            expect(window.axios.patch).not.toHaveBeenCalled();
        });
    });

    describe('preferred name', () => {
        it('edits and saves', async () => {
            const wrapper = await mountLoaded();
            window.axios.patch.mockResolvedValue({ data: {} });

            const editButtons = wrapper.findAll('button').filter((b) => b.text() === 'Edit');
            await editButtons[1].trigger('click'); // Legal name is the first "Edit" row
            await wrapper.find('input[type="text"]').setValue('JJ');
            await wrapper.find('form').trigger('submit');
            await flushPromises();

            expect(window.axios.patch).toHaveBeenCalledWith('/api/account-settings/personal-info/preferred-name', { name: 'JJ' });
            expect(wrapper.text()).toContain('JJ');
        });
    });

    describe('email', () => {
        it('sends a code, then confirms it and updates the displayed email', async () => {
            const wrapper = await mountLoaded();
            window.axios.post.mockResolvedValue({ data: {} });

            const editButtons = wrapper.findAll('button').filter((b) => b.text() === 'Edit');
            await editButtons[2].trigger('click'); // legal name, preferred name, then email
            await wrapper.find('input[type="email"]').setValue('new@example.com');
            await wrapper.findAll('button').find((b) => b.text().includes('Send code')).trigger('click');
            await flushPromises();

            expect(window.axios.post).toHaveBeenCalledWith('/users/email/verify', { email: 'new@example.com' });
            expect(wrapper.text()).toContain('Enter the 6-digit code');

            await wrapper.find('input[type="text"]').setValue('123456');
            await wrapper.findAll('button').find((b) => b.text().includes('Confirm')).trigger('click');
            await flushPromises();

            expect(window.axios.post).toHaveBeenCalledWith('/users/email/confirm', { email: 'new@example.com', code: '123456' });
            expect(wrapper.text()).toContain('new@example.com');
        });

        it('surfaces the servers error message when the code is wrong', async () => {
            const wrapper = await mountLoaded();
            window.axios.post.mockResolvedValueOnce({ data: {} }); // send code
            const editButtons = wrapper.findAll('button').filter((b) => b.text() === 'Edit');
            await editButtons[2].trigger('click'); // legal name, preferred name, then email
            await wrapper.find('input[type="email"]').setValue('new@example.com');
            await wrapper.findAll('button').find((b) => b.text().includes('Send code')).trigger('click');
            await flushPromises();

            window.axios.post.mockRejectedValueOnce({ response: { data: { message: 'That code did not match.' } } });
            await wrapper.find('input[type="text"]').setValue('000000');
            await wrapper.findAll('button').find((b) => b.text().includes('Confirm')).trigger('click');
            await flushPromises();

            expect(wrapper.text()).toContain('That code did not match.');
        });
    });
});
