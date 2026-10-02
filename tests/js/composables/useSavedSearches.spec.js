/**
 * Specs for composables/useSavedSearches.js
 *
 * saveSearch() imports `axios` directly (`import axios from 'axios'`), not
 * window.axios — mock the 'axios' module locally (same pattern as
 * tests/js/stores/SearchStore.spec.js) rather than relying on tests/js/setup.js's
 * window.axios mock, which this composable never touches.
 *
 * Covers the composable's own documented contract:
 *  - Posts to /api/hub/saved-searches with the exact {name, criteria} shape.
 *  - Guests (no window.Laravel.user.id) never call axios.post; their one
 *    search is kept in this browser instead, shown in the dropdown, and
 *    carried into their account when they log in.
 *  - "Fire-and-forget: never throws" — an axios rejection resolves rather
 *    than rejecting/throwing, and is only logged.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('axios', () => {
    const post = vi.fn(() => Promise.resolve({ data: {} }));
    const patch = vi.fn(() => Promise.resolve({ data: {} }));
    return {
        default: { post, patch },
    };
});

import axios from 'axios';
import {
    saveSearch,
    readGuestSearch,
    guestSearchRow,
    pinGuestSearchAfterLogin,
    carryOverGuestSearch,
} from '@/composables/useSavedSearches';

beforeEach(() => {
    axios.post.mockReset();
    axios.post.mockResolvedValue({ data: {} });
    axios.patch.mockReset();
    window.localStorage.clear();
    window.Laravel = { user: { id: 1, name: 'Test', email: 't@e.com', type: 'u' } };
});

describe('useSavedSearches', () => {
    describe('saveSearch', () => {
        it('posts to /api/hub/saved-searches with the given name and criteria', async () => {
            const criteria = { city: 'Portland, OR', searchType: 'inPerson' };
            await saveSearch('Portland search', criteria);

            expect(axios.post).toHaveBeenCalledTimes(1);
            expect(axios.post).toHaveBeenCalledWith('/api/hub/saved-searches', {
                name: 'Portland search',
                criteria,
            });
        });

        it('does not call axios.post when there is no logged-in user (guest)', async () => {
            window.Laravel = {};
            await saveSearch('Guest search', { city: 'Denver, CO' });
            expect(axios.post).not.toHaveBeenCalled();
        });

        it('does not call axios.post when window.Laravel.user has no id', async () => {
            window.Laravel = { user: {} };
            await saveSearch('Half-formed user', { city: 'Denver, CO' });
            expect(axios.post).not.toHaveBeenCalled();
        });

        it('resolves without throwing when the request rejects', async () => {
            axios.post.mockRejectedValue(new Error('network down'));

            await expect(saveSearch('Boom search', { city: 'Chicago, IL' })).resolves.toBeUndefined();
        });

        it('logs to console.error when the request rejects, without rethrowing', async () => {
            const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
            const boom = new Error('network down');
            axios.post.mockRejectedValue(boom);

            await saveSearch('Boom search', { city: 'Chicago, IL' });

            expect(consoleSpy).toHaveBeenCalledWith('[saved-searches] failed to auto-save', boom);
            consoleSpy.mockRestore();
        });
    });
});

const nyc = { city: 'New York', lat: 40.7, lng: -74, searchType: 'inPerson', live: false };
const sf = { city: 'San Francisco', lat: 37.7, lng: -122.4, searchType: 'inPerson', live: false };
const asGuest = () => { window.Laravel = {}; };
const logIn = () => { window.Laravel = { user: { id: 7 } }; };

describe('guest search (one slot, in this browser)', () => {
    beforeEach(asGuest);

    it('keeps a guest\'s search in this browser', async () => {
        await saveSearch('New York', nyc);

        expect(readGuestSearch()).toMatchObject({ name: 'New York', criteria: nyc, pinRequestedAt: null });
    });

    it('keeps only the last one: a new search replaces it', async () => {
        await saveSearch('New York', nyc);
        await saveSearch('San Francisco', sf);

        expect(readGuestSearch()).toMatchObject({ name: 'San Francisco', criteria: sf });
    });

    it('leaves the browser alone for a logged-in user', async () => {
        logIn();
        await saveSearch('New York', nyc);

        expect(readGuestSearch()).toBeNull();
    });

    it('still runs the search when the browser refuses storage', async () => {
        window.localStorage.setItem.mockImplementationOnce(() => { throw new Error('blocked'); });

        await expect(saveSearch('New York', nyc)).resolves.toBeUndefined();
        expect(window.localStorage.setItem).toHaveBeenCalled();
        expect(readGuestSearch()).toBeNull();
    });

    describe('guestSearchRow', () => {
        it('is nothing until the guest has searched', () => {
            expect(guestSearchRow()).toBeNull();
        });

        it('is shaped like an account row, replayed through the server', async () => {
            await saveSearch('New York', nyc);
            const row = guestSearchRow();

            expect(row).toMatchObject({ id: 'guest', guest: true, name: 'New York', criteria: nyc, pinned: false });
            expect(row.url.startsWith('/index/search/replay?criteria=')).toBe(true);
            expect(JSON.parse(decodeURIComponent(row.url.split('criteria=')[1]))).toEqual(nyc);
        });

        it('ignores anything unreadable in storage', () => {
            window.localStorage.setItem('ei_guest_search', '{not json');

            expect(guestSearchRow()).toBeNull();
        });
    });

    it('pinning remembers when they asked and opens the login modal', async () => {
        await saveSearch('New York', nyc);
        const opened = vi.fn();
        window.addEventListener('open-login-modal', opened);

        pinGuestSearchAfterLogin();

        expect(Date.parse(readGuestSearch().pinRequestedAt)).toBeGreaterThan(Date.now() - 5000);
        expect(opened).toHaveBeenCalledOnce();
        window.removeEventListener('open-login-modal', opened);
    });

    it('pins the row they clicked, even if another tab has searched since', async () => {
        await saveSearch('New York', nyc);
        const clicked = guestSearchRow();
        await saveSearch('San Francisco', sf); // another tab

        pinGuestSearchAfterLogin(clicked);

        expect(readGuestSearch()).toMatchObject({ name: 'New York', criteria: nyc });
        expect(readGuestSearch().pinRequestedAt).not.toBeNull();
    });

    it('pinning on a phone goes to the login page, since the modal is desktop-only', async () => {
        await saveSearch('New York', nyc);
        window.Laravel = { isMobile: true };
        const opened = vi.fn();
        window.addEventListener('open-login-modal', opened);
        const location = window.location;
        delete window.location;
        window.location = { href: '/index/search' };

        pinGuestSearchAfterLogin();

        expect(window.location.href).toBe('/login');
        expect(opened).not.toHaveBeenCalled();
        expect(readGuestSearch().pinRequestedAt).not.toBeNull();
        window.location = location;
        window.removeEventListener('open-login-modal', opened);
    });

    describe('carryOverGuestSearch', () => {
        it('does nothing while they are still a guest', async () => {
            await saveSearch('New York', nyc);
            await carryOverGuestSearch();

            expect(axios.post).not.toHaveBeenCalled();
            expect(readGuestSearch()).not.toBeNull();
        });

        it('moves the search into the account and clears the browser copy', async () => {
            await saveSearch('New York', nyc);
            logIn();
            axios.post.mockResolvedValueOnce({ data: { search: { id: 12, pinned: false } } });

            await carryOverGuestSearch();

            expect(axios.post).toHaveBeenCalledWith('/api/hub/saved-searches', { name: 'New York', criteria: nyc, pin: false });
            expect(axios.patch).not.toHaveBeenCalled();
            expect(readGuestSearch()).toBeNull();
        });

        it('pins it when they asked to, in the same request as the save', async () => {
            await saveSearch('New York', nyc);
            pinGuestSearchAfterLogin();
            logIn();
            axios.post.mockResolvedValueOnce({ data: { search: { id: 12, pinned: true } } });

            await carryOverGuestSearch();

            expect(axios.post).toHaveBeenCalledWith('/api/hub/saved-searches', { name: 'New York', criteria: nyc, pin: true });
            expect(axios.patch).not.toHaveBeenCalled();
            expect(readGuestSearch()).toBeNull();
        });

        it('leaves a newer search another tab stored while this one was carrying over', async () => {
            await saveSearch('New York', nyc);
            logIn();
            axios.post.mockImplementationOnce(async () => {
                window.Laravel = {};
                await new Promise((r) => setTimeout(r, 2));
                await saveSearch('San Francisco', sf); // the other, still-guest tab
                window.Laravel = { user: { id: 7 } };
                return { data: { search: { id: 12, pinned: false } } };
            });

            await carryOverGuestSearch();

            expect(readGuestSearch()).toMatchObject({ name: 'San Francisco' });
        });

        it('does not trust a time ahead of now: a search stored with the clock a day fast is dropped', async () => {
            const ahead = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString();
            window.localStorage.setItem('ei_guest_search', JSON.stringify({ name: 'New York', criteria: nyc, updated_at: ahead, pinRequestedAt: ahead }));
            logIn();

            await carryOverGuestSearch();

            expect(axios.post).not.toHaveBeenCalled();
            expect(readGuestSearch()).toBeNull();
        });

        const ago = (ms) => new Date(Date.now() - ms).toISOString();
        const HOUR = 60 * 60 * 1000;

        it('drops a guest search older than an hour instead of handing it to whoever logs in', async () => {
            window.localStorage.setItem('ei_guest_search', JSON.stringify({ name: 'New York', criteria: nyc, updated_at: ago(2 * HOUR), pinRequestedAt: null }));
            logIn();

            await carryOverGuestSearch();

            expect(axios.post).not.toHaveBeenCalled();
            expect(readGuestSearch()).toBeNull();
        });

        it('drops an old pin request, one abandoned when the modal was closed', async () => {
            window.localStorage.setItem('ei_guest_search', JSON.stringify({ name: 'New York', criteria: nyc, updated_at: ago(3 * HOUR), pinRequestedAt: ago(2 * HOUR) }));
            logIn();

            await carryOverGuestSearch();

            expect(axios.post).not.toHaveBeenCalled();
            expect(axios.patch).not.toHaveBeenCalled();
            expect(readGuestSearch()).toBeNull();
        });

        it('still pins an older search they have just asked to pin', async () => {
            window.localStorage.setItem('ei_guest_search', JSON.stringify({ name: 'New York', criteria: nyc, updated_at: ago(5 * HOUR), pinRequestedAt: ago(60 * 1000) }));
            logIn();
            axios.post.mockResolvedValueOnce({ data: { search: { id: 12, pinned: false } } });

            await carryOverGuestSearch();

            expect(axios.post).toHaveBeenCalledWith('/api/hub/saved-searches', { name: 'New York', criteria: nyc, pin: true });
        });

        it('keeps the browser copy to try again when the request fails', async () => {
            const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
            await saveSearch('New York', nyc);
            logIn();
            axios.post.mockRejectedValueOnce(new Error('network'));

            await carryOverGuestSearch();

            expect(readGuestSearch()).not.toBeNull();
            consoleSpy.mockRestore();
        });

        it('lets it go when the account refuses it (e.g. at its saved-search limit)', async () => {
            const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
            await saveSearch('New York', nyc);
            logIn();
            axios.post.mockRejectedValueOnce({ response: { status: 422 } });

            await carryOverGuestSearch();

            expect(readGuestSearch()).toBeNull();
            consoleSpy.mockRestore();
        });
    });
});
