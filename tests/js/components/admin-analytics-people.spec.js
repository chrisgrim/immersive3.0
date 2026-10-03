import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import Analytics from '@/PageComponents/Admin/Management/Analytics.vue';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

/**
 * The admin Insights page shows everyone the server saw beside the
 * browser-confirmed and engaged people, and says when browser confirmation
 * has not been switched on instead of showing zeros.
 */
const report = (confirmed) => ({
    days: 30,
    since: '2026-09-04T00:00:00Z',
    totals: { people: { total: 50, visitors: 40, confirmed_visitors: confirmed, engaged_visitors: 10 } },
    totals_previous: { people: { total: 30, visitors: 20, confirmed_visitors: confirmed, engaged_visitors: 5 } },
    zero_result_total: 0,
    daily: [],
    searches: [],
    at_home_searches: [],
    zero_result_searches: [],
    events: [],
    view_sources: { by_kind: {}, outside_sites: {} },
    search_clicks: { searches: 0, searches_with_a_click: 0, click_rate: null, by_position: {} },
    countries: { US: { visitors: 30, confirmed } },
    bots: { all_rows: 0, flagged: 0, share: null, crawler: 0, no_user_agent: 0, over_daily_cap: 0, datacenter: 0, odd_headers: 0, automation: 0 },
});

const mountWith = async (data) => {
    axios.get.mockResolvedValue({ data });
    const w = mount(Analytics);
    await flushPromises();
    return w;
};

afterEach(() => vi.clearAllMocks());

it('shows browser-confirmed and engaged people next to all visits, and confirmed visits by country', async () => {
    const w = await mountWith(report(25));

    expect(w.text()).toContain('People (browser confirmed)');
    expect(w.text()).toContain('25');
    expect(w.text()).toContain('Engaged');
    expect(w.text()).toContain('Confirmed');
    expect(w.text()).not.toContain('Starts once browser confirmation is switched on');
});

it('says confirmation has not started rather than showing zeros', async () => {
    const w = await mountWith(report(null));

    expect(w.text()).toContain('Starts once browser confirmation is switched on');
    expect(w.text()).toContain('Confirmed starts once browser confirmation is switched on.');
});
