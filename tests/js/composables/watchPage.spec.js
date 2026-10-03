/**
 * Specs for watchPage() in composables/useAnalytics.js: one beacon when the
 * page is first hidden, only after 5 visible seconds, never twice.
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
