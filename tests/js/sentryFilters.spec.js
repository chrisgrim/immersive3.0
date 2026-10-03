/**
 * Specs for resources/js/sentryFilters.js: an error is dropped only when no
 * stack frame lies in our bundle.
 */
import { isFromInjectedScript } from '@/sentryFilters';

const frames = (...filenames) => ({ exception: { values: [{ stacktrace: { frames: filenames.map((filename) => ({ filename })) } }] } });

test('the Google app translate loop (frames only on the page URL) is dropped', () => {
    const event = frames(
        'https://everythingimmersive.com/events/bluey-x-camp',
        'https://everythingimmersive.com/events/bluey-x-camp',
    );

    expect(isFromInjectedScript(event)).toBe(true);
});

test('an error with any frame in our bundle is kept', () => {
    const event = frames(
        'https://everythingimmersive.com/events/bluey-x-camp',
        'https://everythingimmersive.com/build/assets/app-B5xk0Sgv.js',
    );

    expect(isFromInjectedScript(event)).toBe(false);
});

test('an error without a stack trace is kept', () => {
    expect(isFromInjectedScript({ message: 'boom' })).toBe(false);
    expect(isFromInjectedScript(frames())).toBe(false);
});
