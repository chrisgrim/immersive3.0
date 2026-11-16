<template>
    <!-- One address on a map, as the event Location step shows it: until there is
         an address, a search box over the map; then the address in a card with
         "Change address", and the map with its pin, which stays put while the
         map is dragged under it (Airbnb's "Drag the map to reposition the pin").
         Used by the venue editor and the add-a-venue steps. -->
    <div>
        <div v-if="!searching && hasPoint" class="mb-6 px-[2.4rem] py-[2rem] rounded-[1.2rem] border border-[#dddddd] flex items-start justify-between gap-4" data-address-card>
            <p class="min-w-0 text-[1.5rem] leading-[2.2rem] font-normal text-[#222222]">{{ addressLine }}</p>
            <button type="button" class="flex-shrink-0 text-[1.4rem] font-medium underline" @click="startSearch">Change address</button>
        </div>

        <!-- z-0: a stacking context, so map panes stay under the page's fixed bars -->
        <div class="w-full overflow-hidden rounded-3xl relative z-0" :class="searching ? 'h-[45rem]' : height">
            <div v-if="searching" class="absolute w-full top-12 z-[10000] px-6">
                <div class="relative max-w-3xl w-full m-auto">
                    <!-- The pin centred on the box alone, not on the link under it. -->
                    <div class="relative">
                    <svg class="absolute left-8 top-1/2 -translate-y-1/2 z-50 w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z" /><circle cx="12" cy="11" r="3" /></svg>
                    <input
                        v-model="query"
                        type="text"
                        autocomplete="off"
                        placeholder="Enter address"
                        class="relative z-40 w-full rounded-full py-8 pl-24 pr-8 text-[1.6rem] font-normal border border-[#dddddd] shadow-custom-6 focus:outline-none"
                        data-address-search
                        @input="predict"
                    >
                    </div>
                    <ul v-if="predictions.length" class="relative max-w-3xl w-full m-auto -mt-12 pt-14 pb-4 list-none rounded-b-3xl bg-white shadow-custom-6">
                        <li v-for="place in predictions" :key="place.place_id">
                            <button type="button" class="w-full flex items-center gap-6 px-8 py-4 text-left text-[1.5rem] hover:bg-[#f7f7f7]" @click="pick(place)">
                                <span class="w-14 h-14 flex-shrink-0 flex items-center justify-center bg-[#f2f2f2] rounded-xl">
                                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z" /><circle cx="12" cy="11" r="3" /></svg>
                                </span>
                                {{ place.description }}
                            </button>
                        </li>
                    </ul>
                    <button v-if="hasPoint" type="button" class="mt-4 ml-4 relative z-30 px-[1.4rem] py-[0.8rem] rounded-full bg-white shadow-custom-6 text-[1.4rem] font-semibold text-[#222222] hover:bg-[#f7f7f7]" @click="keepCurrent">Keep the current address</button>
                </div>
            </div>

            <l-map
                ref="mapRef"
                :zoom="15"
                :center="start"
                style="height:100%; width:100%;"
                :options="{ scrollWheelZoom: false, zoomControl: false, inertia: false }"
                @ready="settle"
                @dragstart="dragging = canDrag"
                @move="onMove"
                @moveend="onMoveEnd"
            >
                <l-tile-layer :url="TILES" />
                <!-- Not while the search box is over the map: on a phone they would overlap. -->
                <l-control-zoom v-if="!searching" position="topright" />
                <l-marker v-if="hasPoint" :lat-lng="pin" :icon="PIN_ICON" />
            </l-map>

            <p v-if="canDrag" class="absolute left-1/2 bottom-[1.6rem] -translate-x-1/2 z-[1000] pointer-events-none px-[1.6rem] py-[0.8rem] rounded-full bg-[#222222] text-white text-[1.4rem] font-medium whitespace-nowrap" data-drag-hint>
                Drag the map to reposition the pin
            </p>
        </div>
        <p v-if="error" class="mt-3 text-red-500 text-[1.4rem]">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { LMap, LTileLayer, LMarker, LControlZoom } from '@vue-leaflet/vue-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { importMapsLibrary } from '@/composables/useGoogleMaps';
import { localLanguageFor, browserSpeaks } from '@/composables/localLanguage';

const props = defineProps({
    // {home, street, city, region, region_long, country, country_long, postal_code, latitude, longitude}
    modelValue: { type: Object, required: true },
    height: { type: String, default: 'h-[36rem]' },
});
const emit = defineEmits(['update:modelValue']);

// The event Location step's tiles and red pin.
const TILES = 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU';
const PIN_ICON = L.divIcon({
    className: 'custom-div-icon',
    html: '<div style="width:30px;height:30px;position:relative"><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:25px;height:25px;background:#ff385c;border:3.5px solid white;border-radius:50%;box-shadow:0 0 0 5px #ff385c"></div></div>',
    iconSize: [30, 30],
    iconAnchor: [15, 15],
});
const NEW_YORK = { lat: 40.7127753, lng: -74.0059728 };

const hasPoint = computed(() => Boolean(Number(props.modelValue.latitude) && Number(props.modelValue.longitude)));
const start = hasPoint.value ? { lat: Number(props.modelValue.latitude), lng: Number(props.modelValue.longitude) } : NEW_YORK;

const mapRef = ref(null);
const pin = ref({ ...start });
const searching = ref(!hasPoint.value);
const query = ref('');
const predictions = ref([]);
const error = ref('');
const dragging = ref(false);
const canDrag = computed(() => !searching.value && hasPoint.value);

const addressLine = computed(() => [
    props.modelValue.home && props.modelValue.home !== props.modelValue.street ? props.modelValue.home : null,
    props.modelValue.street,
    props.modelValue.city,
    props.modelValue.country,
].filter(Boolean).join(', '));

const update = (changes) => emit('update:modelValue', { ...props.modelValue, ...changes });

// Put the point under the pin, in the middle of the map.
const settle = () => {
    const leaflet = mapRef.value?.leafletObject;
    if (!leaflet) return;
    setTimeout(() => {
        leaflet.invalidateSize();
        if (hasPoint.value) {
            pin.value = { lat: Number(props.modelValue.latitude), lng: Number(props.modelValue.longitude) };
            leaflet.setView([pin.value.lat, pin.value.lng], leaflet.getZoom(), { animate: false });
        }
    }, 100);
};

const onMove = (e) => {
    if (!canDrag.value || !dragging.value) return;
    const center = e?.target?.getCenter?.();
    if (center) pin.value = { lat: center.lat, lng: center.lng };
};

// Only a drag by hand moves the saved point, never the map settling itself.
const onMoveEnd = (e) => {
    if (!canDrag.value || !dragging.value) return;
    dragging.value = false;
    const center = e?.target?.getCenter?.();
    if (!center) return;
    pin.value = { lat: center.lat, lng: center.lng };
    update({ latitude: Number(center.lat.toFixed(8)), longitude: Number(center.lng.toFixed(8)) });
};

// The map changes height with the search box (taller while searching):
// measure it again and put the pin back in the middle.
const recenter = (point = pin.value, zoom = null) => setTimeout(() => {
    // The map settling to its new size is never a drag by hand: it moves no saved point.
    dragging.value = false;
    const leaflet = mapRef.value?.leafletObject;
    if (!leaflet || !point) return;
    leaflet.invalidateSize({ pan: false });
    leaflet.setView([point.lat, point.lng], zoom ?? leaflet.getZoom(), { animate: false });
}, 50);

const keepCurrent = () => {
    dragging.value = false;
    searching.value = false;
    recenter();
};

const startSearch = () => {
    query.value = '';
    predictions.value = [];
    dragging.value = false;
    searching.value = true;
    recenter();
};

const predict = async () => {
    error.value = '';
    if (query.value.trim().length < 3) {
        predictions.value = [];
        return;
    }
    try {
        const { AutocompleteService } = await importMapsLibrary('places');
        const result = await new AutocompleteService().getPlacePredictions({ input: query.value });
        predictions.value = result?.predictions ?? [];
    } catch {
        predictions.value = [];
    }
};

const pick = async (prediction) => {
    try {
        const { Place } = await importMapsLibrary('places');
        const fields = ['formattedAddress', 'addressComponents', 'displayName', 'location'];
        let place = new Place({ id: prediction.place_id });
        await place.fetchFields({ fields });

        // Saved in the country's own language, like a listing's address.
        const language = localLanguageFor(place.addressComponents?.find((c) => c.types?.includes('country'))?.shortText);
        if (language && !browserSpeaks(language)) {
            try {
                const local = new Place({ id: prediction.place_id, requestedLanguage: language });
                await local.fetchFields({ fields });
                place = local;
            } catch {
                // Keep the first answer rather than lose the address.
            }
        }

        const part = (type, long = false) => {
            const found = place.addressComponents?.find((c) => c.types?.includes(type));
            return (long ? found?.longText : found?.shortText) ?? found?.longText ?? '';
        };
        const lat = typeof place.location?.lat === 'function' ? place.location.lat() : place.location?.lat;
        const lng = typeof place.location?.lng === 'function' ? place.location.lng() : place.location?.lng;
        const street = place.formattedAddress?.split(', ')[0] ?? '';

        update({
            home: place.displayName?.text || street,
            street,
            city: part('locality') || part('sublocality') || part('postal_town') || part('administrative_area_level_2', true) || part('administrative_area_level_1', true),
            region: part('administrative_area_level_1'),
            region_long: part('administrative_area_level_1', true),
            postal_code: part('postal_code'),
            country: part('country'),
            country_long: part('country', true),
            latitude: lat,
            longitude: lng,
        });
        searching.value = false;
        predictions.value = [];
        pin.value = { lat, lng };
        recenter({ lat, lng }, 15);
    } catch {
        error.value = 'Could not read that address. Please try another.';
    }
};

defineExpose({ startSearch });
</script>
