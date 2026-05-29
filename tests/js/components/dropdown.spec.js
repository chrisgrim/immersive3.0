import { mount } from '@vue/test-utils';
import { describe, it, expect, beforeEach } from 'vitest';
import Dropdown from '@/GlobalComponents/dropdown.vue';

/**
 * The component uses the `v-click-outside` custom directive (registered
 * globally as `click-outside` in the real app via app.js). In the test it is
 * not registered, which makes Vue throw "Failed to resolve directive". We
 * register a no-op stub so mounting succeeds without changing behavior.
 */
const clickOutsideStub = {
    mounted() {},
    beforeMount() {},
    unmounted() {},
};

const globalOpts = {
    directives: {
        'click-outside': clickOutsideStub,
    },
};

const sampleList = [
    { id: 1, name: 'Apple' },
    { id: 2, name: 'Banana' },
    { id: 3, name: 'Cherry' },
    { id: 4, name: 'apricot' },
];

function makeWrapper(props = {}) {
    return mount(Dropdown, {
        props: { list: sampleList, ...props },
        global: globalOpts,
    });
}

// Helper: open the dropdown by focusing the input.
async function open(wrapper) {
    await wrapper.find('input').trigger('focus');
}

// Helper: type a value into the input (sets v-model + fires @input).
async function type(wrapper, value) {
    const input = wrapper.find('input');
    await input.setValue(value);
    await input.trigger('input');
}

