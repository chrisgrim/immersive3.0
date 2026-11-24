/**
 * The go-live pop-up: a day and a time where the event is. The time menu
 * offers every half hour (noon to begin with), and Save hands back the stored
 * form "YYYY-MM-DD HH:mm:00" with the button spelling out the choice.
 */
import { mount } from '@vue/test-utils';
import EmbargoModal from '@/PageComponents/Creation/Core/Pages/Dates/embargo-modal.vue';

const mountModal = (embargoDate = null) => mount(EmbargoModal, {
    props: { embargoDate, timezone: 'America/Los_Angeles' },
    global: { stubs: { teleport: true, VueDatePicker: { template: '<div class="embargo-calendar" />' } } },
});

describe('go-live pop-up', () => {
    it('offers every half hour, noon to begin with', () => {
        const wrapper = mountModal();
        const options = wrapper.findAll('[data-embargo-time] option');
        expect(options).toHaveLength(48);
        expect(wrapper.find('[data-embargo-time]').element.value).toBe('12:00');
        expect(options[16].text()).toBe('8:00 AM');
    });

    it('opens on the saved day and time, and saves the time picked', async () => {
        const wrapper = mountModal('2099-11-22 08:00:00');
        expect(wrapper.find('[data-embargo-time]').element.value).toBe('08:00');
        expect(wrapper.find('[data-embargo-save]').text()).toContain('Nov 22, 8:00 AM (PST)');

        await wrapper.find('[data-embargo-time]').setValue('18:30');
        await wrapper.find('[data-embargo-save]').trigger('click');
        expect(wrapper.emitted('save')[0]).toEqual(['2099-11-22 18:30:00']);
    });
});

describe('go-live pop-up rules', () => {
    it('will not save a time that has already passed, and Escape closes it', async () => {
        const wrapper = mountModal('2020-01-05 08:00:00');
        expect(wrapper.find('[data-embargo-save]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-embargo-save]').text()).toContain('later time');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(wrapper.emitted('cancel')).toHaveLength(1);
        wrapper.unmount();
    });
});

describe('go-live pop-up months', () => {
    it('shows six months, more on request, and enough to reach a date already picked', async () => {
        const wrapper = mountModal(null);
        await wrapper.find('[data-embargo-more-months]').trigger('click');
        expect(wrapper.vm.monthCount).toBe(12);
        wrapper.unmount();

        const later = new Date();
        later.setMonth(later.getMonth() + 9);
        const far = mountModal(`${later.getFullYear()}-${String(later.getMonth() + 1).padStart(2, '0')}-15 12:00:00`);
        expect(far.vm.monthCount).toBeGreaterThanOrEqual(10);
        far.unmount();
    });

    it('keeps the page behind from scrolling while open, and gives it back on close', () => {
        document.documentElement.style.overflow = '';
        document.body.style.overflow = 'auto';
        const wrapper = mountModal();
        expect(document.body.style.overflow).toBe('hidden');
        expect(document.documentElement.style.overflow).toBe('hidden');
        wrapper.unmount();
        expect(document.body.style.overflow).toBe('auto');
        expect(document.documentElement.style.overflow).toBe('');
    });
});
