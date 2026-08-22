import axios from 'axios';

/**
 * Auto-saves the current search as the user's single "recent search" slot —
 * see SaveSearchAction on the backend for the overwrite-in-place behavior
 * this relies on (a user doing dozens of searches never accumulates more
 * than one unpinned row plus whatever they've pinned).
 *
 * Fire-and-forget: never throws, never blocks the actual search. Guests
 * (no window.Laravel.user) are silently skipped — this runs on every
 * search, not from an explicit "save" click, so there's nothing to prompt
 * a login for (unlike favorite-event.vue's login-modal pattern).
 */
export async function saveSearch(name, criteria) {
    if (!window.Laravel?.user?.id) return;

    try {
        await axios.post('/api/hub/saved-searches', { name, criteria });
    } catch (error) {
        console.error('[saved-searches] failed to auto-save', error);
    }
}