describe('dropdown.vue', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = makeWrapper();
    });

    it('renders all list items when opened with no search term', async () => {
        await open(wrapper);
        const items = wrapper.findAll('ul li');
        expect(items).toHaveLength(sampleList.length);
        expect(items.map((li) => li.text())).toEqual([
            'Apple',
            'Banana',
            'Cherry',
            'apricot',
        ]);
    });

    it('does not render the dropdown list until focused', () => {
        expect(wrapper.find('ul').exists()).toBe(false);
    });

    it('filters items case-insensitively as you type', async () => {
        await open(wrapper);
        await type(wrapper, 'ap'); // matches "Apple" and "apricot"
        const names = wrapper.findAll('ul li').map((li) => li.text());
        expect(names).toContain('Apple');
        expect(names).toContain('apricot');
        expect(names).not.toContain('Banana');
        expect(names).not.toContain('Cherry');
    });

    it('matches uppercase search against lowercase item names', async () => {
        await open(wrapper);
        await type(wrapper, 'BANANA');
        const names = wrapper.findAll('ul li').map((li) => li.text());
        expect(names).toEqual(['Banana']);
    });

    it('emits an "input" event with the current search term as you type', async () => {
        await open(wrapper);
        await type(wrapper, 'che');
        const emitted = wrapper.emitted('input');
        expect(emitted).toBeTruthy();
        expect(emitted[emitted.length - 1]).toEqual(['che']);
    });

    it('emits onSelect with the chosen item and resets/closes on click', async () => {
        await open(wrapper);
        // Click the second item (Banana)
        const banana = wrapper.findAll('ul li')[1];
        await banana.trigger('click');

        const onSelect = wrapper.emitted('onSelect');
        expect(onSelect).toBeTruthy();
        expect(onSelect[0]).toEqual([{ id: 2, name: 'Banana' }]);

        // Dropdown closes after selection
        expect(wrapper.find('ul').exists()).toBe(false);
        // Search term resets
        expect(wrapper.find('input').element.value).toBe('');
    });

    describe('create option (creatable = true)', () => {
        beforeEach(() => {
            wrapper = makeWrapper({ creatable: true });
        });

        it('shows the create option only when no exact match exists', async () => {
            await open(wrapper);
            await type(wrapper, 'Dragonfruit'); // no match at all

            const createLi = wrapper
                .findAll('ul li')
                .find((li) => li.text().includes('Create:'));
            expect(createLi).toBeTruthy();
            expect(createLi.text()).toContain('Dragonfruit');
        });

        it('shows the create option when a partial (non-exact) match exists', async () => {
            await open(wrapper);
            await type(wrapper, 'App'); // partial match for "Apple", no exact

            const createLi = wrapper
                .findAll('ul li')
                .find((li) => li.text().includes('Create:'));
            expect(createLi).toBeTruthy();
        });

        it('hides the create option when an exact (case-insensitive) match exists', async () => {
            await open(wrapper);
            await type(wrapper, 'apple'); // exact match for "Apple"

            const createLi = wrapper
                .findAll('ul li')
                .find((li) => li.text().includes('Create:'));
            expect(createLi).toBeFalsy();
        });

        it('does not show the create option when search term is empty', async () => {
            await open(wrapper);
            const createLi = wrapper
                .findAll('ul li')
                .find((li) => li.text().includes('Create:'));
            expect(createLi).toBeFalsy();
        });

        it('emits onSelect with a string-prefixed temp id when creating', async () => {
            await open(wrapper);
            await type(wrapper, 'Elderberry');

            const createLi = wrapper
                .findAll('ul li')
                .find((li) => li.text().includes('Create:'));
            await createLi.trigger('click');

            const onSelect = wrapper.emitted('onSelect');
            expect(onSelect).toBeTruthy();
            const created = onSelect[onSelect.length - 1][0];
            expect(created.name).toBe('Elderberry');
            // temp id is a "new-" prefixed string so submitData strips it
            expect(typeof created.id).toBe('string');
            expect(created.id.startsWith('new-')).toBe(true);

            // closes + resets
            expect(wrapper.find('ul').exists()).toBe(false);
            expect(wrapper.find('input').element.value).toBe('');
        });
    });

    describe('Enter key handling', () => {
        it('selects the first filtered item on Enter when not creatable', async () => {
            // When not creatable, Enter always falls through to selecting the
            // first filtered item.
            wrapper = makeWrapper({ creatable: false });
            await open(wrapper);
            await type(wrapper, 'ap'); // first match is "Apple"
            await wrapper.find('input').trigger('keydown.enter');

            const onSelect = wrapper.emitted('onSelect');
            expect(onSelect).toBeTruthy();
            expect(onSelect[0]).toEqual([{ id: 1, name: 'Apple' }]);
        });

        it('selects the exact match on Enter even when creatable (create option suppressed)', async () => {
            // With an exact match the create option is hidden, so handleEnter
            // selects the first filtered item instead of creating.
            wrapper = makeWrapper({ creatable: true });
            await open(wrapper);
            await type(wrapper, 'Apple'); // exact match
            await wrapper.find('input').trigger('keydown.enter');

            const onSelect = wrapper.emitted('onSelect');
            expect(onSelect).toBeTruthy();
            expect(onSelect[0]).toEqual([{ id: 1, name: 'Apple' }]);
        });

        it('creates a new item on Enter when creatable and no exact match', async () => {
            wrapper = makeWrapper({ creatable: true });
            await open(wrapper);
            await type(wrapper, 'Figbar'); // no match
            await wrapper.find('input').trigger('keydown.enter');

            const onSelect = wrapper.emitted('onSelect');
            expect(onSelect).toBeTruthy();
            const created = onSelect[onSelect.length - 1][0];
            expect(created.name).toBe('Figbar');
            expect(created.id.startsWith('new-')).toBe(true);
        });

        it('does nothing on Enter when not creatable and no match exists', async () => {
            wrapper = makeWrapper({ creatable: false });
            await open(wrapper);
            await type(wrapper, 'zzz-nomatch');
            await wrapper.find('input').trigger('keydown.enter');

            expect(wrapper.emitted('onSelect')).toBeFalsy();
        });
    });

    it('respects the maxInputLength prop on the input', () => {
        wrapper = makeWrapper({ maxInputLength: 12 });
        expect(wrapper.find('input').attributes('maxlength')).toBe('12');
    });

    it('renders the placeholder prop', () => {
        wrapper = makeWrapper({ placeholder: 'Pick a fruit' });
        expect(wrapper.find('input').attributes('placeholder')).toBe('Pick a fruit');
    });

    it('applies error styling classes when error is truthy', () => {
        wrapper = makeWrapper({ error: true });
        expect(wrapper.find('input').classes()).toContain('border-red-500');
    });
});
