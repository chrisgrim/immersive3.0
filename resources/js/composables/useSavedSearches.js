import axios from 'axios';

/**
 * Auto-saves the current search as the user's single "recent search" slot —
 * see SaveSearchAction on the backend for the overwrite-in-place behavior
 * this relies on (a user doing dozens of searches never accumulates more
 * than one unpinned row plus whatever they've pinned).
 *
 * Fire-and-forget: never throws, never blocks the actual search.
 *
 * A guest gets the same single slot, kept in this browser instead (see the
 * guest search section below): their last search shows in the dropdown, and
 * pinning it asks them to log in. Logging in carries it into their account
 * (carryOverGuestSearch), so nothing they did is lost.
 */
export async function saveSearch(name, criteria) {
    if (!window.Laravel?.user?.id) {
        writeGuestSearch({ name, criteria, updated_at: new Date().toISOString(), pinRequestedAt: null });
        return;
    }

    try {
        await axios.post('/api/hub/saved-searches', { name, criteria });
    } catch (error) {
        console.error('[saved-searches] failed to auto-save', error);
    }
}

// ----- guest search: one slot, in this browser -----
// Only ever the last search, overwritten by the next one, like the account's
// own unpinned slot. Storage can be missing or throw (private windows,
// blocked site data), so every access is guarded, and a failure just means
// the guest has no recent search, which is how the site behaved before.

const GUEST_SEARCH_KEY = 'ei_guest_search';

export function readGuestSearch() {
    try {
        const stored = JSON.parse(window.localStorage.getItem(GUEST_SEARCH_KEY) || 'null');
        return stored && stored.name && stored.criteria ? stored : null;
    } catch {
        return null;
    }
}

function writeGuestSearch(search) {
    try {
        window.localStorage.setItem(GUEST_SEARCH_KEY, JSON.stringify(search));
    } catch {
        // No storage: nothing is remembered; the search itself still runs.
    }
}

function clearGuestSearch() {
    try {
        window.localStorage.removeItem(GUEST_SEARCH_KEY);
    } catch {
        // Nothing to clear.
    }
}

// Clear only the copy that was read, not a newer search another tab has
// stored since (it carries over on its own next page load).
function clearGuestSearchIfStill(search) {
    if (readGuestSearch()?.updated_at === search.updated_at) clearGuestSearch();
}

/**
 * The guest's search as a dropdown row, shaped like the rows the account
 * endpoint returns. `url` goes through the server, so the query string is
 * built by BuildSearchUrlAction, the same code that builds an account row's.
 */
export function guestSearchRow() {
    const search = readGuestSearch();
    if (!search) return null;

    return {
        id: 'guest',
        guest: true,
        name: search.name,
        criteria: search.criteria,
        url: `/index/search/replay?criteria=${encodeURIComponent(JSON.stringify(search.criteria))}`,
        pinned: false,
        updated_at: search.updated_at,
    };
}

/**
 * Pinning needs an account: remember which search they pinned and when, then
 * ask them to log in. It's the row they clicked that's kept, not whatever
 * another tab has stored since the dropdown opened. The login modal lives in
 * nav-profile.vue, which only desktop renders, so a phone goes to the login
 * page instead.
 */
export function pinGuestSearchAfterLogin(row) {
    const search = row ? { name: row.name, criteria: row.criteria, updated_at: row.updated_at } : readGuestSearch();
    if (search) writeGuestSearch({ ...search, pinRequestedAt: new Date().toISOString() });

    if (window.Laravel?.isMobile) {
        window.location.href = '/login';
        return;
    }

    window.dispatchEvent(new CustomEvent('open-login-modal'));
}

// How long a guest search, or a request to pin it, still belongs to whoever
// logs in next on this browser. Past it, on a shared computer that could be
// someone else entirely, so it's dropped rather than carried over. A time
// ahead of now (the clock was wrong when it was stored) isn't trusted
// beyond a few minutes' drift.
const CARRY_OVER_WINDOW_MS = 60 * 60 * 1000;
const CLOCK_DRIFT_MS = 5 * 60 * 1000;

const isRecent = (iso) => {
    const age = Date.now() - Date.parse(iso || '');
    return Number.isFinite(age) && age > -CLOCK_DRIFT_MS && age < CARRY_OVER_WINDOW_MS;
};

/**
 * Run once per page load. When a logged-in user still has a guest search in
 * this browser (they searched, then logged in or signed up), save it to
 * their account, pin it if they asked to, and clear the browser's copy. Only
 * a search or a pin request from the last hour moves; anything older is
 * dropped, since it may be someone else's. The copy is cleared only once the
 * account has it, so a failed request tries again on the next page.
 */
export async function carryOverGuestSearch() {
    if (!window.Laravel?.user?.id) return;

    const search = readGuestSearch();
    if (!search) return;

    const pinWanted = isRecent(search.pinRequestedAt);

    if (!pinWanted && !isRecent(search.updated_at)) {
        clearGuestSearchIfStill(search);
        return;
    }

    try {
        // One request: the server saves and pins under one lock, so the pin
        // can't land on a search another tab saves in between, and it never
        // unpins a search the account already has pinned.
        await axios.post('/api/hub/saved-searches', { name: search.name, criteria: search.criteria, pin: pinWanted });

        clearGuestSearchIfStill(search);
    } catch (error) {
        // A 422 is the account's saved-search limit (or criteria the server
        // won't take): retrying on every page would only fail again.
        if (error.response?.status === 422) clearGuestSearchIfStill(search);
        console.error('[saved-searches] failed to carry over guest search', error);
    }
}
