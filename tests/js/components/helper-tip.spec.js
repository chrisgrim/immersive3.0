/**
 * The Helper Box: in the listing steps its words open in the Tips pop-up;
 * on the organization page (`show`) it is a card on the page itself.
 */
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';
import HelperTip from '@/PageComponents/Creation/Core/Components/helper-tip.vue';

const mountTip = (tips, props = {}) => mount(HelperTip, {
    props: { title: "What's a run?", ...props },
    slots: { default: '<p>Runs are instances.</p>' },
    global: { provide: tips ? { helperTips: tips } : {} },
});

describe('helper-tip', () => {
    let target;
    beforeEach(() => {
        target = document.createElement('div');
        target.id = 'helper-tips-body';
        document.body.appendChild(target);
    });
    afterEach(() => target.remove());

    it('lands in the Tips pop-up only while it is open, and counts itself', async () => {
        const tips = reactive({ open: false, count: 0 });
        const wrapper = mountTip(tips);
        expect(tips.count).toBe(1);
        expect(target.textContent).toBe('');

        tips.open = true;
        await nextTick();
        expect(target.textContent).toContain("What's a run?");
        expect(target.textContent).toContain('Runs are instances.');

        wrapper.unmount();
        expect(tips.count).toBe(0);
    });

    it('is a card on the page when the page shows it', () => {
        const wrapper = mountTip(null, { show: true });
        expect(wrapper.find('[data-helper-tip]').text()).toContain("What's a run?");
        expect(target.textContent).toBe('');
    });
});
