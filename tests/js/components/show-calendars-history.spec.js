/**
 * The event page calendars hand VueDatePicker only the show days near the
 * months on screen (datesNearMonths), including days from the compact show
 * history. A permanent artwork open since 1977 has ~18,000 days, and giving
 * the picker all of them made every month change take seconds.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import ShowCalendarMobile from '@/PageComponents/EventShow/show-calendar-mobile.vue';
import ShowPurchase from '@/PageComponents/EventShow/show-purchase.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: {} })), post: vi.fn(() => Promise.resolve({ data: {} })) } }));

// Stands in for VueDatePicker: records what it was given, emits on demand.
const PickerStub = defineComponent({
    name: 'VueDatePicker',
    props: { modelValue: { type: [Array, Object, Date, null], default: null } },
    emits: ['update-month-year', 'update:model-value'],
    setup(props) {
        return () => h('div', { class: 'picker-stub' }, String(props.modelValue?.length ?? 0));
    },
});

const event = () => ({
    id: 1,
    showtype: 'o',
    timezone: 'America/New_York',
    shows: [{ date: '2026-10-01 16:00:00' }, { date: '2026-10-02 16:00:00' }],
    past_show_dates: [],
    show_summary: { first_date: '1977-10-01 16:00:00', last_date: '2026-10-02 16:00:00', total: 18000, upcoming_total: 2, curtain_times: true },
    show_history: { through: '2025-09-27', runs: [{ from: '1977-10-01', to: '2025-09-27', days: [0, 1, 2, 3, 4, 5, 6] }] },
    tickets: [],
    organizer: { website: 'https://example.com' },
});

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

describe('event page calendars with a long show history', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 8, 28, 12));
    });
    afterEach(() => vi.useRealTimers());

    it('the phone calendar gets only nearby days, and history days when paged back', async () => {
        const wrapper = mount(ShowCalendarMobile, { props: { event: event() }, global: { stubs: { VueDatePicker: PickerStub } } });
        await flushPromises();

        const picker = wrapper.findComponent(PickerStub);
        const now = picker.props('modelValue');
        expect(now.length).toBeGreaterThan(0);
        expect(now.length).toBeLessThan(200);
        expect(now.map(ymd)).toContain('2026-10-01');

        picker.vm.$emit('update-month-year', { instance: 0, month: 6, year: 2001 });
        await flushPromises();

        const then = picker.props('modelValue').map(ymd);
        expect(then).toContain('2001-07-04');
        expect(then.every((d) => d >= '2001-06-01' && d < '2001-10-01')).toBe(true);
    });

    it('the desktop calendar follows each of its two calendars', async () => {
        const wrapper = mount(ShowPurchase, { props: { event: event() }, global: { stubs: { VueDatePicker: PickerStub, 'vue-similar-events': true }, directives: { 'click-outside': {} } } });
        await flushPromises();
        const toggle = wrapper.findAll('button').find((b) => /remaining/i.test(b.text()));
        await toggle.trigger('click');
        await flushPromises();

        const picker = wrapper.findComponent(PickerStub);
        expect(picker.props('modelValue').length).toBeLessThan(200);

        picker.vm.$emit('update-month-year', { instance: 1, month: 0, year: 1980 });
        // Picking a year names only the year: the month stays.
        picker.vm.$emit('update-month-year', { instance: 0, year: 2026 });
        await flushPromises();

        const days = picker.props('modelValue').map(ymd);
        expect(days).toContain('1980-01-15');
        expect(days).toContain('2026-10-01');
        expect(days.length).toBeLessThan(400);
    });
});
