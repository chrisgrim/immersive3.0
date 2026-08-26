/**
 * Specs for composables/useRemoteTypeSearch.js — the At Home type picker's
 * data layer, shared by the desktop and mobile panels.
 *
 * The stale-response guard is the reason this is tested here rather than only
 * through a component: it is the piece both panels now depend on identically,
 * and it protects against a bug that only appears under a specific request
 * ordering no manual pass would reliably reproduce.
 */
import { vi } from 'vitest';
import axios from 'axios';
import { useRemoteTypeSearch, ALL_TYPES_OPTION } from '@/composables/useRemoteTypeSearch';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

const TYPES = Array.from({ length: 10 }, (_, i) => ({ id: i + 1, name: `Type ${i + 1}`, slug: `t${i + 1}` }));

beforeEach(() => axios.get.mockReset());

describe('useRemoteTypeSearch', () => {
    it('offers "All At Home" with a null slug, so no platform filter is applied', async () => {
        axios.get.mockResolvedValueOnce({ data: TYPES });
        const { types, fetchTypes } = useRemoteTypeSearch({ limit: 6 });
        await fetchTypes('');

        expect(types.value[0]).toEqual(ALL_TYPES_OPTION);
        expect(ALL_TYPES_OPTION.slug).toBeNull();
    });

    it('gives up one slot per recent search from the shared budget', async () => {
        axios.get.mockResolvedValueOnce({ data: TYPES });
        const { types, fetchTypes } = useRemoteTypeSearch({ limit: 6, recentCount: () => 2 });
        await fetchTypes('');

        // "All At Home" + 4 fetched = the 6-slot budget minus 2 recents.
        expect(types.value).toHaveLength(5);
    });

    it('never slices a typed query, which is shown on its own', async () => {
        axios.get.mockResolvedValueOnce({ data: TYPES });
        const { types, fetchTypes } = useRemoteTypeSearch({ limit: 6, recentCount: () => 5 });
        await fetchTypes('zoom');

        expect(types.value).toHaveLength(10);
        expect(types.value).not.toContainEqual(ALL_TYPES_OPTION);
    });

    it('ignores a slow earlier response that lands after a newer one', async () => {
        let resolveSlow;
        axios.get.mockImplementationOnce(() => new Promise((r) => { resolveSlow = r; }));
        const { types, fetchTypes } = useRemoteTypeSearch({ limit: 6 });
        const slow = fetchTypes('z');

        axios.get.mockResolvedValueOnce({ data: [{ id: 99, name: 'Fresh', slug: 'fresh' }] });
        await fetchTypes('zo');

        resolveSlow({ data: [{ id: 1, name: 'Stale', slug: 'stale' }] });
        await slow;

        expect(types.value.map((t) => t.name)).toEqual(['Fresh']);
    });

    it('keeps "All At Home" usable when the request fails', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});
        axios.get.mockRejectedValueOnce(new Error('network'));
        const { types, fetchTypes } = useRemoteTypeSearch({ limit: 6 });
        await fetchTypes('');

        expect(types.value).toEqual([ALL_TYPES_OPTION]);
    });

    it('drops a response that arrives after the panel unmounted', async () => {
        let resolveLate;
        axios.get.mockImplementationOnce(() => new Promise((r) => { resolveLate = r; }));
        const { types, fetchTypes, invalidateInFlight } = useRemoteTypeSearch({ limit: 6 });
        const pending = fetchTypes('');

        invalidateInFlight(); // onUnmounted
        resolveLate({ data: TYPES });
        await pending;

        expect(types.value).toEqual([]);
    });
});
