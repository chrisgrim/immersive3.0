/** Add a venue: the steps, the venue made at Location, the tiles. */
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@/GlobalComponents/address-map.vue', () => ({ default: { name: 'AddressMap', props: ['modelValue'], emits: ['update:modelValue'], template: '<div data-map />' } }));

import VenueCreate from '@/PageComponents/Venues/venue-create.vue';
import AmenityTiles from '@/PageComponents/Venues/amenity-tiles.vue';

const salford = { home: 'The Lowry Hotel', street: '50 Dearmans Place', city: 'Salford', region: 'England', region_long: 'England', country: 'GB', country_long: 'United Kingdom', postal_code: 'M3 5LH', latitude: 53.48, longitude: -2.25 };

describe('venue-create.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.location;
        window.location = { href: '', search: '', pathname: '/hosting/venues/new' };
        window.history.replaceState = vi.fn();
    });

    it('makes a draft from the name, then saves the address and Socials to it', async () => {
        const wrapper = mount(VenueCreate);
        await wrapper.find('[data-next]').trigger('click');
        expect(wrapper.find('[data-step-error]').text()).toContain('name');

        await wrapper.find('[data-venue-name]').setValue('Temp one');
        axios.post.mockResolvedValueOnce({ data: { id: 5, slug: 'temp-one', photos: [], max_photos: 4 } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/hosting/venues', { name: 'Temp one', room: null });
        expect(window.history.replaceState).toHaveBeenCalledWith({}, '', '/venues/temp-one/setup?step=Name');
        expect(wrapper.vm.step).toBe('Location');

        await wrapper.find('[data-next]').trigger('click');
        expect(wrapper.find('[data-step-error]').text()).toContain('address');

        axios.get.mockResolvedValueOnce({ data: [] });
        wrapper.findComponent({ name: 'AddressMap' }).vm.$emit('update:modelValue', salford);
        await flushPromises();
        axios.post.mockResolvedValueOnce({ data: { id: 5, slug: 'temp-one', photos: [], max_photos: 4 } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenLastCalledWith('/venues/temp-one', expect.objectContaining({ street: '50 Dearmans Place', city: 'Salford' }));
        expect(wrapper.vm.step).toBe('Socials');

        await wrapper.find('[data-social="website"]').setValue('https://lowry.example');
        axios.post.mockResolvedValueOnce({ data: { id: 5, slug: 'temp-one', photos: [], max_photos: 4 } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenLastCalledWith('/venues/temp-one', expect.objectContaining({ website: 'https://lowry.example' }));
        expect(wrapper.vm.step).toBe('Photos');
    });

    it('a known venue at the address stops it, until "a different space here" is named', async () => {
        const wrapper = mount(VenueCreate);
        await wrapper.find('[data-venue-name]').setValue('Rooftop Bar');
        axios.post.mockResolvedValueOnce({ data: { id: 6, slug: 'rooftop-bar' } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();

        axios.get.mockResolvedValueOnce({ data: [{ id: 9, name: 'The Lowry Hotel', slug: 'the-lowry-hotel', status: 'pending', at_address: true, url: '/venues/the-lowry-hotel', claim_url: '/venues/the-lowry-hotel?expires=1&signature=x' }] });
        wrapper.findComponent({ name: 'AddressMap' }).vm.$emit('update:modelValue', salford);
        await flushPromises();

        await wrapper.find('[data-next]').trigger('click');
        expect(wrapper.find('[data-step-error]').text()).toContain('We already have The Lowry Hotel here');
        expect(axios.post).toHaveBeenCalledTimes(1); // only the draft, nothing more
        // "Check it out": its page through the link (in review), naming the draft to drop.
        expect(wrapper.find('[data-venue-known-open]').attributes('href')).toBe('/venues/the-lowry-hotel?expires=1&signature=x');

        await wrapper.find('[data-venue-known] button').trigger('click');
        await wrapper.find('[data-next]').trigger('click');
        expect(wrapper.find('[data-step-error]').text()).toContain('Name this space');
    });

    it('carries on from a made venue: later steps save to it, and Finish goes to Your venues', async () => {
        window.location.search = '?step=Amenities';
        const wrapper = mount(VenueCreate, { props: { venue: { id: 5, slug: 'the-lowry-hotel', name: 'The Lowry Hotel', ...salford, photos: [], amenities: [] } } });
        expect(wrapper.vm.step).toBe('Amenities');

        const food = wrapper.find('[data-amenity-group="food_drink"]');
        await food.find('input').trigger('focus');
        await food.findAll('li').find((li) => li.text() === 'Bar').trigger('click');
        axios.post.mockResolvedValueOnce({ data: { slug: 'the-lowry-hotel', amenities: ['bar'] } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith('/venues/the-lowry-hotel', { amenities: ['bar'] });
        expect(wrapper.vm.step).toBe('Review');

        axios.post.mockResolvedValueOnce({ data: { slug: 'the-lowry-hotel', status: 'pending' } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();
        expect(axios.post).toHaveBeenLastCalledWith('/venues/the-lowry-hotel/submit');
        expect(window.location.href).toBe('/hosting/venues?added=1');
    });
});

describe('venue-create.vue, a refused contact box', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.location;
        window.location = { href: '', search: '?step=Socials', pathname: '/venues/the-lowry-hotel/setup' };
        window.history.replaceState = vi.fn();
    });

    it('goes red with its reason under it, and clears as it is fixed', async () => {
        const wrapper = mount(VenueCreate, { props: { venue: { id: 5, slug: 'the-lowry-hotel', name: 'The Lowry Hotel', ...salford, photos: [], amenities: [] } } });
        await wrapper.find('[data-social="phone"]').setValue('832-443-03034');
        axios.post.mockRejectedValueOnce({ response: { status: 422, data: { errors: { phone: ['That phone number does not look right.'] } } } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();

        expect(wrapper.vm.step).toBe('Socials');
        expect(wrapper.find('[data-field-error="phone"]').text()).toBe('That phone number does not look right.');
        expect(wrapper.find('[data-social="phone"]').element.parentElement.className).toContain('border-red-500');
        expect(wrapper.find('[data-step-error]').exists()).toBe(false);

        await wrapper.find('[data-social="phone"]').setValue('8324438072');
        expect(wrapper.find('[data-field-error="phone"]').exists()).toBe(false);
        expect(wrapper.find('[data-social="phone"]').element.value).toBe('832-443-8072');
    });
});

describe('amenity-tiles.vue', () => {
    const pick = async (wrapper, group, label) => {
        const section = wrapper.find(`[data-amenity-group="${group}"]`);
        await section.find('input').trigger('focus');
        await section.findAll('li').find((li) => li.text() === label).trigger('click');
    };
    const type = async (wrapper, group, text) => {
        const box = wrapper.find(`[data-amenity-group="${group}"] input`);
        await box.setValue(text);
        await box.trigger('keydown', { key: 'Enter' });
    };

    it('each group is a box: the main ones with their icon first, then more, or your own words', async () => {
        const onUpdate = vi.fn();
        const own = { group: 'access', label: 'Ramp at the side door' };
        const wrapper = mount(AmenityTiles, { props: { modelValue: ['bar', own], 'onUpdate:modelValue': onUpdate } });
        const access = wrapper.find('[data-amenity-group="access"]');

        await access.find('input').trigger('focus');
        const options = access.findAll('li').map((li) => li.text());
        expect(options.slice(0, 2)).toEqual(['Wheelchair accessible', 'Step-free entrance']);
        expect(options).toContain('Quiet room');
        expect(access.findAll('li')[0].find('svg').exists()).toBe(true);

        await pick(wrapper, 'access', 'Quiet room');
        expect(onUpdate).toHaveBeenLastCalledWith(['bar', own, 'quiet_room']);

        await type(wrapper, 'access', 'Sign language tours');
        expect(onUpdate).toHaveBeenLastCalledWith(['bar', own, { group: 'access', label: 'Sign language tours' }]);
        // Typing one we know picks it.
        await type(wrapper, 'access', 'lift');
        expect(onUpdate).toHaveBeenLastCalledWith(['bar', own, 'lift']);

        // Picked ones show as chips, removed with their cross.
        expect(access.text()).toContain('Ramp at the side door');
        await access.find('.dropdown-delete-btn').trigger('click');
        expect(onUpdate).toHaveBeenLastCalledWith(['bar']);
    });

    it('Age takes one at most, and no words of your own', async () => {
        const onUpdate = vi.fn();
        const wrapper = mount(AmenityTiles, { props: { modelValue: ['bar', 'age_18'], 'onUpdate:modelValue': onUpdate } });

        await pick(wrapper, 'age', '21+');
        expect(onUpdate).toHaveBeenLastCalledWith(['bar', 'age_21']);
        onUpdate.mockClear();
        await type(wrapper, 'age', '16+');
        expect(onUpdate).not.toHaveBeenCalled();
    });
});

describe('venue-create.vue, Save & exit', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.location;
        window.location = { href: '', search: '', pathname: '/hosting/venues/new' };
        window.history.replaceState = vi.fn();
    });

    it('on the first step with a name, it makes the draft and leaves: it is in Your venues', async () => {
        const wrapper = mount(VenueCreate);
        await wrapper.find('[data-venue-name]').setValue('Temp one');
        axios.post.mockResolvedValueOnce({ data: { id: 7, slug: 'temp-one' } });

        await wrapper.find('[data-save-exit]').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/hosting/venues', { name: 'Temp one', room: null });
        expect(window.location.href).toBe('/hosting/venues');
    });

    it('with nothing typed it just leaves', async () => {
        const wrapper = mount(VenueCreate);
        await wrapper.find('[data-save-exit]').trigger('click');

        expect(axios.post).not.toHaveBeenCalled();
        expect(window.location.href).toBe('/hosting/venues');
    });

    it('names venues EI already has by that name, each with a Check it out link (its page, where Claim is)', async () => {
        vi.useFakeTimers();
        const wrapper = mount(VenueCreate);
        axios.get.mockResolvedValueOnce({ data: [{ id: 3, name: 'The Lowry', address: 'Salford', status: 'approved', claim_url: '/venues/the-lowry' }] });

        await wrapper.find('[data-venue-name]').setValue('The Lowry');
        await flushPromises();
        // Nothing while typing; once they leave the box.
        expect(wrapper.find('[data-venue-name-matches]').exists()).toBe(false);
        await wrapper.find('[data-venue-name]').trigger('blur');
        await vi.advanceTimersByTimeAsync(450);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/venues/known', { params: { name: 'The Lowry', except: undefined } });
        expect(wrapper.find('[data-venue-name-matches] a').attributes('href')).toBe('/venues/the-lowry');
        vi.useRealTimers();
    });

    it('a venue of their own at the address: "Go to it" opens theirs and leaves the one being added alone', async () => {
        const wrapper = mount(VenueCreate);
        await wrapper.find('[data-venue-name]').setValue('Temp two');
        axios.post.mockResolvedValueOnce({ data: { id: 8, slug: 'temp-two' } });
        await wrapper.find('[data-next]').trigger('click');
        await flushPromises();

        axios.get.mockResolvedValueOnce({ data: [{ id: 3, slug: 'temp-one', name: 'Temp one', status: 'draft', at_address: true, yours: true, edit_url: '/venues/temp-one/setup' }] });
        wrapper.findComponent({ name: 'AddressMap' }).vm.$emit('update:modelValue', salford);
        await flushPromises();

        expect(wrapper.find('[data-venue-known]').text()).toContain('You already have a venue at this address');
        const posts = axios.post.mock.calls.length;
        await wrapper.find('[data-go-to-mine]').trigger('click');
        await flushPromises();

        expect(axios.post.mock.calls.length).toBe(posts); // nothing dropped
        expect(window.location.href).toBe('/venues/temp-one/setup');
    });

    it('Save & exit on an unfinished map step leaves, the draft kept, nothing more saved', async () => {
        window.location.search = '?step=Location';
        const wrapper = mount(VenueCreate, { props: { venue: { id: 5, slug: 'temp-two', name: 'Temp Two', status: 'draft' } } });

        await wrapper.find('[data-save-exit]').trigger('click');
        await flushPromises();

        expect(axios.post).not.toHaveBeenCalled();
        expect(window.location.href).toBe('/hosting/venues');
    });

});
