/**
 * The location step: first "At a venue" or "Somewhere else"; a venue is picked
 * from the list or added, and a new venue's address is checked against venues
 * EI already knows there. The map waits until there is a place.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { reactive, ref } from 'vue';
import axios from 'axios';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@/composables/useGoogleMaps', () => ({ importMapsLibrary: vi.fn(() => Promise.resolve({ AutocompleteService: function () {} })) }));
vi.mock('@vue-leaflet/vue-leaflet', () => ({
    LMap: { name: 'LMap', template: '<div data-map><slot /></div>' },
    LTileLayer: { template: '<div />' },
    LMarker: { template: '<div />' },
    LIcon: { template: '<div />' },
}));
vi.mock('leaflet', () => ({ default: { divIcon: () => ({}) } }));

import Location from '@/PageComponents/Creation/Core/Pages/location.vue';

const lowry = { id: 9, name: 'The Lowry Hotel', room: null, home: 'The Lowry Hotel', street: '50 Dearmans Place', city: 'Salford', region: 'England', country: 'GB', postal_code: 'M3 5LH', latitude: 53.48, longitude: -2.25, address: '50 Dearmans Place, Salford' };

function mountStep(location = {}, provide = {}) {
    const event = reactive({ slug: 'x', timezone: 'Europe/London', location: { venue: '', venue_id: null, latitude: null, longitude: null, hiddenLocationToggle: false, ...location } });
    const errors = ref({});
    global.fetch = vi.fn(() => Promise.resolve({ json: () => Promise.resolve({}) }));
    const wrapper = mount(Location, { global: { provide: { event, errors, ...provide } } });
    return { wrapper, event, errors };
}

describe('location.vue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks "at a venue or somewhere else" first, with no map yet', () => {
        const { wrapper } = mountStep();

        expect(wrapper.find('[data-location-choice]').exists()).toBe(true);
        expect(wrapper.find('[data-map]').exists()).toBe(false);
    });

    it('will not go on without a choice, or "at a venue" without a venue', async () => {
        const { wrapper } = mountStep();

        expect(await wrapper.vm.isValid()).toBe(false);
        await wrapper.find('[data-choose-venue]').trigger('click');
        expect(await wrapper.vm.isValid()).toBe(false);
        expect(wrapper.find('[data-location-error]').text()).toContain('Pick the venue from the list');
    });

    it('picking a venue fills its address, shows the map, and sends the venue', async () => {
        axios.get.mockResolvedValue({ data: [lowry] });
        const { wrapper, event } = mountStep();
        await wrapper.find('[data-choose-venue]').trigger('click');

        wrapper.findComponent({ name: 'VenueSearch' }).vm.$emit('picked', lowry);
        await flushPromises();

        expect(event.location.venue_id).toBe(9);
        expect(event.location.city).toBe('Salford');
        expect(wrapper.find('[data-venue-picked]').text()).toContain('The Lowry Hotel');
        expect(wrapper.find('[data-map]').exists()).toBe(true);
        expect(await wrapper.vm.isValid()).toBe(true);
        expect(wrapper.vm.submitData().location).toEqual(expect.objectContaining({ venue_id: 9 }));
    });

    const startNew = async (name) => {
        const step = mountStep();
        await step.wrapper.find('[data-choose-venue]').trigger('click');
        step.wrapper.findComponent({ name: 'VenueSearch' }).vm.$emit('add', name);
        await flushPromises();
        return step;
    };
    // The address is picked (as the Google search would set it), then checked against known venues.
    const pickAddress = async ({ wrapper, event }, known) => {
        Object.assign(event.location, { home: 'The Lowry Hotel', street: '50 Dearmans Place', city: 'Salford', latitude: 53.48, longitude: -2.25 });
        axios.get.mockResolvedValueOnce({ data: { venue: known } });
        wrapper.vm.$.setupState.locationSearch = false;
        await wrapper.vm.$.setupState.checkKnownVenue();
        await flushPromises();
    };

    it('a new venue is one card, added on Next with no Add button', async () => {
        const step = await startNew('Lowry Rooftop');
        expect(step.wrapper.find('[data-venue-new]').exists()).toBe(true);
        expect(await step.wrapper.vm.isValid()).toBe(false); // no address yet
        expect(step.wrapper.find('[data-location-error]').text()).toContain('address');

        await pickAddress(step, null);
        axios.post.mockResolvedValueOnce({ data: { venue: { ...lowry, id: 12, name: 'Lowry Rooftop' } } });

        expect(await step.wrapper.vm.isValid()).toBe(true);
        expect(axios.post).toHaveBeenCalledWith('/api/venues', expect.objectContaining({ name: 'Lowry Rooftop', room: null, street: '50 Dearmans Place' }));
        expect(step.event.location.venue_id).toBe(12);
    });

    it('at an address EI knows it asks "same place?", and "yes" uses the known venue', async () => {
        const step = await startNew('Lowry Rooftop');
        await pickAddress(step, lowry);

        expect(await step.wrapper.vm.isValid()).toBe(false);
        expect(step.wrapper.find('[data-location-error]').text()).toContain('same place as The Lowry Hotel');
        await step.wrapper.find('[data-venue-known] button').trigger('click');
        expect(step.event.location.venue_id).toBe(9);
        expect(axios.post).not.toHaveBeenCalled();
    });

    it('"a different space here" asks for the space\'s name and adds it as its own venue', async () => {
        const step = await startNew('Lowry Rooftop');
        await pickAddress(step, lowry);
        await step.wrapper.findAll('[data-venue-known] button')[1].trigger('click');

        expect(await step.wrapper.vm.isValid()).toBe(false);
        await step.wrapper.find('[data-venue-room]').setValue('Rooftop');
        axios.post.mockResolvedValueOnce({ data: { venue: { ...lowry, id: 12, name: 'Lowry Rooftop', room: 'Rooftop' } } });

        expect(await step.wrapper.vm.isValid()).toBe(true);
        expect(axios.post).toHaveBeenCalledWith('/api/venues', expect.objectContaining({ name: 'Lowry Rooftop', room: 'Rooftop' }));
    });

    it('dragging the map moves the pin and the saved point, for a picked address only', async () => {
        const { wrapper, event } = mountStep({ city: 'Brooklyn', street: '1 Main St', latitude: 40.6, longitude: -73.9, kind: 'place' });
        // The point read is the one under the pin: up the map (38% from the top) for somewhere else.
        const containerPointToLatLng = vi.fn(() => ({ lat: 40.61, lng: -73.91 }));
        const target = { getSize: () => ({ x: 600, y: 400 }), containerPointToLatLng };

        expect(wrapper.find('[data-drag-hint]').exists()).toBe(true);
        // The map settling by itself changes nothing.
        wrapper.vm.$.setupState.onMapMoveEnd({ target });
        expect(event.location.latitude).toBe(40.6);

        wrapper.vm.$.setupState.onMapDragStart();
        wrapper.vm.$.setupState.onMapMoveEnd({ target });
        expect(containerPointToLatLng).toHaveBeenCalledWith([300, 400 * 0.38]);
        expect(event.location.latitude).toBe(40.61);
        expect(event.location.longitude).toBe(-73.91);
    });

    it('a known venue\'s pin does not move', async () => {
        const { wrapper, event } = mountStep({ ...lowry, venue: 'The Lowry Hotel', venue_id: 9 });

        expect(wrapper.find('[data-drag-hint]').exists()).toBe(false);
        wrapper.vm.$.setupState.onMapDragStart();
        wrapper.vm.$.setupState.onMapMoveEnd({ target: { getSize: () => ({ x: 600, y: 400 }), containerPointToLatLng: () => ({ lat: 1, lng: 1 }) } });
        expect(event.location.latitude).toBe(53.48);
    });

    it('somewhere else: a place name, the Hidden location card on the map, never a venue', async () => {
        const { wrapper, event } = mountStep({ city: 'Brooklyn', street: '1 Main St', latitude: 40.6, longitude: -73.9, kind: 'place' });

        expect(wrapper.find('[data-place-name]').exists()).toBe(true);
        expect(wrapper.find('[data-hidden-location]').text()).toContain('Hidden location');
        expect(event.location.venue_id).toBeNull();
    });

    it('the link under the heading goes back to the two choices', async () => {
        const { wrapper } = mountStep({ city: 'Brooklyn', latitude: 40.6, longitude: -73.9, kind: 'place' });

        expect(wrapper.find('[data-change-mode]').text()).toBe('At a venue?');
        await wrapper.find('[data-change-mode]').trigger('click');
        expect(wrapper.find('[data-location-choice]').exists()).toBe(true);
    });

    it('sends the kind every time: "place" for somewhere else, "venue" for a venue', async () => {
        const place = mountStep({ city: 'Brooklyn', street: '1 Main St', latitude: 40.6, longitude: -73.9, kind: 'place', venue: 'Prospect Park' });
        expect(place.wrapper.vm.submitData().location).toEqual(expect.objectContaining({ kind: 'place', venue: 'Prospect Park', venue_id: null }));

        const venue = mountStep({ ...lowry, venue: 'The Lowry Hotel', venue_id: 9 });
        expect(venue.wrapper.vm.submitData().location).toEqual(expect.objectContaining({ kind: 'venue', venue_id: 9 }));
    });

    it('a listing from before the question opens on the choice, its address kept for "somewhere else"', async () => {
        const { wrapper, event } = mountStep({ city: 'Brooklyn', street: '1 Main St', latitude: 40.6, longitude: -73.9, venue: 'Old typed name' });

        expect(wrapper.find('[data-location-choice]').exists()).toBe(true);
        await wrapper.find('[data-choose-place]').trigger('click');
        expect(event.location.city).toBe('Brooklyn');
        expect(wrapper.find('[data-place-name]').element.value).toBe('Old typed name');
    });

    it('opens a saved venue on "at a venue" and a saved address on "somewhere else"', () => {
        expect(mountStep({ ...lowry, venue: 'The Lowry Hotel', venue_id: 9 }).wrapper.find('[data-venue-picked]').exists()).toBe(true);
        expect(mountStep({ city: 'Brooklyn', latitude: 40.6, longitude: -73.9, kind: 'place' }).wrapper.find('[data-place-name]').exists()).toBe(true);
    });

    it('each way keeps its own details: somewhere else starts empty, and the venue comes back', async () => {
        const { wrapper, event } = mountStep({ ...lowry, venue: 'The Lowry Hotel', venue_id: 9, kind: 'venue' });

        await wrapper.find('[data-change-mode]').trigger('click');
        await wrapper.find('[data-choose-place]').trigger('click');
        expect(event.location.venue_id).toBeNull();
        expect(event.location.venue).toBe('');
        expect(event.location.city).toBe('');

        // Type a place, then go back to the venue: the venue is there again.
        event.location.venue = 'The park';
        await wrapper.find('[data-change-mode]').trigger('click');
        await wrapper.find('[data-choose-venue]').trigger('click');
        expect(event.location.venue_id).toBe(9);
        expect(event.location.city).toBe('Salford');
        expect(wrapper.find('[data-venue-picked]').text()).toContain('The Lowry Hotel');

        // And the place typed meanwhile is kept for "Somewhere else" too.
        await wrapper.find('[data-change-mode]').trigger('click');
        await wrapper.find('[data-choose-place]').trigger('click');
        expect(event.location.venue).toBe('The park');
    });

    it('editing a live listing from before the question opens on "Somewhere else", without saying so for it', async () => {
        const old = { city: 'Brooklyn', street: '1 Main St', latitude: 40.6, longitude: -73.9, venue: 'Old typed name' };
        const { wrapper } = mountStep(old, { editingLiveListing: true });

        expect(wrapper.find('[data-place-name]').element.value).toBe('Old typed name');
        // Not chosen by hand: the kind is not sent, so the typed name stays "not said".
        expect(wrapper.vm.submitData().location).not.toHaveProperty('kind');

        // Choosing by hand says it.
        await wrapper.find('[data-change-mode]').trigger('click');
        await wrapper.find('[data-choose-place]').trigger('click');
        expect(wrapper.vm.submitData().location.kind).toBe('place');
    });
});
