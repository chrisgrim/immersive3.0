/**
 * Specs for pingPage() in composables/useAnalytics.js: one load ping per
 * page view, as soon as the script runs and the page is visible, carrying
 * whether the browser reports being automated.
 */
import { pingPage } from '@/composables/useAnalytics';

let visibility = 'visible';

beforeEach(() => {
    visibility = 'visible';
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => visibility });
    Object.defineProperty(navigator, 'webdriver', { configurable: true, value: false });
    navigator.sendBeacon = vi.fn(() => true);
});

// Listeners from earlier tests' pages stay attached: look at this page's only.
const pingsFor = (viewId) => navigator.sendBeacon.mock.calls
    .filter(([url, body]) => url === '/api/analytics/page-ping' && body.get('view_id') === viewId)
    .map(([, body]) => Object.fromEntries(body));

const show = () => {
    visibility = 'visible';
    document.dispatchEvent(new Event('visibilitychange'));
};

test('a visible page pings at once, and only once', () => {
    pingPage('pingONCE1234');
    show();
    window.dispatchEvent(new Event('pagehide'));

    expect(pingsFor('pingONCE1234')).toEqual([{ view_id: 'pingONCE1234', wd: '0' }]);
});

test('a background tab pings only once it is shown', () => {
    visibility = 'hidden';
    pingPage('pingHIDE1234');
    window.dispatchEvent(new Event('pagehide'));
    expect(pingsFor('pingHIDE1234')).toHaveLength(0);

    show();
    show();
    expect(pingsFor('pingHIDE1234')).toHaveLength(1);
});

test('a prerendered page that is never shown sends nothing', () => {
    Object.defineProperty(document, 'prerendering', { configurable: true, get: () => true });
    pingPage('pingPRE12345');
    expect(pingsFor('pingPRE12345')).toHaveLength(0);

    delete document.prerendering;
    document.dispatchEvent(new Event('prerenderingchange'));
    expect(pingsFor('pingPRE12345')).toHaveLength(1);
});

test('leaving a shown page that has not pinged yet pings then', () => {
    Object.defineProperty(document, 'prerendering', { configurable: true, get: () => true });
    pingPage('pingLEAVE123');
    delete document.prerendering;
    // Activated without a prerenderingchange reaching us: the pagehide net.
    window.dispatchEvent(new Event('pagehide'));

    expect(pingsFor('pingLEAVE123')).toHaveLength(1);
});

test('an automated browser says so', () => {
    Object.defineProperty(navigator, 'webdriver', { configurable: true, value: true });
    pingPage('pingBOT12345');

    expect(pingsFor('pingBOT12345')).toEqual([{ view_id: 'pingBOT12345', wd: '1' }]);
});

test('no view id, no ping', () => {
    pingPage(null);
    window.dispatchEvent(new Event('pagehide'));

    expect(navigator.sendBeacon).not.toHaveBeenCalled();
});
