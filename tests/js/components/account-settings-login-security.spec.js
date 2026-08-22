/**
 * Specs for PageComponents/AccountSettings/Pages/LoginSecurity.vue
 *
 * Covers:
 *  - Provider-based label rendering (google/github/apple/passwordless).
 *  - The second "Login code" row only renders when provider is truthy —
 *    it's always a valid sign-in method, but only worth mentioning as an
 *    ALTERNATIVE when the account's primary method is something else.
 *  - Device list rendering, the "Current session" badge, and that the
 *    Log out button is hidden for the current device but present (and
 *    working) for others.
 *  - The "Show all N devices" collapse/expand behavior past 5 devices.
 */
import { mount, flushPromises } from '@vue/test-utils';
import LoginSecurity from '@/PageComponents/AccountSettings/Pages/LoginSecurity.vue';

function device(overrides = {}) {
    return {
        id: 1,
        browser: 'Chrome',
        platform: 'Mac',
        device_type: 'desktop',
        last_seen_at: new Date().toISOString(),
        isCurrent: false,
        ...overrides,
    };
}

function mockGet(provider, devices = []) {
    window.axios.get.mockResolvedValue({ data: { provider, devices } });
}

async function mountLoaded(provider, devices) {
    mockGet(provider, devices);
    const wrapper = mount(LoginSecurity);
    await flushPromises();
    return wrapper;
}

describe('AccountSettings LoginSecurity.vue', () => {
    it('fetches login-security data on mount', async () => {
        const wrapper = await mountLoaded('google', []);
        expect(window.axios.get).toHaveBeenCalledWith('/api/account-settings/login-security');
        expect(wrapper.text()).toContain('You sign in with Google');
    });

    it.each([
        ['google', 'You sign in with Google'],
        ['github', 'You sign in with GitHub'],
        ['apple', 'You sign in with Apple'],
        [null, 'You sign in with a login code sent to your email'],
    ])('renders the right label for provider=%s', async (provider, label) => {
        const wrapper = await mountLoaded(provider, []);
        expect(wrapper.text()).toContain(label);
    });

    it('only shows the second "Login code" row when a real provider is set', async () => {
        const withProvider = await mountLoaded('google', []);
        expect(withProvider.text()).toContain('Login code');
        expect(withProvider.text()).toContain("don't need Google to sign in");

        const passwordless = await mountLoaded(null, []);
        expect(passwordless.text()).not.toContain('A one-time code sent to your email also works');
    });

    it('shows "No device history yet." when there are no devices', async () => {
        const wrapper = await mountLoaded('google', []);
        expect(wrapper.text()).toContain('No device history yet.');
    });

    it('marks the current device without a Log out button, and shows Log out for others', async () => {
        const current = device({ id: 1, isCurrent: true });
        const other = device({ id: 2, isCurrent: false, browser: 'Safari' });
        const wrapper = await mountLoaded('google', [current, other]);

        expect(wrapper.text()).toContain('Current session');
        const logoutButtons = wrapper.findAll('button').filter((b) => b.text().includes('Log out'));
        expect(logoutButtons).toHaveLength(1);
    });

    it('logs out a device: DELETEs the endpoint and removes it from the list', async () => {
        const other = device({ id: 2, browser: 'Safari' });
        const wrapper = await mountLoaded('google', [other]);
        window.axios.delete.mockResolvedValue({ data: {} });

        const logoutButton = wrapper.findAll('button').find((b) => b.text().includes('Log out'));
        await logoutButton.trigger('click');
        await flushPromises();

        expect(window.axios.delete).toHaveBeenCalledWith('/api/account-settings/login-security/devices/2');
        expect(wrapper.text()).toContain('No device history yet.');
    });

    it('collapses to 5 devices by default and expands on "Show all"', async () => {
        const devices = Array.from({ length: 7 }, (_, i) => device({ id: i + 1, browser: `Browser ${i + 1}` }));
        const wrapper = await mountLoaded('google', devices);

        expect(wrapper.findAll('input, .divide-y > div').length).toBeGreaterThan(0); // sanity: rows rendered
        expect(wrapper.text()).toContain('Show all 7 devices');
        expect(wrapper.text()).not.toContain('Browser 6');

        const showAll = wrapper.findAll('button').find((b) => b.text().includes('Show all'));
        await showAll.trigger('click');

        expect(wrapper.text()).toContain('Browser 6');
        expect(wrapper.text()).toContain('Browser 7');
        expect(wrapper.text()).not.toContain('Show all 7 devices');
    });
});
