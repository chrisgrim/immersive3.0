<template>
    <div class="w-full">
        <div class="relative w-full">
            <!-- First: at a venue, or somewhere else. The map waits until there is a place to show. -->
            <div class="w-full mb-10">
                <h2 class="text-black">{{ heading }}</h2>
                <!-- One link in this line back to the two choices: no extra row above the map. -->
                <p v-if="subheading || mode" class="text-[1.6rem] leading-[2.2rem] text-[#6a6a6a] font-normal mt-3">
                    {{ subheading }}
                    <button v-if="mode" type="button" class="text-[#222222] font-medium underline" data-change-mode @click="changeMode">{{ mode === 'venue' ? 'Not at a venue?' : 'At a venue?' }}</button>
                </p>
            </div>

            <!-- Airbnb's choice list: one card a row, words on the left, the picture on the right -->
            <div v-if="!mode" class="space-y-4" data-location-choice>
                <button v-for="option in MODES" :key="option.id" type="button"
                    class="w-full flex items-center justify-between gap-6 text-left px-[2.4rem] py-[2.2rem] rounded-[1.2rem] border border-[#dddddd] hover:border-[#222222] hover:shadow-[0_0_0_1px_#222222] transition"
                    :data-choose-venue="option.id === 'venue' ? '' : null"
                    :data-choose-place="option.id === 'place' ? '' : null"
                    @click="choose(option.id)">
                    <span class="min-w-0">
                        <span class="block text-[1.8rem] leading-[2.4rem] font-medium text-[#222222]">{{ option.title }}</span>
                        <span class="block mt-1 text-[1.4rem] leading-[2rem] font-normal text-[#6a6a6a]">{{ option.text }}</span>
                    </span>
                    <svg class="w-[3.6rem] h-[3.6rem] flex-shrink-0 text-[#222222]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.3"><path stroke-linecap="round" stroke-linejoin="round" :d="option.icon"/></svg>
                </button>
            </div>

            <p v-if="locationError" class="mb-6 text-red-500 text-[1.4rem]" role="alert" data-location-error>{{ locationError }}</p>

            <!-- At a venue -->
            <template v-if="mode === 'venue'">
                <!-- Adding a new one: one card with its name, the check against venues EI
                     already has at its address, and its address. Added on Next. -->
                <div v-if="addingVenue" class="mb-8 px-[2.4rem] py-[2rem] rounded-[1.2rem] border border-[#dddddd]" data-venue-new>
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-[1.2rem] font-medium uppercase tracking-wide text-[#6a6a6a]">New venue</p>
                        <button type="button" class="text-[1.4rem] font-medium underline" @click="addingVenue = false">Cancel</button>
                    </div>
                    <input v-model="newVenueName" type="text" maxlength="80" class="mt-2 w-full text-[1.8rem] leading-[2.4rem] font-medium text-[#222222] bg-transparent focus:outline-none" data-venue-new-name>

                    <p class="mt-2 text-[1.4rem] leading-[2rem] font-normal" :class="event.location.latitude ? 'text-[#6a6a6a]' : 'text-[#b0b0b0]'">
                        {{ event.location.latitude ? addressLine : 'Search its address on the map below.' }}
                        <button v-if="event.location.latitude && !locationSearch" type="button" class="ml-2 text-[#222222] font-medium underline" @click="locationSearch = true">Change address</button>
                    </p>

                    <div v-if="!locationSearch && knownAtAddress && !differentSpace" class="mt-6 pt-6 border-t border-[#ebebeb]" data-venue-known>
                        <p class="text-[1.5rem] leading-[2.2rem] text-[#222222]">We already know a venue at this address: <span class="font-medium">{{ knownAtAddress.name }}<span v-if="knownAtAddress.room">, {{ knownAtAddress.room }}</span></span>. Is it the same place?</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <button type="button" class="px-6 py-3 rounded-[0.8rem] bg-[#222222] text-white text-[1.4rem] font-medium" @click="pickVenue(knownAtAddress)">Yes, use {{ knownAtAddress.name }}</button>
                            <button type="button" class="px-6 py-3 rounded-[0.8rem] border border-[#222222] text-[1.4rem] font-medium" @click="differentSpace = true">No, a different space here</button>
                        </div>
                    </div>
                    <div v-if="differentSpace" class="mt-6 pt-6 border-t border-[#ebebeb]">
                        <p class="text-[1.4rem] text-[#6a6a6a] mb-2">Name of this space, so people can tell it from {{ knownAtAddress?.name }}</p>
                        <input v-model="roomName" type="text" maxlength="255" placeholder="Studio 2, Rooftop, Back room" class="w-full p-4 text-[1.5rem] rounded-[0.8rem] border border-[#dddddd] focus:border-[#222222] focus:outline-none" data-venue-room>
                    </div>
                </div>

                <!-- Picked: the venue, its address, and the map below -->
                <div v-else-if="event.location.venue_id" class="mb-8 px-[2.4rem] py-[2rem] rounded-[1.2rem] border border-[#dddddd] flex items-start justify-between gap-4" data-venue-picked>
                    <div class="min-w-0">
                        <p class="text-[1.8rem] leading-[2.4rem] font-medium text-[#222222]">{{ event.location.venue }}</p>
                        <p class="mt-1 text-[1.4rem] leading-[2rem] font-normal text-[#6a6a6a]">{{ addressLine }}</p>
                    </div>
                    <button type="button" class="flex-shrink-0 text-[1.4rem] font-medium underline" @click="changeVenue">Change venue</button>
                </div>

                <!-- Looking for it -->
                <venue-search v-else :initial="newVenueName" @picked="pickVenue" @add="startAdding" />
            </template>

            <!-- Somewhere else: a name for the place, then the address -->
            <template v-if="mode === 'place'">
                <input
                    v-model="event.location.venue"
                    type="text"
                    maxlength="80"
                    class="w-full mb-8 h-[5.6rem] px-6 rounded-full border border-[#dddddd] hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black focus:outline-none text-[1.6rem] font-normal transition"
                    placeholder="Name of the place (optional), e.g. Prospect Park, by the boathouse"
                    data-place-name
                >

                <!-- The address, once picked: tap to search again -->
                <div v-if="!locationSearch" class="mb-8 px-[2.4rem] py-[2rem] rounded-[1.2rem] border border-[#dddddd] flex items-start justify-between gap-4">
                    <p class="min-w-0 text-[1.5rem] leading-[2.2rem] font-normal text-[#222222]">{{ addressLine }}</p>
                    <button type="button" class="flex-shrink-0 text-[1.4rem] font-medium underline" @click="locationSearch = true">Change address</button>
                </div>
            </template>

            <!-- z-0 creates a stacking context so leaflet panes/controls stay under the fixed header/footer -->
            <div v-if="showMap" class="w-full overflow-hidden rounded-3xl relative z-0"
                :class="locationSearch ? 'h-[45rem]' : (mode === 'place' ? 'h-[46rem]' : 'h-[30rem]')">
                <div 
                    v-if="locationSearch"
                    class="absolute w-full top-12 z-[10000]">
                    <div class="max-w-3xl w-full m-auto">
                        <div class="absolute z-[1002] ml-8 mt-4">
                            <div class="w-20 h-20 flex items-center justify-center rounded-xl">
                                <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z"></path>
                                    <circle cx="12" cy="11" r="3"></circle>
                                </svg>
                            </div>
                        </div>
                        <input 
                            :class="[
                                'relative rounded-full p-10 pl-32 shadow-custom-6 w-full font-medium z-40 transition-all duration-200',
                                {
                                    'border border-red-500 focus:border-red-500 shadow-focus-error': addressInputError,
                                    'border-neutral-300 focus:shadow-none': !addressInputError
                                }
                            ]"
                            v-model="userInput"
                            placeholder="Enter Address"
                            @input="handleAddressInput"
                            @focus="dropdown=true"
                            autocomplete="false"
                            onfocus="value = ''" 
                            type="text">
                    </div>
                    <ul 
                        class="bg-white relative max-w-3xl w-full m-auto mt-[-3rem] pt-[3rem] pb-4 list-none rounded-b-3xl shadow-custom-6" 
                        v-if="dropdown">
                        <li 
                            class="pt-5 px-8 flex items-center gap-8 hover:bg-gray-300 first:border-t first:border-neutral-300" 
                            v-for="place in places"
                            :key="place.place_id"
                            @click="selectLocation(place)">
                            <div class="w-20 h-20 flex items-center justify-center bg-neutral-100 rounded-xl">
                                <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z"></path>
                                    <circle cx="12" cy="11" r="3"></circle>
                                </svg>
                            </div>
                            {{place.description}}
                        </li>
                    </ul>
                </div>
                
                <l-map 
                    ref="mapRef"
                    :key="locationSearch"
                    :zoom="map.zoom" 
                    :center="map.center" 
                    style="height:100%; width:100%;"
                    @ready="onMapReady"
                    @dragstart="onMapDragStart"
                    @move="onMapMove"
                    @moveend="onMapMoveEnd"
                    :class="{ 'initial-search': locationSearch }"
                    :options="{ scrollWheelZoom: false, zoomControl: true, inertia: false }">
                    <l-tile-layer :url="map.url" />
                    <l-marker 
                        :lat-lng="pin"
                        :icon="markerIcon">
                    </l-marker>
                </l-map> 

                <!-- At the bottom of the map, stacked so they never overlap at any width:
                     Airbnb's hint (the pin stays put, the map moves under it), then the
                     Hidden location card (Airbnb's "Show precise location"). -->
                <div class="absolute left-[1.6rem] right-[1.6rem] bottom-[1.6rem] z-[1000] flex flex-col items-center gap-[1.2rem] pointer-events-none">
                    <p v-if="canReposition" class="px-[1.6rem] py-[0.8rem] rounded-full bg-[#222222] text-white text-[1.4rem] font-medium whitespace-nowrap" data-drag-hint>
                        Drag the map to reposition the pin
                    </p>
                    <div v-if="mode === 'place' && !locationSearch" class="pointer-events-auto w-full flex items-center justify-between gap-6 px-[2.4rem] py-[2rem] rounded-[1.6rem] bg-white shadow-[0_6px_20px_rgba(0,0,0,0.2)]" data-hidden-location>
                        <div class="min-w-0">
                            <p class="text-[1.6rem] leading-[2rem] font-medium text-[#222222]">Hidden location</p>
                            <p class="mt-1 text-[1.4rem] leading-[1.8rem] font-normal text-[#6a6a6a]">Only the general area shows on the map. You tell guests the address yourself.</p>
                        </div>
                        <ToggleSwitch v-model="event.location.hiddenLocationToggle" leftLabel="No" rightLabel="Yes" />
                    </div>
                </div>
            </div>

            <!-- A hidden location: how guests learn where to go -->
            <div v-if="mode === 'place' && event.location.hiddenLocationToggle" class="mt-6">
                <p class="text-[1.4rem] text-[#6a6a6a] mb-2">How will guests learn the address?</p>
                <textarea 
                    v-model="event.location.hiddenLocation"
                    :class="[
                        'text-[1.6rem] font-normal border rounded-[1.2rem] p-4 w-full transition-all duration-200',
                        {
                            'border-red-500 focus:border-red-500 focus:shadow-focus-error': hiddenLocationError,
                            'border-[#dddddd] hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !hiddenLocationError
                        }
                    ]"
                    placeholder="For example: we email the address the day before."
                    rows="3"
                    @input="hiddenLocationError = false"
                ></textarea>
                <p v-if="hiddenLocationError" class="text-red-500 text-[1.4rem] mt-2">Please explain how guests will learn the address</p>
            </div>
        </div>
    </div>
