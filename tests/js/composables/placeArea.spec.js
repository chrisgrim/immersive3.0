import { describe, it, expect } from 'vitest';
import { placeArea, areaFromParams, setAreaParams, keepPrediction, savedBoxCriteria } from '@/composables/placeArea.js';

const latLng = (lat, lng) => ({ lat: () => lat, lng: () => lng });
const viewport = (sw, ne) => ({ getSouthWest: () => latLng(...sw), getNorthEast: () => latLng(...ne) });

describe('placeArea', () => {
    it('gives a country its whole box', () => {
        const place = { types: ['country', 'political'], viewport: viewport([41.3, -5.1], [51.1, 9.6]) };
        expect(placeArea(place)).toEqual({ NElat: 51.1, NElng: 9.6, SWlat: 41.3, SWlng: -5.1 });
    });

    it('gives a state its box, from an old PlacesService result too', () => {
        const place = { types: ['administrative_area_level_1'], geometry: { viewport: viewport([32.5, -124.4], [42, -114.1]) } };
        expect(placeArea(place)?.SWlng).toBe(-124.4);
    });

    it('leaves a city to the circle search', () => {
        expect(placeArea({ types: ['locality', 'political'], viewport: viewport([48.8, 2.2], [48.9, 2.5]) })).toBeNull();
    });

    it('keeps the wider side of a box that crosses the date line', () => {
        const usa = { types: ['country'], viewport: viewport([18.9, 172.4], [71.5, -66.9]) };
        expect(placeArea(usa)).toMatchObject({ SWlng: -180, NElng: -66.9 });
        const newZealand = { types: ['country'], viewport: viewport([-52.6, 165.8], [-29.2, -175.1]) };
        expect(placeArea(newZealand)).toMatchObject({ SWlng: 165.8, NElng: 180 });
        const russia = { types: ['country'], viewport: viewport([41.2, 19.6], [81.9, -169]) };
        expect(placeArea(russia)).toMatchObject({ SWlng: 19.6, NElng: 180 });
    });
});

describe('search params', () => {
    it('writes the box and reads it back', () => {
        const params = new URLSearchParams('city=France&live=false');
        setAreaParams(params, { NElat: 51.1, NElng: 9.6, SWlat: 41.3, SWlng: -5.1 });
        expect(params.get('live')).toBe('true');
        expect(params.get('area')).toBe('1');
        expect(areaFromParams(params)).toEqual({ NElat: 51.1, NElng: 9.6, SWlat: 41.3, SWlng: -5.1 });
    });

    it('a city clears a box left in the URL', () => {
        const params = new URLSearchParams('area=1&NElat=1&NElng=1&SWlat=0&SWlng=0&live=true');
        setAreaParams(params, null);
        expect(params.get('live')).toBe('false');
        expect(params.has('NElat') || params.has('area')).toBe(false);
    });

    it('a dragged map (no area) is not a place box', () => {
        expect(areaFromParams(new URLSearchParams('NElat=1&NElng=1&SWlat=0&SWlng=0&live=true'))).toBeNull();
    });
});

it('hides postal codes and counties from the suggestions', () => {
    expect(keepPrediction({ types: ['postal_code'] })).toBe(false);
    expect(keepPrediction({ types: ['administrative_area_level_2'] })).toBe(false);
    expect(keepPrediction({ types: ['country'] })).toBe(true);
});

it('a recent search keeps the box that is really searched', () => {
    const box = 'NElat=1&NElng=2&SWlat=0&SWlng=1';
    expect(savedBoxCriteria(new URLSearchParams(`${box}&live=true&area=1`))).toEqual({ live: true, NElat: 1, NElng: 2, SWlat: 0, SWlng: 1, area: true });
    expect(savedBoxCriteria(new URLSearchParams(`${box}&live=true`))).toEqual({ live: true, NElat: 1, NElng: 2, SWlat: 0, SWlng: 1 });
    expect(savedBoxCriteria(new URLSearchParams(`${box}&live=false`))).toEqual({ live: false });
});
