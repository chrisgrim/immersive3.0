/**
 * Our JavaScript only ever runs from the built bundle (/build/assets/*.js):
 * the page itself carries data (window.Laravel), never functions. An error
 * whose every stack frame lies outside the bundle was thrown by code someone
 * else put into the page: an in-app browser, a translator, a reader mode, an
 * extension. Nothing to fix on our side, so it is not reported.
 *
 * Examples: the Google app on iOS (its translate script recursing until
 * "Maximum call stack size exceeded", frames on the page URL itself,
 * EI-VUE-1C), Brave/Firefox reader mode (EI-VUE-1A), Meta's in-app browser
 * (EI-VUE-S), an app's WebViewJavascriptBridge (EI-VUE-1D). Errors with no frames at all are kept: they say nothing either
 * way.
 */
const OUR_CODE = /\/build\/assets\//;

export function isFromInjectedScript(event) {
    const frames = (event?.exception?.values || []).flatMap((value) => value?.stacktrace?.frames || []);

    return frames.length > 0 && frames.every((frame) => !OUR_CODE.test(frame?.filename || ''));
}
