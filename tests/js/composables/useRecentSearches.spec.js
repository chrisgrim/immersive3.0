/**
 * Specs for composables/useRecentSearches.js — the shared "Recent searches"
 * fetch behind all four nav search panels.
 *
 * Worth testing directly rather than through any one panel: the whole point
 * of extracting it is that desktop and mobile now depend on identical
 * behaviour, so this is the one place a change to it can be caught for both.
 */
import { vi } from 'vitest';
import axios from 'axios';
import { useRecentSearches } from '@/composables/useRecentSearches';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

const searches = (n) => ({ data: { searches: Array.from({ length: n }, (_, i) => ({ id: i + 1, name: `s${i}` })) } });

beforeEach(() => {
    axios.get.mockReset();
    window.Laravel = { user: { id: 1 } };
});

describe('useRecentSearches', () => {
    it('loads the dropdown view, not the full saved-search list', async () => {
        axios.get.mockResolvedValueOnce(searches(2));
        const { fetchRecentSearches } = useRecentSearches({ limit: 6 });
        await fetchRecentSearches();

        expect(axios.get).toHaveBeenCalledWith('/api/hub/saved-searches', { params: { dropdown: 1 } });
    });

    it('caps results at the shared dropdown budget', async () => {
        axios.get.mockResolvedValueOnce(searches(20));
        const { recentSearches, fetchRecentSearches } = useRecentSearches({ limit: 6 });
        await fetchRecentSearches();

        expect(recentSearches.value).toHaveLength(6);
    });

    it('asks for nothing at all when the viewer is a guest', async () => {
        window.Laravel = {};
        const { fetchRecentSearches } = useRecentSearches({ limit: 6 });
        await fetchRecentSearches();

        expect(axios.get).not.toHaveBeenCalled();
    });

    it('notifies the caller so it can re-slice its own list', async () => {
        axios.get.mockResolvedValueOnce(searches(2));
        const onLoaded = vi.fn();
        const { fetchRecentSearches } = useRecentSearches({ limit: 6, onLoaded });
        await fetchRecentSearches();

        expect(onLoaded).toHaveBeenCalledOnce();
    });

    it('leaves the panel usable when the request fails', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});
        axios.get.mockRejectedValueOnce(new Error('network'));
        const onLoaded = vi.fn();
        const { recentSearches, fetchRecentSearches } = useRecentSearches({ limit: 6, onLoaded });

        await expect(fetchRecentSearches()).resolves.toBeUndefined();
        expect(recentSearches.value).toEqual([]);
        expect(onLoaded).not.toHaveBeenCalled();
    });

    describe('once', () => {
        it('fetches a single time for a long-lived desktop panel', async () => {
            axios.get.mockResolvedValue(searches(1));
            const { fetchRecentSearches } = useRecentSearches({ limit: 6, once: true });
            await fetchRecentSearches();
            await fetchRecentSearches();

            expect(axios.get).toHaveBeenCalledTimes(1);
        });

        it('does not retry after a failure, matching the desktop behaviour it replaces', async () => {
            vi.spyOn(console, 'error').mockImplementation(() => {});
            axios.get.mockRejectedValue(new Error('network'));
            const { fetchRecentSearches } = useRecentSearches({ limit: 6, once: true });
            await fetchRecentSearches();
            await fetchRecentSearches();

            expect(axios.get).toHaveBeenCalledTimes(1);
        });

        it('re-fetches each time for a mobile sheet that remounts on open', async () => {
            axios.get.mockResolvedValue(searches(1));
            const { fetchRecentSearches } = useRecentSearches({ limit: 6, once: false });
            await fetchRecentSearches();
            await fetchRecentSearches();

            expect(axios.get).toHaveBeenCalledTimes(2);
        });
    });
});
