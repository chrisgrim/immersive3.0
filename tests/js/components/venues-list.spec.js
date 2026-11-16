/**
 * Your venues, as the hosting events list: the whole row opens it, a box picks
 * it for the side panel, hovering shows Edit and (a draft) Delete, and a
 * delete is asked in its own pop-up. Only drafts can be deleted.
 */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import VenuesList from '@/PageComponents/Venues/venues-list.vue';

vi.mock('axios', () => ({ default: { delete: vi.fn(() => Promise.resolve({ data: {} })) } }));

const draft = { name: 'Temp Hall', slug: 'temp-hall', image: null, owned: true, draft: true, pending: false, place: 'Petaluma, CA', open_url: '/venues/temp-hall/setup', show_url: '/venues/temp-hall', delete_url: '/venues/temp-hall' };
const live = { name: 'Albert Theatre', slug: 'albert', image: null, owned: true, draft: false, pending: false, place: 'Chicago, IL', open_url: '/venues/albert/edit', show_url: '/venues/albert', delete_url: null };
const other = { ...draft, name: 'Scratch', slug: 'scratch', open_url: '/venues/scratch/setup', delete_url: '/venues/scratch' };

const row = (wrapper, slug) => wrapper.find(`[data-venue-row="${slug}"]`);
const mountList = (venues = [draft, live, other]) => mount(VenuesList, { props: { venues } });

describe('your venues list', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.location;
        window.location = { href: '', reload: vi.fn() };
    });

    it('the whole row opens it: a draft where it was left, a sent one its edit page', async () => {
        const wrapper = mountList();
        await row(wrapper, 'temp-hall').trigger('click');
        expect(window.location.href).toBe('/venues/temp-hall/setup');
        await row(wrapper, 'albert').trigger('click');
        expect(window.location.href).toBe('/venues/albert/edit');
    });

    it('hovering: Delete on a draft, See it on the site on a sent one', () => {
        const wrapper = mountList();
        expect(row(wrapper, 'temp-hall').find('[data-venue-delete]').exists()).toBe(true);
        expect(row(wrapper, 'albert').find('[data-venue-delete]').exists()).toBe(false);
        expect(row(wrapper, 'albert').find('[data-venue-view]').attributes('href')).toBe('/venues/albert');
    });

    it('picking opens the panel; while picking a row click picks instead of opening', async () => {
        const wrapper = mountList();
        await row(wrapper, 'albert').find('[data-venue-pick]').trigger('change');
        expect(wrapper.find('[data-venue-panel]').text()).toContain('Edit 1 venue');
        expect(wrapper.find('[data-venue-panel-delete]').exists()).toBe(false);

        await row(wrapper, 'temp-hall').trigger('click');
        expect(window.location.href).toBe('');
        expect(wrapper.find('[data-venue-panel]').text()).toContain('2 venues picked');
        expect(wrapper.find('[data-venue-panel-delete-all]').text()).toContain('Delete 1 draft');

        await wrapper.find('[data-venue-panel-close]').trigger('click');
        expect(wrapper.find('[data-venue-panel]').exists()).toBe(false);
    });

    it('delete asks in its pop-up, then deletes the drafts only', async () => {
        const wrapper = mountList();
        await wrapper.find('[data-pick-all]').exists();
        for (const slug of ['temp-hall', 'albert', 'scratch']) await row(wrapper, slug).find('[data-venue-pick]').trigger('change');
        await wrapper.find('[data-venue-panel-delete-all]').trigger('click');
        expect(wrapper.find('[data-venue-delete-ask]').text()).toContain('Delete 2 drafts?');

        await wrapper.find('[data-venue-delete-yes]').trigger('click');
        await flushPromises();
        expect(axios.delete).toHaveBeenCalledTimes(2);
        expect(axios.delete).toHaveBeenCalledWith('/venues/temp-hall');
        expect(axios.delete).toHaveBeenCalledWith('/venues/scratch');
        expect(window.location.reload).toHaveBeenCalled();
    });
});