</template>


<script setup>
import { ref, onMounted, onUnmounted, inject, computed } from 'vue';
import { LMap, LTileLayer, LMarker, LIcon } from "@vue-leaflet/vue-leaflet";
import L from "leaflet";
import 'leaflet/dist/leaflet.css'
import { RiCheckboxBlankLine, RiCheckboxLine } from "@remixicon/vue";
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';
import VenueSearch from '../Components/venue-search.vue';
import axios from 'axios';
import { importMapsLibrary } from '@/composables/useGoogleMaps';
import { localLanguageFor, browserSpeaks } from '@/composables/localLanguage';

const event = inject('event');
const errors = inject('errors');

const DEFAULT_COORDINATES = { lat: 40.7127753, lng: -74.0059728 };

const map = ref(initializeMapObject());
const autoComplete = ref(null);
const userInput = ref('');
const places = ref([]);
const dropdown = ref(false);
const locationSearch = ref(!event.location.latitude);
const hiddenLocationError = ref(false);
const addressInputError = ref(false);

const markerIcon = computed(() => {
    if (event.location.hiddenLocationToggle) {
        // Secret location icon - transparent with expanding red outline
        return L.divIcon({
            className: 'custom-div-icon secret-location',
            html: `
                <div style="position: relative; width: 150px; height: 150px;">
                    <!-- Static inner circle - transparent with red outline -->
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 70px;
                        height: 70px;
                        background: transparent;
                        border: 8.5px solid transparent;
                        border-radius: 50%;
                        box-shadow: 0 0 0 5px #ff385c;
                    "></div>
                    
                    <!-- First animated pulse ring -->
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 70px;
                        height: 70px;
                        border: 5px solid #ff385c;
                        border-radius: 50%;
                        animation: secretPulse1 3s infinite;
                        opacity: 0.7;
                    "></div>
                    
                    <!-- Second animated pulse ring (delayed) -->
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 70px;
                        height: 70px;
                        border: 5px solid #ff385c;
                        border-radius: 50%;
                        animation: secretPulse2 3s infinite;
                        animation-delay: 1.5s;
                        opacity: 0.5;
                    "></div>
                </div>
                <style>
                    @keyframes secretPulse1 {
                        0% {
                            transform: translate(-50%, -50%) scale(1);
                            opacity: 0.7;
                        }
                        100% {
                            transform: translate(-50%, -50%) scale(2);
                            opacity: 0;
                        }
                    }
                    @keyframes secretPulse2 {
                        0% {
                            transform: translate(-50%, -50%) scale(1);
                            opacity: 0.5;
                        }
                        100% {
                            transform: translate(-50%, -50%) scale(2);
                            opacity: 0;
                        }
                    }
                </style>
            `,
            iconSize: [150, 150],  // Increased size to accommodate the animation
            iconAnchor: [75, 75]   // Adjusted anchor point
        });
    } else {
        // Regular location icon
        return L.divIcon({
            className: 'custom-div-icon',
            html: `
                <div style="position: relative; width: 30px; height: 30px;">
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 25px;
                        height: 25px;
                        background: #ff385c;
                        border: 3.5px solid white;
                        border-radius: 50%;
                        box-shadow: 0 0 0 5px #ff385c;
                    "></div>
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 40px;
                        height: 40px;
                        background: rgba(255, 56, 92, 0.35);
                        border-radius: 50%;
                        animation: markerPulse 2s infinite;
                    "></div>
                </div>
                <style>
                    @keyframes markerPulse {
                        0% {
                            transform: translate(-50%, -50%) scale(1);
                            opacity: 1;
                        }
                        100% {
                            transform: translate(-50%, -50%) scale(3);
                            opacity: 0;
                        }
                    }
                </style>
            `,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
    }
});

function initializeMapObject() {
    return {
        zoom: 14,
        center: event.location.latitude 
            ? { lat: event.location.latitude, lng: event.location.longitude } 
            : DEFAULT_COORDINATES,
        url: 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU',
        attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    };
}

const initGoogleMaps = async () => {
    try {
        // Shared bootstrap loader — loads the API once and guarantees importLibrary.
        const { AutocompleteService } = await importMapsLibrary("places");
        autoComplete.value = new AutocompleteService();
    } catch (error) {
        console.error('Error initializing Google Maps Places API:', error);
        errors.value = { location: ['Error initializing location services'] };
    }
};

const updateLocations = async () => {
    if (!autoComplete.value) {
        errors.value = { location: ['Location service not available'] };
        return;
    }
    
    try {
        // Use the promise-based approach
        const result = await autoComplete.value.getPlacePredictions({ 
            input: userInput.value 
        });
        places.value = result?.predictions || [];
    } catch (error) {
        console.error('Error fetching place predictions:', error);
        places.value = [];
    }
};

const handleAddressInput = () => {
    addressInputError.value = false;
    updateLocations();
};

const mapRef = ref(null);

const onMapReady = () => {
    setTimeout(() => {
        if (mapRef.value) {
            settleView(map.value.center.lat, map.value.center.lng);
        }
    }, 100);
};

// ---- At a venue, or somewhere else ----
// What was saved decides where the step opens: a venue, or "somewhere else"
// (kind "place", or hidden, which is never a venue). A listing from before this
// question was asked opens on the choice, its address kept, so the answer is
// always the organizer's own and never a guess.
// Editing a live listing from before the question, though, opens straight on
// "Somewhere else" with its address, since it already has one. That is only a
// starting point: unless the organizer picks a way themselves, the kind is not
// sent, and the listing stays "not said" (see submitData).
const editingLiveListing = inject('editingLiveListing', false);
const legacyAddress = !event.location.venue_id && !event.location.kind && !event.location.hiddenLocationToggle && Boolean(event.location.latitude);
const mode = ref(event.location.venue_id ? 'venue'
    : ((event.location.kind === 'place' || event.location.hiddenLocationToggle || (editingLiveListing && legacyAddress)) ? 'place' : null));
// True once the organizer has chosen a way on this step (or one was saved before).
const wayChosen = ref(Boolean(event.location.venue_id || event.location.kind || event.location.hiddenLocationToggle));
const addingVenue = ref(false);
const newVenueName = ref('');
const roomName = ref('');
const knownAtAddress = ref(null);
const differentSpace = ref(false);
const proposing = ref(false);
const venueError = ref('');
// What stops this step, shown once above the choice's details.
const locationError = computed(() => venueError.value || errors.value?.location?.[0] || '');

const MODES = [
    { id: 'venue', title: 'At a venue', text: 'A theatre, bar, hotel, gallery or other space with a name.', icon: 'M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6' },
    { id: 'place', title: 'Somewhere else', text: "A park, a forest, the streets, someone's home, or a hidden place.", icon: 'M12 3l-6 9h4l-4 6h12l-4-6h4zM12 18v3' },
];

// One line of address: the place's own name (when it is not the street), street, city, country.
const addressLine = computed(() => [
    event.location.home && event.location.home !== event.location.street ? event.location.home : null,
    event.location.street,
    event.location.city,
    event.location.country,
].filter(Boolean).join(', '));

// ---- The pin: it stays in the middle and the map moves under it (Airbnb's
// "Drag the map to reposition the pin"), for an address someone picked: a
// place, or a new venue. A known venue's pin is the venue's own.
const pin = ref({ ...map.value.center });
const canReposition = computed(() => !locationSearch.value && Boolean(event.location.latitude)
    && (mode.value === 'place' || (mode.value === 'venue' && addingVenue.value)));

// Where the pin sits, as a share of the map's height from the top. Somewhere
// else has the Hidden location card over the bottom of the map, so its pin sits
// in the middle of what is left above it.
const pinHeight = computed(() => (mode.value === 'place' && !locationSearch.value ? 0.38 : 0.5));

// The spot on the map under the pin.
const pointUnderPin = (leaflet) => {
    if (!leaflet?.getSize) return null;
    const size = leaflet.getSize();
    return leaflet.containerPointToLatLng([size.x / 2, size.y * pinHeight.value]);
};

// Put a point under the pin: centre on it, then slide it up to the pin's height.
const settleView = (lat, lng) => {
    const leaflet = mapRef.value?.leafletObject;
    if (!leaflet || !lat || !lng) return;
    leaflet.invalidateSize();
    leaflet.setView([lat, lng], map.value.zoom, { animate: false });
    const offset = (0.5 - pinHeight.value) * leaflet.getSize().y;
    if (offset) leaflet.panBy([0, offset], { animate: false });
    pin.value = { lat: Number(lat), lng: Number(lng) };
};

// Only a drag by hand moves the saved point, never the map settling itself.
let dragging = false;
const onMapDragStart = () => { dragging = true; };

const onMapMove = (e) => {
    if (!canReposition.value || !dragging) return;
    const point = pointUnderPin(e?.target ?? mapRef.value?.leafletObject);
    if (point) pin.value = { lat: point.lat, lng: point.lng };
};

const onMapMoveEnd = (e) => {
    if (!canReposition.value || !dragging) return;
    dragging = false;
    const point = pointUnderPin(e?.target ?? mapRef.value?.leafletObject);
    if (!point) return;
    event.location.latitude = Number(point.lat.toFixed(8));
    event.location.longitude = Number(point.lng.toFixed(8));
    pin.value = { lat: point.lat, lng: point.lng };
};

// The step's own title once a place is set (a venue picked, or an address
// for somewhere else): coming back to it reads as this step, not a question.
const heading = computed(() => {
    if (mode.value === 'venue' && addingVenue.value) return 'Add a new venue';
    if (mode.value === 'venue' && !event.location.venue_id) return 'Which venue?';
    if (mode.value === 'place' && locationSearch.value) return 'Where is it?';
    return 'Where does it happen?';
});
const subheading = computed(() => {
    if (mode.value === 'venue' && !event.location.venue_id && !addingVenue.value) return 'Find it in the list. If it is not there yet, add it.';
    if (mode.value === 'venue' && addingVenue.value) return 'Its name and address. We check it is not a venue we already know.';
    if (mode.value === 'place' && !locationSearch.value) return 'Check the address, and drag the map if the pin is not quite right.';
    return '';
});

// The map shows once there is a place to put on it (or an address to search on it).
const showMap = computed(() => mode.value === 'place' || (mode.value === 'venue' && (Boolean(event.location.venue_id) || addingVenue.value)));

const ADDRESS_FIELDS = ['home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'latitude', 'longitude'];
const blankAddress = () => Object.fromEntries(ADDRESS_FIELDS.map((field) => [field, '']));

// Each way keeps its own details while the organizer goes back and forth:
// leaving "At a venue" remembers the venue, leaving "Somewhere else" its
// address, and choosing one again brings back what it had. A way never
// borrows the other's: "Somewhere else" never starts with the venue's address.
const KEPT_FIELDS = [...ADDRESS_FIELDS, 'venue', 'venue_id', 'hiddenLocationToggle', 'hiddenLocation'];
// Each way keeps its own address with its own timezone, so switching back
// never pairs one city's address with another's time.
const snapshot = () => ({
    fields: Object.fromEntries(KEPT_FIELDS.map((field) => [field, event.location[field] ?? null])),
    timezone: event.timezone ?? null,
});
const kept = {
    venue: mode.value === 'venue' ? snapshot() : null,
    // A listing from before the question: its address is offered to "Somewhere else".
    place: mode.value === 'place' || (mode.value === null && event.location.latitude && !event.location.venue_id) ? snapshot() : null,
};

const choose = (chosen) => {
    mode.value = chosen;
    wayChosen.value = true;
    errors.value = {};
    addingVenue.value = false;
    const restored = kept[chosen];
    if (restored) {
        Object.assign(event.location, restored.fields);
        // None kept: looked up again from the point before saving (isValid).
        event.timezone = restored.timezone;
    } else {
        Object.assign(event.location, blankAddress(), { venue: '', venue_id: null, hiddenLocationToggle: false, hiddenLocation: '' });
        event.timezone = null;
    }
    if (chosen === 'venue') {
        // A venue is never secret: a venue page would give the place away.
        event.location.hiddenLocationToggle = false;
        event.location.hiddenLocation = '';
        locationSearch.value = !event.location.venue_id;
    } else {
        event.location.venue_id = null;
        locationSearch.value = !event.location.latitude;
    }
    if (event.location.latitude && !locationSearch.value) centerOnLocation();
};

const changeMode = () => {
    // Remember this way's details before going back to the two choices.
    if (mode.value) kept[mode.value] = snapshot();
    mode.value = null;
    addingVenue.value = false;
    differentSpace.value = false;
    knownAtAddress.value = null;
    venueError.value = '';
    userInput.value = '';
};

const centerOnLocation = () => {
    map.value.center = { lat: parseFloat(event.location.latitude), lng: parseFloat(event.location.longitude) };
    pin.value = { ...map.value.center };
    setTimeout(() => settleView(map.value.center.lat, map.value.center.lng), 300);
};

// A venue picked (from the list, or the one already known at the new address):
// its address is the event's, and its timezone comes with it.
const pickVenue = (venue) => {
    ADDRESS_FIELDS.forEach((field) => { event.location[field] = venue[field]; });
    event.location.venue = venue.name;
    event.location.venue_id = venue.id;
    addingVenue.value = false;
    differentSpace.value = false;
    knownAtAddress.value = null;
    venueError.value = '';
    errors.value = {};
    locationSearch.value = false;
    userInput.value = '';
    dropdown.value = false;
    centerOnLocation();
    setTimezoneFromCoordinates(event.location.latitude, event.location.longitude);
};

const changeVenue = () => {
    newVenueName.value = event.location.venue || '';
    event.location.venue_id = null;
    Object.assign(event.location, blankAddress());
    event.location.venue = '';
    locationSearch.value = true;
};

const startAdding = (name) => {
    newVenueName.value = name;
    addingVenue.value = true;
    differentSpace.value = false;
    knownAtAddress.value = null;
    roomName.value = '';
    venueError.value = '';
    Object.assign(event.location, blankAddress());
    event.location.venue = name;
    event.location.venue_id = null;
    locationSearch.value = true;
    userInput.value = '';
};

// The new venue's address is picked: is there already a venue there?
const checkKnownVenue = async () => {
    knownAtAddress.value = null;
    differentSpace.value = false;
    const { home, street, city } = event.location;
    if (!street || !city) return;
    try {
        const response = await axios.get('/api/venues/match', { params: { home, street, city } });
        knownAtAddress.value = response.data.venue;
    } catch {
        knownAtAddress.value = null;
    }
};

// Made when Next is pressed: the new venue becomes the event's, and the screen
// stays as it is while the step moves on (no flash of the picked-venue view).
const addVenue = async () => {
    proposing.value = true;
    venueError.value = '';
    try {
        const response = await axios.post('/api/venues', {
            name: newVenueName.value.trim(),
            room: differentSpace.value ? roomName.value.trim() : null,
            ...Object.fromEntries(ADDRESS_FIELDS.map((field) => [field, event.location[field]])),
            // The listing's company keeps the new venue, not the team last selected.
            event_slug: event.slug,
        });
        const venue = response.data.venue;
        ADDRESS_FIELDS.forEach((field) => { event.location[field] = venue[field]; });
        event.location.venue = venue.name;
        event.location.venue_id = venue.id;
        venueAddFailed.value = false;
    } catch (failure) {
        venueError.value = failure.response?.status === 429
            ? 'Too many tries just now. Please wait a minute and try again.'
            : (Object.values(failure.response?.data?.errors ?? {}).flat()[0] ?? failure.response?.data?.message ?? 'That venue could not be added.');
        venueAddFailed.value = true;
    } finally {
        proposing.value = false;
    }
};
// Adding the new venue was refused or failed: Save & exit stays, so what was
// typed is not lost (see Core/index.vue).
const venueAddFailed = ref(false);

const selectLocation = async (location) => {
    // A different address takes the venue off (ApplyVenue says the same).
    event.location.venue_id = null;

    try {
        // Use the new Place class via the shared bootstrap loader.
        const { Place } = await importMapsLibrary("places");
        const fields = ["formattedAddress", "addressComponents", "displayName", "location"];
        let placeResult = new Place({ id: location.place_id });
        await placeResult.fetchFields({ fields });

        // Google answered in this browser's language; re-ask in the language
        // of the country the place is in (see localLanguage.js). If that
        // lookup fails, keep the first answer rather than lose the address.
        const countryCode = placeResult.addressComponents
            ?.find(c => c.types?.includes('country'))?.shortText;
        const language = localLanguageFor(countryCode);
        if (language && !browserSpeaks(language)) {
            try {
                const localPlace = new Place({ id: location.place_id, requestedLanguage: language });
                await localPlace.fetchFields({ fields });
                placeResult = localPlace;
            } catch (error) {
                console.error('Error fetching local-language place details:', error);
            }
        }

        setPlace(placeResult);
        userInput.value = location.description;
        dropdown.value = false;
        locationSearch.value = false;
        if (mode.value === 'venue' && addingVenue.value) {
            event.location.venue = newVenueName.value;
            checkKnownVenue();
        }
        
        // Wait to ensure the map is available
        setTimeout(() => {
            try {
                if (mapRef.value && mapRef.value.leafletObject) {
                    mapRef.value.leafletObject.invalidateSize();
                    
                    // Extract lat/lng correctly based on whether they're functions or properties
                    let lat = 0, lng = 0;
                    
                    if (placeResult.location) {
                        if (typeof placeResult.location.lat === 'function') {
                            lat = placeResult.location.lat();
                            lng = placeResult.location.lng();
                        } else {
                            lat = placeResult.location.lat;
                            lng = placeResult.location.lng;
                        }
                    }
                    
                    settleView(lat, lng);
                }
            } catch (error) {
                console.error('Error updating map view:', error);
            }
        }, 300); // Increased timeout for map to be ready
    } catch (error) {
        console.error('Error fetching place details:', error);
        errors.value = { location: ['Error retrieving location details'] };
    }
};

const setPlace = (place) => {
    // Debug: Log the full place object to see what Google is returning
    
    // Log each component for debugging
    if (place.addressComponents) {
        place.addressComponents.forEach((component, index) => {
        });
    }
    
    // Helper function to extract address components
    const getAddressComponent = (type, preferLong = false) => {
        if (place.addressComponents) {
            const component = place.addressComponents.find(component => 
                component.types && component.types.includes(type)
            );
            
            if (component) {
                
                let value = '';
                if (preferLong) {
                    // Try different properties for long name
                    value = component.longText || component.long_name || component.shortText || component.short_name || '';
                } else {
                    // Try different properties for short name
                    value = component.shortText || component.short_name || component.longText || component.long_name || '';
                }
                
                // Handle case where value might be an array
                if (Array.isArray(value)) {
                    console.warn(`Component ${type} returned array:`, value);
                    return value[0] || '';
                }
                
                // Handle case where the entire component might be malformed
                if (typeof value !== 'string') {
                    console.warn(`Component ${type} returned non-string:`, value, 'Component:', component);
                    // Try to extract from component object keys
                    const keys = Object.keys(component);
                    for (const key of keys) {
                        if (typeof component[key] === 'string' && component[key].length > 0 && !key.includes('type')) {
                            return component[key];
                        }
                    }
                    return '';
                }
                
                return value;
            }
        }
        return '';
    };

    // Get address details
    const street = place.formattedAddress?.split(', ')[0] || '';
    // Only use displayName.text since 'name' is not available
    const displayName = place.displayName?.text || street || '';
    
    // Extract lat/lng correctly - handle both function and property cases
    let lat = 0, lng = 0;
    
    if (place.location) {
        if (typeof place.location.lat === 'function') {
            // Handle function case (old API style)
            lat = place.location.lat();
            lng = place.location.lng();
        } else {
            // Handle property case (new API style)
            lat = place.location.lat;
            lng = place.location.lng;
        }
    }
    
    // Extract city, region, postal code, country properly
    const city = getAddressComponent('locality') ||
                getAddressComponent('sublocality') ||
                getAddressComponent('postal_town') ||
                getAddressComponent('administrative_area_level_2', true) ||
                getAddressComponent('administrative_area_level_1', true);
                
    const region = getAddressComponent('administrative_area_level_1'); // Short name (e.g., "CA")
    const region_long = getAddressComponent('administrative_area_level_1', true); // Long name (e.g., "California")
    const postal_code = getAddressComponent('postal_code');
    const country = getAddressComponent('country'); // Short name (e.g., "US")
    const country_long = getAddressComponent('country', true); // Long name (e.g., "United States")
    
    // Additional debugging for the location object being set
    
    const currentVenue = event.location?.venue || '';
    
    // Update the event location
    event.location = {
        latitude: lat,
        longitude: lng,
        home: displayName,
        street: street,
        city: city || '',
        region: region || '',
        region_long: region_long || '',
        postal_code: postal_code || '',
        country: country || '',
        country_long: country_long || '',
        hiddenLocationToggle: event.location?.hiddenLocationToggle || false,
        hiddenLocation: event.location?.hiddenLocation || '',
        venue: currentVenue
    };

    // Update map center
    if (lat && lng) {
        map.value.center = { lat, lng };
        pin.value = { lat, lng };
        
        // Set timezone based on coordinates
        setTimezoneFromCoordinates(lat, lng);
    }
};

// Simple function to get timezone from coordinates
const setTimezoneFromCoordinates = async (lat, lng) => {
    if (!lat || !lng) return;

    // GeoNames sometimes hangs — abort after 8s so the form doesn't sit in limbo.
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);

    try {
        // Proxied through the Laravel API so the GeoNames username doesn't ship
        // in the JS bundle. Response shape mirrors upstream — `timezoneId` etc.
        const response = await fetch(`/api/geonames/timezone?lat=${lat}&lng=${lng}`, {
            signal: controller.signal,
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();

        if (data.timezoneId) {
            event.timezone = data.timezoneId;
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Error getting timezone:', error);
        }
    } finally {
        clearTimeout(timeoutId);
    }
};

onMounted(() => {
    // Initialize Google Maps via the shared bootstrap loader (loads once,
    // guarantees google.maps.importLibrary — no manual <script>/initMap needed).
    initGoogleMaps().catch((error) => {
        console.error('Failed to initialize location services:', error);
        errors.value = { location: ['Failed to initialize location services'] };
    });
});

onUnmounted(() => {
    autoComplete.value = null;
});

defineExpose({
    keepsOnExit: () => (venueAddFailed.value ? venueError.value || 'That venue could not be added.' : null),
    isValid: async () => {
        // Reset error states
        hiddenLocationError.value = false;
        addressInputError.value = false;
        venueError.value = '';

        if (!mode.value) {
            errors.value = { location: ['Choose: at a venue, or somewhere else'] };
            return false;
        }
        // At a venue means a venue: picked from the list, or the new one, added
        // here on Next once it has a name and an address (and, at an address EI
        // already knows, the answer to "is it the same place?").
        if (mode.value === 'venue' && !event.location.venue_id && addingVenue.value) {
            const stop = !newVenueName.value.trim() ? 'Give the new venue a name.'
                : (!event.location.latitude || locationSearch.value) ? 'Search the new venue\'s address on the map.'
                : (knownAtAddress.value && !differentSpace.value) ? `Is it the same place as ${knownAtAddress.value.name}? Choose one.`
                : (differentSpace.value && !roomName.value.trim()) ? 'Name this space, so people can tell it apart.'
                : null;
            if (stop) {
                venueError.value = stop;
                return false;
            }
            await addVenue();
        }
        if (mode.value === 'venue' && !event.location.venue_id) {
            if (!venueError.value) venueError.value = 'Pick the venue from the list, or add it as a new one.';
            return false;
        }
        
        // If in search mode and no address entered
        if (locationSearch.value && !userInput.value) {
            errors.value = { location: ['Please enter and select an address'] };
            addressInputError.value = true;
            return false;
        }
        
        // A typed place name is capped at 80; a picked venue keeps its own
        // (longer) name, sent by venue_id.
        if (!event.location.venue_id && event.location.venue?.length > 80) {
            errors.value = { location: ['Venue name cannot exceed 80 characters'] };
            return false;
        }

        // Validate hidden location description if location is secret
        if (event.location.hiddenLocationToggle && !event.location.hiddenLocation) {
            errors.value = { location: ['Please explain how participants will be notified of the location'] };
            hiddenLocationError.value = true;
            return false;
        }

        const isValid = Boolean(event.location && 
                       event.location.latitude && 
                       event.location.longitude && 
                       event.location.city);
        
        if (!isValid) {
            errors.value = { location: ['Please select a valid location'] };
            // If in search mode, highlight the address input
            if (locationSearch.value) {
                addressInputError.value = true;
            }
        }

        // An address that arrived without its timezone (copied from an earlier
        // run, say): look it up from the map point before saving, or the save is refused.
        if (isValid && !event.timezone) {
            await setTimezoneFromCoordinates(event.location.latitude, event.location.longitude);
        }
        
        return isValid;
    },
            submitData: () => ({
        // A picked venue is its own address: the server copies it. The kind is
        // said every time: "place" means a typed name is a label, never a venue.
        location: event.location.venue_id ? {
            kind: 'venue',
            venue_id: event.location.venue_id,
            hiddenLocationToggle: event.location.hiddenLocationToggle,
            hiddenLocation: event.location.hiddenLocation,
        } : {
            latitude: event.location.latitude,
            longitude: event.location.longitude,
            home: event.location.home,
            street: event.location.street,
            city: event.location.city,
            region: event.location.region,
            region_long: event.location.region_long,
            postal_code: event.location.postal_code,
            country: event.location.country,
            country_long: event.location.country_long,
            venue: event.location.venue,
            venue_id: null,
            // Only when it was said: an older listing opened on "Somewhere else"
            // for editing keeps its kind unsaid, so its typed name stays a
            // candidate for a venue later.
            ...(wayChosen.value ? { kind: 'place' } : {}),
            hiddenLocationToggle: event.location.hiddenLocationToggle,
            hiddenLocation: event.location.hiddenLocation
        },
        timezone: event.timezone
    })
});
</script>

<style>
.leaflet-left {
    right: 3rem !important;
    left: auto !important;
}
.leaflet-top {
    top: 2rem;
}
.leaflet-bar {
    border: none !important;
    margin: 0 !important;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 2px 16px rgb(0 0 0 / 12%) !important;
}
.leaflet-touch .leaflet-bar a {
    width: 40px;
    height: 40px;
    line-height: 40px;
}
.leaflet-control-attribution {
    display: none;
}
.initial-search .leaflet-control-container {
    display: none;
}
</style>
