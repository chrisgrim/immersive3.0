/**
 * Countries and states in the location search.
 *
 * A city is searched as a circle around its middle (the server's 40km
 * radius). That does not work for "France" or "California": the circle
 * catches a sliver round the middle. A country or a state is searched as the
 * box Google gives for it instead, the same box search the map runs when it
 * is dragged (live=true + NElat/NElng/SWlat/SWlng). `area=1` marks the box as
 * the place's own, so the results still say "in France" rather than "in the
 * map area"; dragging the map drops it.
 */

/** Ask Google for these, so a pick can tell a country from a city. */
export const PLACE_FIELDS = ['displayName', 'formattedAddress', 'location', 'types', 'viewport'];

/**
 * Predictions: what `(cities)` gave (locality, administrative_area_level_3),
 * plus countries and states. Named outright: `(regions)` drops level 3 towns
 * and fills Google's five rows with postal codes and counties.
 */
export const PLACE_TYPES = ['locality', 'administrative_area_level_3', 'administrative_area_level_1', 'country'];

const AREA_TYPES = ['country', 'administrative_area_level_1'];

/** Postal codes and counties are noise in a "where" box. */
const HIDDEN_TYPES = ['postal_code', 'administrative_area_level_2'];

export const BOX_KEYS = ['NElat', 'NElng', 'SWlat', 'SWlng'];

/** Drop the predictions nobody searches events by. */
export function keepPrediction(prediction) {
    const types = prediction?.types || [];
    return !types.some((t) => HIDDEN_TYPES.includes(t));
}

const coord = (point, key) => (typeof point?.[key] === 'function' ? point[key]() : point?.[key]);

/**
 * The box to search for a country or a state, or null for anything else.
 * Works for the new Place (viewport) and the old PlacesService result
 * (geometry.viewport).
 */
export function placeArea(place) {
    const types = place?.types || [];
    if (!types.some((t) => AREA_TYPES.includes(t))) return null;

    const viewport = place.viewport || place.geometry?.viewport;
    const ne = viewport?.getNorthEast?.();
    const sw = viewport?.getSouthWest?.();
    const box = {
        NElat: coord(ne, 'lat'),
        NElng: coord(ne, 'lng'),
        SWlat: coord(sw, 'lat'),
        SWlng: coord(sw, 'lng'),
    };
    if (!BOX_KEYS.every((k) => Number.isFinite(box[k]))) return null;

    // A box across the date line ends further east than it starts. It keeps
    // its wider side instead, so the map opens on the right part of the
    // world: the United States keeps -180 to Maine (losing a few Aleutians),
    // New Zealand 166 to 180 (losing the Chatham Islands).
    if (box.SWlng > box.NElng) {
        if (180 - box.SWlng > box.NElng + 180) box.NElng = 180;
        else box.SWlng = -180;
    }

    // Six decimals, as the URL keeps them: a search saved from a pick and
    // one saved from the URL are then the same search (one recent row).
    BOX_KEYS.forEach((k) => { box[k] = Number(box[k].toFixed(6)); });

    return box;
}

/** The box a URL's area search carries, or null. */
export function areaFromParams(params) {
    if (params.get('area') !== '1') return null;
    const box = Object.fromEntries(BOX_KEYS.map((k) => [k, parseFloat(params.get(k))]));
    return BOX_KEYS.every((k) => Number.isFinite(box[k])) ? box : null;
}

/** Write a place search's box (or its absence) into the search params. */
export function setAreaParams(params, area) {
    BOX_KEYS.forEach((k) => params.delete(k));
    params.delete('area');
    if (area) {
        BOX_KEYS.forEach((k) => params.set(k, Number(area[k]).toFixed(6)));
        params.set('area', '1');
        params.set('live', 'true');
    } else {
        params.set('live', 'false');
    }
}

/**
 * The box part of a recent/saved search, read from the params that are
 * really searched: a country's own box (area), a dragged map, or no box.
 */
export function savedBoxCriteria(params) {
    const box = Object.fromEntries(BOX_KEYS.map((k) => [k, parseFloat(params.get(k))]));
    if (params.get('live') !== 'true' || !BOX_KEYS.every((k) => Number.isFinite(box[k]))) {
        return { live: false };
    }
    return { live: true, ...box, ...(params.get('area') === '1' ? { area: true } : {}) };
}
