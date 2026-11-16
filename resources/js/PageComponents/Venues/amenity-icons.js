/**
 * The icon for each venue tile (resources/data/venue-amenities.json holds the
 * keys, labels and groups). Imported one by one so only these icons ship.
 */
import {
    RiWheelchairLine, RiDoorOpenLine, RiEmpathizeLine, RiArrowUpDownLine, RiVolumeUpLine,
    RiParkingBoxLine, RiParkingLine, RiCarLine, RiTrainLine, RiBikeLine,
    RiGobletLine, RiRestaurantLine, RiCupLine,
    RiShirtLine, RiLock2Line, RiArmchairLine, RiSnowflakeLine, RiFireLine, RiTreeLine,
    RiParentLine, RiShieldUserLine, RiBeerLine, RiCheckLine,
} from '@remixicon/vue';
import catalog from '../../../data/venue-amenities.json';

export const AMENITY_ICONS = {
    wheelchair_accessible: RiWheelchairLine,
    step_free_entrance: RiDoorOpenLine,
    accessible_toilets: RiEmpathizeLine,
    lift: RiArrowUpDownLine,
    hearing_loop: RiVolumeUpLine,
    free_parking: RiParkingBoxLine,
    paid_parking: RiParkingLine,
    street_parking: RiCarLine,
    public_transit: RiTrainLine,
    bike_parking: RiBikeLine,
    bar: RiGobletLine,
    food: RiRestaurantLine,
    alcohol_free: RiCupLine,
    coat_check: RiShirtLine,
    lockers: RiLock2Line,
    seating: RiArmchairLine,
    air_conditioning: RiSnowflakeLine,
    heating: RiFireLine,
    outdoor_space: RiTreeLine,
    all_ages: RiParentLine,
    age_18: RiShieldUserLine,
    age_21: RiBeerLine,
};

// Each group: its tiles (with an icon) and more to pick from a list.
export const AMENITY_GROUPS = catalog.groups.map((group) => ({
    ...group,
    amenities: catalog.amenities.filter((amenity) => amenity.group === group.key && !amenity.more),
    more: catalog.amenities.filter((amenity) => amenity.group === group.key && amenity.more),
}));

export const AMENITY_LABELS = Object.fromEntries(catalog.amenities.map((amenity) => [amenity.key, amenity.label]));
const AMENITY_GROUP_OF = Object.fromEntries(catalog.amenities.map((amenity) => [amenity.key, amenity.group]));

// What a venue has is a known key or its own words, {group, label}.
export const amenityLabel = (item) => (typeof item === 'string' ? AMENITY_LABELS[item] : item?.label) || null;
export const amenityGroup = (item) => (typeof item === 'string' ? AMENITY_GROUP_OF[item] : item?.group) || null;
export const amenityIcon = (item) => (typeof item === 'string' && AMENITY_ICONS[item]) || RiCheckLine;
