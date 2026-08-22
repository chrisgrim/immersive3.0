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
 *  - Guests (no window.Laravel.user.id) are a silent no-op — axios.post is
 *    never called.
 *  - "Fire-and-forget: never throws" — an axios rejection resolves rather
 *    than rejecting/throwing, and is only logged.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('axios', () => {
    const post = vi.fn(() => Promise.resolve({ data: {} }));
    return {
        default: { post },
    };
});

import axios from 'axios';
import { saveSearch } from '@/composables/useSavedSearches';

beforeEach(() => {
    axios.post.mockReset();
    axios.post.mockResolvedValue({ data: {} });
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
