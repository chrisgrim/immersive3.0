/**
 * Specs for pingPage() in composables/useAnalytics.js: one load ping per
 * page view, only once the document is ready and has been visible, carrying
 * whether the browser reports being automated.
 */
import { pingPage } from '@/composables/useAnalytics';

let visibility = 'visible';
let readyState = 'interactive';

beforeEach(() => {
    visibility = 'visible';
    readyState = 'interactive';
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => visibility });
    Object.defineProperty(document, 'readyState', { configurable: true, get: () => readyState });
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

test('a ready, visible page pings once with its view id, without waiting for load', () => {
    pingPage('pingONCE1234');
    show();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    document.dispatchEvent(new Event('DOMContentLoaded'));

    expect(pingsFor('pingONCE1234')).toEqual([{ view_id: 'pingONCE1234', wd: '0' }]);
});

test('a page still being parsed waits for DOM ready', () => {
    readyState = 'loading';
    pingPage('pingLOAD1234');
    expect(pingsFor('pingLOAD1234')).toHaveLength(0);

    readyState = 'interactive';
    document.dispatchEvent(new Event('DOMContentLoaded'));
    expect(pingsFor('pingLOAD1234')).toHaveLength(1);
});

test('a background tab pings only once it is shown', () => {
    visibility = 'hidden';
    pingPage('pingHIDE1234');
    document.dispatchEvent(new Event('DOMContentLoaded'));
    expect(pingsFor('pingHIDE1234')).toHaveLength(0);

    show();
    show();
    expect(pingsFor('pingHIDE1234')).toHaveLength(1);
});

test('a prerendered page that is never shown sends nothing', () => {
    Object.defineProperty(document, 'prerendering', { configurable: true, get: () => true });
    pingPage('pingPRE12345');
    document.dispatchEvent(new Event('DOMContentLoaded'));
    expect(pingsFor('pingPRE12345')).toHaveLength(0);

    delete document.prerendering;
    document.dispatchEvent(new Event('prerenderingchange'));
    expect(pingsFor('pingPRE12345')).toHaveLength(1);
});

test('an automated browser says so', () => {
    Object.defineProperty(navigator, 'webdriver', { configurable: true, value: true });
    pingPage('pingBOT12345');

    expect(pingsFor('pingBOT12345')).toEqual([{ view_id: 'pingBOT12345', wd: '1' }]);
});

test('no view id, no ping', () => {
    pingPage(null);
    document.dispatchEvent(new Event('DOMContentLoaded'));

    expect(navigator.sendBeacon).not.toHaveBeenCalled();
});
