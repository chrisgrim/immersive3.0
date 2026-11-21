/**
 * The Helper Box: shown while the top bar's Tips switch is on, or when the
 * page says so itself (the organization page has no top bar).
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
    it('follows the Tips switch', async () => {
        const tips = reactive({ on: false });
        const wrapper = mountTip(tips);
        expect(wrapper.find('[data-helper-tip]').exists()).toBe(false);

        tips.on = true;
        await nextTick();
        expect(wrapper.find('[data-helper-tip]').text()).toContain("What's a run?");
        expect(wrapper.text()).toContain('Runs are instances.');
    });

    it('stays hidden with no switch, unless the page shows it', () => {
        expect(mountTip(null).find('[data-helper-tip]').exists()).toBe(false);
        expect(mountTip(null, { show: true }).find('[data-helper-tip]').exists()).toBe(true);
    });
});
