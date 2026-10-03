/**
 * Specs for PageComponents/Admin/Management/AnalyticsGoogle.vue (the
 * Insights page's "From Google" block) and analyticsChart.js.
 */
import { mount } from '@vue/test-utils';
import AnalyticsGoogle from '@/PageComponents/Admin/Management/AnalyticsGoogle.vue';
import { lineChart, niceStep } from '@/PageComponents/Admin/Management/analyticsChart.js';

const data = (overrides = {}) => ({
    configured: true,
    has_data: true,
    days: 7,
    period: { from: '2026-09-24', to: '2026-09-30', days: 7 },
    note: 'Google reports 2 to 3 days late.',
    totals: { clicks: 120, impressions: 4000, ctr: 0.03, position: 8.4 },
    previous: { clicks: 100, impressions: 5000, ctr: 0.02, position: 9.4 },
    daily: [
        { day: '2026-09-29', clicks: 50, impressions: 2000, ctr: 0.025, position: 8 },
        { day: '2026-09-30', clicks: 70, impressions: 2000, ctr: 0.035, position: 9 },
    ],
    queries: [{ query: '<b>sleep no more</b>', clicks: 30, impressions: 300, ctr: 0.1, position: 2.5 }],
    pages: [
        { page: '/events/the-show', kind: 'event', id: 1, name: 'The Show', thumb: null, clicks: 40, impressions: 400, ctr: 0.1, position: 3 },
        { page: '/events/old-name', kind: 'gone', id: null, name: null, thumb: null, clicks: 3, impressions: 30, ctr: 0.1, position: 4 },
        { page: 'javascript:alert(1)', kind: 'page', id: null, name: null, thumb: null, clicks: 1, impressions: 10, ctr: 0.1, position: 5 },
    ],
    ...overrides,
});

describe('AnalyticsGoogle.vue', () => {
    it('shows the totals with their change, position improving when it goes down', () => {
        const wrapper = mount(AnalyticsGoogle, { props: { data: data() } });
        const text = wrapper.text();

        expect(text).toContain('From Google');
        expect(text).toContain('Clicks from Google');
        expect(text).toContain('↑ 20%');
        expect(text).toContain('8.4');
        // Position 9.4 to 8.4: down by 1, which is good (green).
        const position = wrapper.findAll('.card').find((card) => card.text().includes('Average Position'));
        expect(position.text()).toContain('↓ 1');
        expect(position.find('.text-\\[\\#008A05\\]').exists()).toBe(true);
    });

    it('lists searches as text and links only real addresses', () => {
        const wrapper = mount(AnalyticsGoogle, { props: { data: data() } });

        expect(wrapper.html()).not.toContain('<b>sleep');
        expect(wrapper.text()).toContain('<b>sleep no more</b>');
        const links = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(links).toEqual(['/events/the-show', '/events/old-name']);
        expect(wrapper.text()).toContain('Not a current event page');
        expect(wrapper.text()).toContain('The Show');
    });

    it('opens the section pages from their titles', async () => {
        const wrapper = mount(AnalyticsGoogle, { props: { data: data() } });
        const buttons = wrapper.findAll('button.section-link');

        await buttons[0].trigger('click');
        await buttons[1].trigger('click');

        expect(wrapper.emitted('open')).toEqual([['google_queries'], ['google_pages']]);
    });

    it('shows no change when there is no earlier data to compare with', () => {
        const wrapper = mount(AnalyticsGoogle, { props: { data: data({ previous: null, period: { from: '2026-09-24', to: '2026-09-30', days: 7, data_since: '2026-09-24' } }) } });

        expect(wrapper.text()).toContain('No earlier data (imported since Sep 24)');
        expect(wrapper.text()).not.toContain('↑');
        expect(wrapper.text()).not.toContain('↓');
    });

    it('says when nothing has been imported yet', () => {
        const wrapper = mount(AnalyticsGoogle, { props: { data: { configured: true, has_data: false } } });

        expect(wrapper.text()).toContain('No data yet');
        expect(wrapper.find('svg[role="img"]').exists()).toBe(false);
    });
});

describe('analyticsChart.js', () => {
    it('scales the axis to a round step above the highest value', () => {
        expect(niceStep(33)).toBe(50);
        const chart = lineChart([{ day: '2026-09-01', v: 0 }, { day: '2026-09-02', v: 90 }], (point) => point.v);

        expect(chart.yTicks.map((tick) => tick.value)).toEqual([0, 50, 100]);
        expect(chart.coords).toHaveLength(2);
        expect(chart.line.startsWith('M')).toBe(true);
    });
});
