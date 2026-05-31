import { afterEach, describe, expect, it, vi } from 'vitest';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';

/**
 * ClickOutsideDirective is a Vue custom directive (v-click-outside) used to
 * close dropdowns/menus. On beforeMount it attaches a document-level "click"
 * listener that fires binding.value(event) only when the click target is
 * neither the bound element nor a descendant of it; on unmounted it detaches
 * that exact listener.
 *
 * The directive stashes the handler on el.clickOutsideEvent, so we exercise it
 * by invoking the hooks directly with a real DOM element + a fake binding and
 * dispatching genuine click events through the document, which is the same path
 * Vue takes at runtime.
 */

describe('ClickOutsideDirective', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('exposes beforeMount and unmounted hooks', () => {
        expect(typeof ClickOutsideDirective.beforeMount).toBe('function');
        expect(typeof ClickOutsideDirective.unmounted).toBe('function');
    });

    it('registers a document click listener on beforeMount and stores the handler on the el', () => {
        const addSpy = vi.spyOn(document, 'addEventListener');
        const el = document.createElement('div');
        const binding = { value: vi.fn() };

        ClickOutsideDirective.beforeMount(el, binding);

        expect(typeof el.clickOutsideEvent).toBe('function');
        expect(addSpy).toHaveBeenCalledWith('click', el.clickOutsideEvent);
    });

    it('invokes the binding function for a click OUTSIDE the element', () => {
        const el = document.createElement('div');
        const outside = document.createElement('button');
        document.body.appendChild(el);
        document.body.appendChild(outside);

        const fn = vi.fn();
        ClickOutsideDirective.beforeMount(el, { value: fn });

        outside.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(fn).toHaveBeenCalledTimes(1);
        // The handler forwards the original event object.
        expect(fn.mock.calls[0][0]).toBeInstanceOf(Event);

        ClickOutsideDirective.unmounted(el);
    });

    it('does NOT invoke the binding function for a click ON the element itself', () => {
        const el = document.createElement('div');
        document.body.appendChild(el);

        const fn = vi.fn();
        ClickOutsideDirective.beforeMount(el, { value: fn });

        el.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(fn).not.toHaveBeenCalled();

        ClickOutsideDirective.unmounted(el);
    });

    it('does NOT invoke the binding function for a click on a DESCENDANT of the element', () => {
        const el = document.createElement('div');
        const child = document.createElement('span');
        el.appendChild(child);
        document.body.appendChild(el);

        const fn = vi.fn();
        ClickOutsideDirective.beforeMount(el, { value: fn });

        child.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(fn).not.toHaveBeenCalled();

        ClickOutsideDirective.unmounted(el);
    });

    it('stops responding to outside clicks after unmounted removes the listener', () => {
        const removeSpy = vi.spyOn(document, 'removeEventListener');
        const el = document.createElement('div');
        const outside = document.createElement('button');
        document.body.appendChild(el);
        document.body.appendChild(outside);

        const fn = vi.fn();
        ClickOutsideDirective.beforeMount(el, { value: fn });
        const handler = el.clickOutsideEvent;

        ClickOutsideDirective.unmounted(el);

        expect(removeSpy).toHaveBeenCalledWith('click', handler);

        // No further invocations once detached.
        outside.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(fn).not.toHaveBeenCalled();
    });

    it('only fires for the element whose hook was invoked (per-element isolation)', () => {
        const elA = document.createElement('div');
        const elB = document.createElement('div');
        document.body.appendChild(elA);
        document.body.appendChild(elB);

        const fnA = vi.fn();
        const fnB = vi.fn();
        ClickOutsideDirective.beforeMount(elA, { value: fnA });
        ClickOutsideDirective.beforeMount(elB, { value: fnB });

        // Clicking elB is "outside" for elA, and "inside" for elB.
        elB.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(fnA).toHaveBeenCalledTimes(1);
        expect(fnB).not.toHaveBeenCalled();

        ClickOutsideDirective.unmounted(elA);
        ClickOutsideDirective.unmounted(elB);
    });
});
