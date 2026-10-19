/**
 * Specs for composables/usePasskeys.js: the browser half of passkeys.
 * @simplewebauthn/browser is mocked; these check what is asked of the
 * server, what is told to the password manager, and that a closed passkey
 * sheet is a quiet null, never an error.
 */
import { vi } from 'vitest';

vi.mock('@simplewebauthn/browser', () => ({
    startRegistration: vi.fn(),
    startAuthentication: vi.fn(),
    sendSignal: vi.fn(),
    browserSupportsWebAuthnAutofill: vi.fn(),
    WebAuthnAbortService: { cancelCeremony: vi.fn() },
}));

import * as webauthn from '@simplewebauthn/browser';
import {
    addPasskey,
    cancelPasskeyCeremony,
    defaultPasskeyName,
    passkeysSupported,
    signInWithPasskey,
    syncPasswordManager,
} from '@/composables/usePasskeys';

const SIGNALS = {
    rpId: 'ei.test',
    userId: 'dXNlcg',
    allAcceptedCredentialIds: ['cred1'],
    name: 'pat@example.com',
    displayName: 'Pat',
};

const cancelled = () => Object.assign(new Error('closed'), { name: 'NotAllowedError' });

beforeEach(() => {
    vi.clearAllMocks();
    webauthn.sendSignal.mockResolvedValue(undefined);
});

