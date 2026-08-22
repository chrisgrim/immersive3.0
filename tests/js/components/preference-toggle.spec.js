/**
 * Specs for GlobalComponents/preference-toggle.vue
 *
 * This is the shared toggle used by both Privacy.vue and Notifications.vue.
 * Notifications.vue previously bound `:checked="preferences[item.key]"`
 * directly even though its label reads as a "Turn off X" action — the
 * toggle showed the opposite of what was actually true, and nothing here
 * caught it since preference-toggle.vue itself had no coverage. This file
 * exists to nail down the component's actual, unambiguous contract (checked
 * reflects the prop as given — inverting for label semantics is the
 * CALLER's job) so a future caller-side inversion bug is easy to isolate.
 *
 * Covers:
 *  - checked prop reflects on the underlying checkbox with no inversion.
 *  - A user interaction emits a bare 'change' event (no payload) — the
 *    caller reads its own state, not an event argument.
 *  - disabled prop sets the actual disabled attribute (the real mechanism
 *    that blocks interaction) rather than asserting jsdom's exact blocked-
 *    event semantics, which are more implementation detail than contract.
 *  - ariaLabel passthrough.
 */
import { mount } from '@vue/test-utils';
import PreferenceToggle from '@/GlobalComponents/preference-toggle.vue';

function mountPreferenceToggle(props = {}) {
    return mount(PreferenceToggle, {
        props: {
            ariaLabel: 'Test toggle',
            ...props,
        },
    });
}

describe('preference-toggle.vue', () => {
    describe('checked prop', () => {
        it('reflects checked=false on the checkbox with no inversion', () => {
            const wrapper = mountPreferenceToggle({ checked: false });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
        });

        it('reflects checked=true on the checkbox with no inversion', () => {
            const wrapper = mountPreferenceToggle({ checked: true });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(true);
        });

        it('defaults to unchecked when checked is omitted', () => {
            const wrapper = mountPreferenceToggle();
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
        });

        it('updates the checkbox when the checked prop changes', async () => {
            const wrapper = mountPreferenceToggle({ checked: false });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
            await wrapper.setProps({ checked: true });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(true);
        });
    });

    describe('change event', () => {
        it('emits a bare "change" event with no payload when toggled', async () => {
            const wrapper = mountPreferenceToggle({ checked: false });
            await wrapper.find('input[type="checkbox"]').trigger('change');

            expect(wrapper.emitted('change')).toBeTruthy();
            expect(wrapper.emitted('change')).toHaveLength(1);
            // No payload — the caller reads its own preference state rather
            // than trusting an event argument (checked is not re-emitted).
            expect(wrapper.emitted('change')[0]).toEqual([]);
        });

        it('emits "change" again on a second interaction regardless of checked value', async () => {
            const wrapper = mountPreferenceToggle({ checked: true });
            await wrapper.find('input[type="checkbox"]').trigger('change');
            await wrapper.find('input[type="checkbox"]').trigger('change');

            expect(wrapper.emitted('change')).toHaveLength(2);
        });
    });

    describe('disabled prop', () => {
        it('is not disabled by default', () => {
            const wrapper = mountPreferenceToggle();
            expect(wrapper.find('input[type="checkbox"]').element.disabled).toBe(false);
        });

        it('sets the disabled attribute on the checkbox when disabled=true', () => {
            const wrapper = mountPreferenceToggle({ disabled: true });
            expect(wrapper.find('input[type="checkbox"]').element.disabled).toBe(true);
        });

        it('applies a not-allowed cursor class on the label when disabled', () => {
            const enabled = mountPreferenceToggle({ disabled: false });
            expect(enabled.find('label').classes()).toContain('cursor-pointer');
            expect(enabled.find('label').classes()).not.toContain('cursor-not-allowed');

            const disabled = mountPreferenceToggle({ disabled: true });
            expect(disabled.find('label').classes()).toContain('cursor-not-allowed');
            expect(disabled.find('label').classes()).not.toContain('cursor-pointer');
        });
    });

    describe('ariaLabel', () => {
        it('passes ariaLabel through to the checkbox aria-label attribute', () => {
            const wrapper = mountPreferenceToggle({ ariaLabel: 'Turn off new dates added to saved events' });
            expect(wrapper.find('input[type="checkbox"]').attributes('aria-label')).toBe(
                'Turn off new dates added to saved events',
            );
        });
    });
});
