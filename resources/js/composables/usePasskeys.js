import axios from 'axios';

/**
 * Passkeys in the browser: add one, sign in with one, and keep the
 * person's password manager in step with EI (WebAuthn Signal API).
 *
 * The server side is laravel/passkeys (routes in routes/auth.php). The
 * browser side is @simplewebauthn/browser, loaded only when a passkey
 * action actually starts, so the nav's login modal (on every page) does
 * not carry it.
 *
 * Every function here swallows "the person closed the passkey sheet": that
 * is a normal answer, not an error, and must never reach Sentry.
 */

let library = null;

// The app's shared client (app.js sets window.axios, with the CSRF header);
// the import is the fallback for a page without it.
const http = () => window.axios ?? axios;

const load = () => {
    // A failed download (a deploy swapping files) is tried again next time.
    library ??= import('@simplewebauthn/browser').catch((error) => {
        library = null;
        throw error;
    });
    return library;
};

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Signals are only hints: never hold anything up longer than this for one.
const SIGNAL_WAIT_MS = 1000;

/** True for a cancelled or superseded ceremony (closed sheet, timeout, a newer one started). */
export const isCancelled = (error) => (
    error?.name === 'NotAllowedError'
    || error?.name === 'AbortError'
    || error?.code === 'ERROR_CEREMONY_ABORTED'
);

/** Can this browser use passkeys at all? (No HTTPS, very old browser, some in-app browsers: no.) */
export const passkeysSupported = () => (
    typeof window !== 'undefined'
    && window.isSecureContext === true
    && typeof window.PublicKeyCredential === 'function'
);

const firstError = (error, fallback) => {
    const data = error?.response?.data;
    return data?.errors?.credential?.[0]
        || data?.errors?.name?.[0]
        || data?.message
        || fallback;
};

/** Fire-and-forget: browsers without the Signal API (Firefox today) just ignore it. */
const signal = async (opts) => {
    try {
        const { sendSignal } = await load();
        await Promise.race([sendSignal(opts), wait(SIGNAL_WAIT_MS)]);
    } catch {
        // Unsupported or refused: nothing to do, it is only a hint.
    }
};

/**
 * Tells the password manager which of this person's passkeys EI still
 * accepts (so removed ones disappear from it) and their current email and
 * name. `signals` is the `passkeySignals` / `signals` object the server sends.
 */
export const syncPasswordManager = async (signals) => {
    if (!signals?.rpId || !signals?.userId) return;

    await Promise.all([
        signal({
            signalName: 'allAcceptedCredentials',
            rpID: signals.rpId,
            userID: signals.userId,
            allAcceptedCredentialIDs: signals.allAcceptedCredentialIds ?? [],
        }),
        signal({
            signalName: 'currentUserDetails',
            rpID: signals.rpId,
            userID: signals.userId,
            userName: signals.name,
            userDisplayName: signals.displayName,
        }),
    ]);
};

/** A readable default name: "Chrome on Mac", "Safari on iPhone". */
export const defaultPasskeyName = (userAgent = navigator.userAgent) => {
    const browser = /Edg\//.test(userAgent) ? 'Edge'
        : /Firefox\//.test(userAgent) ? 'Firefox'
            : /(Chrome|CriOS)\//.test(userAgent) ? 'Chrome'
                : /Safari\//.test(userAgent) ? 'Safari'
                    : 'Browser';
    const platform = /iPhone/.test(userAgent) ? 'iPhone'
        : /iPad/.test(userAgent) ? 'iPad'
            : /Android/.test(userAgent) ? 'Android'
                : /Mac OS X|Macintosh/.test(userAgent) ? 'Mac'
                    : /Windows/.test(userAgent) ? 'Windows'
                        : /Linux/.test(userAgent) ? 'Linux'
                            : null;
    return platform ? `${browser} on ${platform}` : browser;
};

/**
 * Makes a passkey for the signed-in person and saves it.
 * Resolves to { id, name } when saved, or null when they cancelled.
 * Throws an Error with a readable message otherwise.
 */
export const addPasskey = async (name = defaultPasskeyName()) => {
    const { startRegistration } = await load();

    let credential;
    try {
        const { data } = await http().get('/user/passkeys/options');
        credential = await startRegistration({ optionsJSON: data.options });
    } catch (error) {
        if (isCancelled(error)) return null;
        if (error?.code === 'ERROR_AUTHENTICATOR_PREVIOUSLY_REGISTERED') {
            throw new Error('This device or password manager already has a passkey for your account.');
        }
        throw new Error(firstError(error, 'Could not create a passkey. Please try again.'));
    }

    try {
        const { data } = await http().post('/user/passkeys', { name, credential });
        return data;
    } catch (error) {
        throw new Error(firstError(error, 'Could not save the passkey. Please try again.'));
    }
};

