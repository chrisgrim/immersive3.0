/**
 * Specs for GlobalComponents/toggle-switch.vue
 *
 * Covers:
 *  - v-model: a change on the hidden checkbox emits 'update:modelValue'
 *    with the negated boolean.
 *  - The checkbox `checked` attribute tracks the modelValue prop.
 *  - Left/right label rendering + the opacity classes that reflect state.
 *  - textSize-driven class bindings (textSizeClass / sizeClass / toggleSizeClass).
 */
import { mount } from '@vue/test-utils';
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';

function mountToggle(props = {}) {
    return mount(ToggleSwitch, {
        props: {
            modelValue: false,
            ...props,
        },
    });
}

describe('toggle-switch.vue', () => {
    describe('v-model behaviour', () => {
        it('emits update:modelValue with true when toggled from false', async () => {
            const wrapper = mountToggle({ modelValue: false });
            await wrapper.find('input[type="checkbox"]').trigger('change');
            expect(wrapper.emitted('update:modelValue')).toBeTruthy();
            expect(wrapper.emitted('update:modelValue')[0]).toEqual([true]);
        });

        it('emits update:modelValue with false when toggled from true', async () => {
            const wrapper = mountToggle({ modelValue: true });
            await wrapper.find('input[type="checkbox"]').trigger('change');
            expect(wrapper.emitted('update:modelValue')[0]).toEqual([false]);
        });

        it('reflects modelValue on the checkbox checked state', () => {
            const off = mountToggle({ modelValue: false });
            expect(off.find('input[type="checkbox"]').element.checked).toBe(false);

            const on = mountToggle({ modelValue: true });
            expect(on.find('input[type="checkbox"]').element.checked).toBe(true);
        });

        it('updates the checked state when the modelValue prop changes', async () => {
            const wrapper = mountToggle({ modelValue: false });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(false);
            await wrapper.setProps({ modelValue: true });
            expect(wrapper.find('input[type="checkbox"]').element.checked).toBe(true);
        });
    });

    describe('labels', () => {
        it('renders default Off / On labels', () => {
            const wrapper = mountToggle();
            const spans = wrapper.findAll('span');
            expect(spans[0].text()).toBe('Off');
            expect(spans[1].text()).toBe('On');
        });

        it('renders custom left/right labels', () => {
            const wrapper = mountToggle({ leftLabel: 'List', rightLabel: 'Map' });
            const spans = wrapper.findAll('span');
            expect(spans[0].text()).toBe('List');
            expect(spans[1].text()).toBe('Map');
        });

        it('shows the left label active (opacity-100) when off', () => {
            const wrapper = mountToggle({ modelValue: false });
            const spans = wrapper.findAll('span');
            expect(spans[0].classes()).toContain('opacity-100');
            expect(spans[0].classes()).toContain('text-black');
            expect(spans[1].classes()).toContain('opacity-0');
        });

        it('shows the right label active (opacity-100) when on', () => {
            const wrapper = mountToggle({ modelValue: true });
            const spans = wrapper.findAll('span');
            expect(spans[0].classes()).toContain('opacity-0');
            expect(spans[1].classes()).toContain('opacity-100');
            expect(spans[1].classes()).toContain('text-black');
        });
    });

    describe('on/off track + knob classes', () => {
        it('uses the active background and translated knob when on', () => {
            const wrapper = mountToggle({ modelValue: true });
            const track = wrapper.find('label > div');
            expect(track.classes()).toContain('bg-[#ff385c]');
            // The knob (first child div of the track) gets the translate class.
            const knob = track.find('div');
            expect(knob.classes()).toContain('translate-x-[calc(100%-0.50rem)]');
        });

        it('uses the inactive background and untranslated knob when off', () => {
            const wrapper = mountToggle({ modelValue: false });
            const track = wrapper.find('label > div');
            expect(track.classes()).toContain('bg-black');
            const knob = track.find('div');
            expect(knob.classes()).not.toContain('translate-x-[calc(100%-0.50rem)]');
        });
    });

    describe('textSize class bindings', () => {
        const cases = [
            { textSize: 'xs', text: 'text-xs', size: 'w-24', toggle: 'w-[45%]' },
            { textSize: 'sm', text: 'text-sm', size: 'w-32', toggle: 'w-[50%]' },
            { textSize: 'base', text: 'text-base', size: 'w-32', toggle: 'w-[50%]' },
            { textSize: 'lg', text: 'text-lg', size: 'w-40', toggle: 'w-[50%]' },
            { textSize: 'xl', text: 'text-xl', size: 'w-48', toggle: 'w-[50%]' },
        ];

        it.each(cases)(
            'applies the right classes for textSize=$textSize',
            ({ textSize, text, size, toggle }) => {
                const wrapper = mountToggle({ textSize });
                const track = wrapper.find('label > div');
                const knob = track.find('div');
                const labelSpan = wrapper.findAll('span')[0];

                expect(track.classes()).toContain(size);
                expect(knob.classes()).toContain(toggle);
                expect(labelSpan.classes()).toContain(text);
            },
        );

        it('defaults to the sm text size when textSize is omitted', () => {
            const wrapper = mountToggle();
            const track = wrapper.find('label > div');
            expect(track.classes()).toContain('w-32');
            expect(wrapper.findAll('span')[0].classes()).toContain('text-sm');
        });
    });
});
