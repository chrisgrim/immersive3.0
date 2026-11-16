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
        const wrapper = mountModal('2026-11-22 08:00:00');
        expect(wrapper.find('[data-embargo-time]').element.value).toBe('08:00');
        expect(wrapper.find('[data-embargo-save]').text()).toContain('Nov 22, 8:00 AM (PST)');

        await wrapper.find('[data-embargo-time]').setValue('18:30');
        await wrapper.find('[data-embargo-save]').trigger('click');
        expect(wrapper.emitted('save')[0]).toEqual(['2026-11-22 18:30:00']);
    });
});