/** Removes one of the signed-in person's passkeys. Follow with syncPasswordManager(). */
export const removePasskey = async (id) => {
    await http().delete(`/user/passkeys/${id}`);
};

// Sign-in attempts run one at a time. The server keeps a single challenge
// per session, so two overlapping options requests would leave the sheet
// holding the wrong one; and simplewebauthn aborts whichever ceremony
// started first, so a late older one would cut off the newer one.
let latestAttempt = 0;
let running = Promise.resolve(null);

// Longest we wait for an older attempt to stop (a slow options request).
const STOP_WAIT_MS = 10000;

/**
 * Waits until the attempt `previous` has finished, cancelling it as it
 * goes. It may still be fetching its options (it then sees it is stale and
 * stops) or preparing its sheet (simplewebauthn awaits an autofill check
 * before its ceremony can be aborted), so cancelling once is not enough.
 */
const stopAttempt = async (previous) => {
    let done = false;
    previous.then(() => { done = true; });
    await Promise.race([previous, wait(0)]);
    if (done) return;

    const { WebAuthnAbortService } = await load();
    for (let waited = 0; !done && waited < STOP_WAIT_MS; waited += 25) {
        WebAuthnAbortService.cancelCeremony();
        await Promise.race([previous, wait(25)]);
    }
};

/** Stops a pending passkey sheet or autofill (e.g. the login modal closed). */
export const cancelPasskeyCeremony = async () => {
    latestAttempt++;
    if (!library) return;
    await stopAttempt(running);
};

/** Can this browser offer passkeys in the email field's autofill list? */
export const autofillSupported = async () => {
    if (!passkeysSupported()) return false;
    try {
        const { browserSupportsWebAuthnAutofill } = await load();
        return await browserSupportsWebAuthnAutofill();
    } catch {
        return false;
    }
};

/**
 * Signs in with a passkey. With `autofill`, waits quietly until the person
 * picks a passkey from the email field's suggestions (the field needs
 * autocomplete="email webauthn"); without it, opens the browser's passkey
 * sheet right away.
 *
 * A new call first stops the one before it (see stopAttempt), and an
 * attempt that is no longer the latest, or whose `isCurrent` answers false
 * (the form closed), stops before opening a sheet, as if cancelled.
 *
 * Resolves to the address to go to next, or null when cancelled. Autofill
 * also resolves null when the options could not be fetched (rate limit,
 * network): it is a background offer, and nobody asked for it yet. Only
 * call it for guests: the routes are guest-only and answer a signed-in
 * browser with a redirect, not JSON.
 */
export const signInWithPasskey = ({ autofill = false, isCurrent = () => true } = {}) => {
    const attempt = ++latestAttempt;
    const previous = running;
    const current = () => attempt === latestAttempt && isCurrent();

    // When the older one signed the person in (they picked from the autofill
    // as they tapped the button), this one has nothing left to do: same answer.
    let signedInBy = null;
    previous.then((redirect) => { signedInBy = redirect; });

    const result = stopAttempt(previous).then(() => {
        if (signedInBy) return signedInBy;
        return current() ? runSignIn({ autofill, current }) : null;
    });
    const mine = result.then((redirect) => redirect, () => null);
    running = mine;
    // Finished: a later attempt starts fresh rather than following this one.
    mine.then(() => { if (running === mine) running = Promise.resolve(null); });
    return result;
};

const runSignIn = async ({ autofill, current }) => {
    const { startAuthentication } = await load();

    let options;
    try {
        ({ data: { options } } = await http().get('/passkeys/login/options'));
    } catch (error) {
        if (autofill) return null;
        throw new Error(firstError(error, 'Could not use a passkey. Please try again.'));
    }
    if (!current()) return null;

    let credential;
    try {
        credential = await startAuthentication({ optionsJSON: options, useBrowserAutofill: autofill });
    } catch (error) {
        if (isCancelled(error)) return null;
        throw new Error(firstError(error, 'Could not use a passkey. Please try again.'));
    }

    try {
        const { data } = await http().post('/passkeys/login', { credential, remember: true });
        await syncPasswordManager(data.signals);
        return data.redirect || '/';
    } catch (error) {
        const data = error?.response?.data;
        if (data?.code === 'unknown_credential') {
            // Removed on EI (or the account is gone): ask the password
            // manager to hide it so it is not offered again.
            await signal({
                signalName: 'unknownCredential',
                rpID: options?.rpId ?? window.location.hostname,
                credentialID: data.credential_id ?? credential.id,
            });
        }
        throw new Error(firstError(error, 'Could not sign in with that passkey. Please try again.'));
    }
};
