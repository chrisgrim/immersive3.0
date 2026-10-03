/**
 * Specs for watchPage() in composables/useAnalytics.js: a beacon with the
 * running visible time each time the page is hidden, only after 5 visible
 * seconds and only when it grew.
 */
import { watchPage } from '@/composables/useAnalytics';

let visibility = 'visible';

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-03T12:00:00Z'));
    visibility = 'visible';
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => visibility });
    navigator.sendBeacon = vi.fn(() => true);
});

afterEach(() => vi.useRealTimers());

const hide = () => {
    visibility = 'hidden';
    document.dispatchEvent(new Event('visibilitychange'));
};

test('a page looked at for 8 seconds sends one beacon with its view id and seconds', () => {
    watchPage('abcDEF123456');
    vi.advanceTimersByTime(8000);
    hide();
    window.dispatchEvent(new Event('pagehide'));

    expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);
    const [url, body] = navigator.sendBeacon.mock.calls[0];
    expect(url).toBe('/api/analytics/page-leave');
    expect(Object.fromEntries(body)).toMatchObject({ view_id: 'abcDEF123456', seconds: '8' });
});

test('a page left within 5 seconds sends nothing', () => {
    watchPage('abcDEF123456');
    vi.advanceTimersByTime(3000);
    hide();

    expect(navigator.sendBeacon).not.toHaveBeenCalled();
});

test('no view id, no watching', () => {
    watchPage(null);
    vi.advanceTimersByTime(10000);
    hide();

    expect(navigator.sendBeacon).not.toHaveBeenCalled();
});

const show = () => {
    visibility = 'visible';
    document.dispatchEvent(new Event('visibilitychange'));
};

test('time after coming back to the tab is added, and sent again as a running total', () => {
    watchPage('backAGAIN123');
    vi.advanceTimersByTime(10000);
    hide();
    vi.advanceTimersByTime(60000); // away: not counted
    show();
    vi.advanceTimersByTime(50000);
    hide();
    window.dispatchEvent(new Event('pagehide'));

    // Listeners from earlier tests' pages are still attached: only this page's.
    const mine = navigator.sendBeacon.mock.calls.map(([, body]) => Object.fromEntries(body)).filter((b) => b.view_id === 'backAGAIN123');
    expect(mine.map((b) => b.seconds)).toEqual(['10', '60']);
});

test('a tab left open for more than a day sends nothing more', () => {
    watchPage('leftOPEN1234');
    hide();
    vi.advanceTimersByTime(24 * 60 * 60 * 1000);
    show();
    vi.advanceTimersByTime(30000);
    hide();

    expect(navigator.sendBeacon.mock.calls.filter(([, body]) => body.get('view_id') === 'leftOPEN1234')).toHaveLength(0);
});
