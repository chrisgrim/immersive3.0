/**
 * The event creation steps' Save & exit: a step not filled in is left for
 * later (the draft already exists), and the message under the top bar never
 * sticks: it goes on OK, on typing, on a step change, and by itself.
 */
import { mount, flushPromises } from '@vue/test-utils';

// The wizard uses the global axios (window.axios, a mock from tests/js/setup.js).
const axios = { get post() { return window.axios.post; } };

// Every step page is a stand-in that is valid or not, as the test says.
const state = vi.hoisted(() => ({ valid: true, kept: null }));
vi.mock('@/PageComponents/Creation/Core/Pages/name.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return { default: defineComponent({
        setup(_, { expose }) {
            expose({ isValid: async () => state.valid, submitData: async () => ({ name: 'X' }), keepsOnExit: () => state.kept });
            return () => h('div', [h('input', { 'data-step-input': '' })]);
        },
    }) };
});

import Wizard from '@/PageComponents/Creation/Core/index.vue';

function mountAt(valid, kept = null) {
    state.valid = valid.value;
    state.kept = kept;
    delete window.location;
    window.location = { href: '', search: '?view=Name', pathname: '/hosting/event/x/edit' };
    window.history.pushState = vi.fn();
    window.history.replaceState = vi.fn();
    return mount(Wizard, {
        props: { event: { slug: 'x', status: '2', organizer: { slug: 'org' }, location: {} }, user: { id: 1 } },
        global: { stubs: { ShowRunPicker: true } },
    });
}

describe('event wizard Save & exit', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.axios.get.mockResolvedValue({ data: { choices: [] } });
    });
    afterEach(() => vi.useRealTimers());

    it('leaves without saving a step that is not filled in', async () => {
        const wrapper = mountAt({ value: false });
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text().includes('Save & exit')).trigger('click');
        await flushPromises();

        expect(axios.post).not.toHaveBeenCalled();
        expect(window.location.href).toBe('/hosting/events?organizer=org');
    });

    it('a refused save shows why, and the message goes on OK, by itself, and on typing', async () => {
        const settle = () => flushPromises();
        axios.post.mockRejectedValue({ response: { status: 422, data: { errors: { name: ['Too long.'] } } } });
        const wrapper = mountAt({ value: true });
        await settle();

        const exit = () => wrapper.findAll('button').find((b) => b.text().includes('Save & exit') || b.text().includes('Saving')).trigger('click');
        await exit();
        await settle();
        expect(wrapper.text()).toContain('Could not save: Too long.');

        await wrapper.find('[data-exit-error-ok]').trigger('click');
        expect(wrapper.text()).not.toContain('Could not save');

        vi.useFakeTimers();
        await exit();
        await vi.advanceTimersByTimeAsync(50);
        expect(wrapper.text()).toContain('Could not save');
        await vi.advanceTimersByTimeAsync(6500);
        vi.useRealTimers();
        expect(wrapper.text()).not.toContain('Could not save');

        await exit();
        await settle();
        await wrapper.find('[data-step-input]').trigger('input');
        expect(wrapper.text()).not.toContain('Could not save');
    });

    it('stays, with the reason, when the step tried to save something and failed', async () => {
        const wrapper = mountAt({ value: false }, 'That venue could not be added.');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text().includes('Save & exit')).trigger('click');
        await flushPromises();

        expect(window.location.href).toBe('');
        expect(wrapper.text()).toContain('Could not save: That venue could not be added.');
    });
});
