import { mount } from '@vue/test-utils';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import Toast from '@/GlobalComponents/toast-notifications.vue';

/**
 * NOTE ON ASSIGNMENT DEVIATION:
 * The assignment described a toast component that holds a *list* of toasts,
 * auto-dismisses each after its timeout, and has a per-toast close button.
 * The actual `toast-notifications.vue` in this codebase is a simpler,
 * fully-controlled single toast:
 *   - props: { show, message, duration }
 *   - it renders `message` only while `show` is true
 *   - when `show` transitions to true it starts a setTimeout and, after
 *     `duration` ms, emits `update:show` with `false` (parent-controlled v-model)
 * There is no internal close button — dismissal is owned by the parent. These
 * tests therefore exercise the real auto-dismiss timer + conditional render
 * behavior, using fake timers as instructed.
 */
describe('toast-notifications.vue', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('does not render the toast body when show is false', () => {
        const wrapper = mount(Toast, {
            props: { show: false, message: 'Saved' },
        });
        expect(wrapper.text()).not.toContain('Saved');
        expect(wrapper.find('p').exists()).toBe(false);
    });

    it('renders the message when show is true', async () => {
        const wrapper = mount(Toast, {
            props: { show: true, message: 'Saved successfully' },
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.find('p').exists()).toBe(true);
        expect(wrapper.text()).toContain('Saved successfully');
    });

    it('uses the default message when none is provided', async () => {
        const wrapper = mount(Toast, {
            props: { show: true },
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain('Updated successfully');
    });

    it('auto-dismisses by emitting update:show=false after the default duration (3000ms)', async () => {
        const wrapper = mount(Toast, {
            // start hidden, then flip on so the watcher fires
            props: { show: false, message: 'Hi' },
        });

        await wrapper.setProps({ show: true });

        // Before the timer elapses, nothing emitted yet.
        expect(wrapper.emitted('update:show')).toBeFalsy();

        // Advance just under the duration — still nothing.
        vi.advanceTimersByTime(2999);
        expect(wrapper.emitted('update:show')).toBeFalsy();

        // Cross the 3000ms threshold — now it emits false.
        vi.advanceTimersByTime(1);
        const emitted = wrapper.emitted('update:show');
        expect(emitted).toBeTruthy();
        expect(emitted).toHaveLength(1);
        expect(emitted[0]).toEqual([false]);
    });

    it('honors a custom duration prop', async () => {
        const wrapper = mount(Toast, {
            props: { show: false, message: 'Hi', duration: 500 },
        });

        await wrapper.setProps({ show: true });

        vi.advanceTimersByTime(499);
        expect(wrapper.emitted('update:show')).toBeFalsy();

        vi.advanceTimersByTime(1);
        expect(wrapper.emitted('update:show')[0]).toEqual([false]);
    });

    it('starts the auto-dismiss timer when mounted already showing? (watcher is on change only)', async () => {
        // The watcher fires only when `show` *transitions* to true. A toast
        // mounted with show=true does NOT schedule a timer (documented behavior).
        const wrapper = mount(Toast, {
            props: { show: true, message: 'Hi', duration: 100 },
        });

        vi.advanceTimersByTime(1000);
        expect(wrapper.emitted('update:show')).toBeFalsy();
    });

    it('schedules a new timer each time show toggles back on', async () => {
        const wrapper = mount(Toast, {
            props: { show: false, message: 'Hi', duration: 100 },
        });

        // First on -> off cycle
        await wrapper.setProps({ show: true });
        vi.advanceTimersByTime(100);
        expect(wrapper.emitted('update:show')).toHaveLength(1);

        // Parent acknowledges by setting show=false, then shows again.
        await wrapper.setProps({ show: false });
        await wrapper.setProps({ show: true });
        vi.advanceTimersByTime(100);
        expect(wrapper.emitted('update:show')).toHaveLength(2);
        expect(wrapper.emitted('update:show')[1]).toEqual([false]);
    });
});