describe('usePasskeys', () => {
    it('names a passkey after the browser and device', () => {
        const mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36';
        const iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
        const edge = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 Edg/141.0';

        expect(defaultPasskeyName(mac)).toBe('Chrome on Mac');
        expect(defaultPasskeyName(iphone)).toBe('Safari on iPhone');
        expect(defaultPasskeyName(edge)).toBe('Edge on Windows');
    });

    it('needs a secure page and WebAuthn to offer passkeys', () => {
        const original = window.isSecureContext;
        window.PublicKeyCredential = function PublicKeyCredential() {};

        Object.defineProperty(window, 'isSecureContext', { value: false, configurable: true });
        expect(passkeysSupported()).toBe(false);

        Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true });
        expect(passkeysSupported()).toBe(true);

        Object.defineProperty(window, 'isSecureContext', { value: original, configurable: true });
        delete window.PublicKeyCredential;
    });

    it('signs in: options, the passkey, the answer, then syncs the password manager', async () => {
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c', rpId: 'ei.test' } } });
        webauthn.startAuthentication.mockResolvedValue({ id: 'cred1' });
        window.axios.post.mockResolvedValue({ data: { redirect: 'https://ei.test/hub', signals: SIGNALS } });

        const redirect = await signInWithPasskey({ autofill: true });

        expect(redirect).toBe('https://ei.test/hub');
        expect(window.axios.get).toHaveBeenCalledWith('/passkeys/login/options');
        expect(webauthn.startAuthentication).toHaveBeenCalledWith({
            optionsJSON: { challenge: 'c', rpId: 'ei.test' },
            useBrowserAutofill: true,
        });
        expect(window.axios.post).toHaveBeenCalledWith('/passkeys/login', { credential: { id: 'cred1' }, remember: true });
        expect(webauthn.sendSignal).toHaveBeenCalledWith(expect.objectContaining({
            signalName: 'allAcceptedCredentials',
            allAcceptedCredentialIDs: ['cred1'],
        }));
        expect(webauthn.sendSignal).toHaveBeenCalledWith(expect.objectContaining({
            signalName: 'currentUserDetails',
            userName: 'pat@example.com',
            userDisplayName: 'Pat',
        }));
    });

    it('autofill stays quiet when the options cannot be fetched (rate limit, network)', async () => {
        window.axios.get.mockRejectedValue({ response: { status: 429, data: { message: 'Too Many Attempts.' } } });

        await expect(signInWithPasskey({ autofill: true })).resolves.toBeNull();
        expect(webauthn.startAuthentication).not.toHaveBeenCalled();

        await expect(signInWithPasskey()).rejects.toThrow('Too Many Attempts.');
    });

    it('an attempt that is no longer current stops before opening a passkey sheet', async () => {
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c' } } });

        await expect(signInWithPasskey({ isCurrent: () => false })).resolves.toBeNull();
        expect(webauthn.startAuthentication).not.toHaveBeenCalled();
    });

    describe('a new attempt waits for the older one to stop', () => {
        let events;

        beforeEach(() => {
            events = [];
            let inFlight = 0;
            window.axios.get.mockImplementation(async () => {
                inFlight++;
                if (inFlight > 1) events.push('two options requests at once');
                await new Promise((resolve) => setTimeout(resolve, 5));
                inFlight--;
                return { data: { options: { challenge: 'c', rpId: 'ei.test' } } };
            });
            // The autofill ceremony waits until it is cancelled, like a real
            // conditional request nobody has picked from yet.
            let abortAutofill = null;
            webauthn.WebAuthnAbortService.cancelCeremony.mockImplementation(() => abortAutofill?.());
            webauthn.startAuthentication.mockImplementation(({ useBrowserAutofill }) => {
                events.push(useBrowserAutofill ? 'autofill sheet' : 'button sheet');
                if (!useBrowserAutofill) return Promise.resolve({ id: 'cred1' });
                return new Promise((resolve, reject) => {
                    abortAutofill = () => { abortAutofill = null; events.push('autofill aborted'); reject(cancelled()); };
                });
            });
            window.axios.post.mockResolvedValue({ data: { redirect: '/hub', signals: SIGNALS } });
        });

        afterEach(() => {
            webauthn.WebAuthnAbortService.cancelCeremony.mockReset();
            webauthn.startAuthentication.mockReset();
            window.axios.get.mockReset();
        });

        it('while the older one is still fetching its options', async () => {
            const autofill = signInWithPasskey({ autofill: true });
            const button = signInWithPasskey();

            await expect(autofill).resolves.toBeNull();
            await expect(button).resolves.toBe('/hub');
            expect(events).toEqual(['button sheet']);
        });

        it('while the older one already has its sheet open', async () => {
            const autofill = signInWithPasskey({ autofill: true });
            await vi.waitFor(() => expect(events).toContain('autofill sheet'));
            const button = signInWithPasskey();

            await expect(autofill).resolves.toBeNull();
            await expect(button).resolves.toBe('/hub');
            expect(events).toEqual(['autofill sheet', 'autofill aborted', 'button sheet']);
        });

        it('and when the older one signed the person in meanwhile, the newer one just follows it', async () => {
            webauthn.startAuthentication.mockImplementation(({ useBrowserAutofill }) => {
                events.push(useBrowserAutofill ? 'autofill sheet' : 'button sheet');
                return Promise.resolve({ id: 'cred1' });
            });

            // The autofill has its passkey and is sending it when the button is tapped.
            let answer;
            window.axios.post.mockReturnValue(new Promise((resolve) => { answer = resolve; }));
            const autofill = signInWithPasskey({ autofill: true });
            await vi.waitFor(() => expect(window.axios.post).toHaveBeenCalled());
            const button = signInWithPasskey();
            answer({ data: { redirect: '/hub', signals: SIGNALS } });

            await expect(autofill).resolves.toBe('/hub');
            await expect(button).resolves.toBe('/hub');
            expect(window.axios.get).toHaveBeenCalledTimes(1);
            expect(window.axios.post).toHaveBeenCalledTimes(1);
        });
    });

    it('closing the form stops an attempt still fetching its options', async () => {
        let answer;
        window.axios.get.mockReturnValue(new Promise((resolve) => { answer = resolve; }));

        const pending = signInWithPasskey({ autofill: true });
        const closing = cancelPasskeyCeremony();
        answer({ data: { options: { challenge: 'c' } } });

        await expect(pending).resolves.toBeNull();
        await closing;
        expect(webauthn.startAuthentication).not.toHaveBeenCalled();
    });

    it('a signal that never settles does not hold up the "passkey no longer on any account" message', async () => {
        vi.useFakeTimers();
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c', rpId: 'ei.test' } } });
        webauthn.startAuthentication.mockResolvedValue({ id: 'gone' });
        window.axios.post.mockRejectedValue({
            response: { data: { code: 'unknown_credential', credential_id: 'gone', errors: { credential: ['No longer on any account.'] } } },
        });
        webauthn.sendSignal.mockReturnValue(new Promise(() => {}));

        const signingIn = signInWithPasskey();
        const outcome = expect(signingIn).rejects.toThrow('No longer on any account.');
        await vi.advanceTimersByTimeAsync(1000);

        await outcome;
        vi.useRealTimers();
    });

    it('a password manager that never answers the signal does not hold up the sign-in', async () => {
        vi.useFakeTimers();
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c', rpId: 'ei.test' } } });
        webauthn.startAuthentication.mockResolvedValue({ id: 'cred1' });
        window.axios.post.mockResolvedValue({ data: { redirect: '/hub', signals: SIGNALS } });
        webauthn.sendSignal.mockReturnValue(new Promise(() => {}));

        const signingIn = signInWithPasskey();
        await vi.advanceTimersByTimeAsync(1000);

        await expect(signingIn).resolves.toBe('/hub');
        vi.useRealTimers();
    });

    it('a closed passkey sheet is null, not an error', async () => {
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c' } } });
        webauthn.startAuthentication.mockRejectedValue(cancelled());

        await expect(signInWithPasskey()).resolves.toBeNull();
        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('a passkey EI no longer knows is reported to the password manager', async () => {
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c', rpId: 'ei.test' } } });
        webauthn.startAuthentication.mockResolvedValue({ id: 'gone' });
        window.axios.post.mockRejectedValue({
            response: { data: { code: 'unknown_credential', credential_id: 'gone', errors: { credential: ['No longer on any account.'] } } },
        });

        await expect(signInWithPasskey()).rejects.toThrow('No longer on any account.');
        expect(webauthn.sendSignal).toHaveBeenCalledWith({
            signalName: 'unknownCredential',
            rpID: 'ei.test',
            credentialID: 'gone',
        });
    });

    it('adds a passkey with a default name', async () => {
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c' } } });
        webauthn.startRegistration.mockResolvedValue({ id: 'new' });
        window.axios.post.mockResolvedValue({ data: { id: '7', name: 'Phone' } });

        await expect(addPasskey('Phone')).resolves.toEqual({ id: '7', name: 'Phone' });
        expect(window.axios.get).toHaveBeenCalledWith('/user/passkeys/options');
        expect(window.axios.post).toHaveBeenCalledWith('/user/passkeys', { name: 'Phone', credential: { id: 'new' } });
    });

    it('adding: cancelled is null, an already-saved device is explained', async () => {
        window.axios.get.mockResolvedValue({ data: { options: { challenge: 'c' } } });

        webauthn.startRegistration.mockRejectedValueOnce(cancelled());
        await expect(addPasskey('Phone')).resolves.toBeNull();

        webauthn.startRegistration.mockRejectedValueOnce(Object.assign(new Error('x'), { code: 'ERROR_AUTHENTICATOR_PREVIOUSLY_REGISTERED' }));
        await expect(addPasskey('Phone')).rejects.toThrow('already has a passkey');
    });

    it('a browser without the Signal API is fine', async () => {
        webauthn.sendSignal.mockRejectedValue(new Error('unsupported'));

        await expect(syncPasswordManager(SIGNALS)).resolves.toBeUndefined();
    });
});
