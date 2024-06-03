<template>
    <div>
        <div class="fixed overflow-auto h-full relative">
            <div class="fixed top-0 right-0 bottom-0 w-[calc(100vw-25.2rem)]">
                <div 
                    v-if="locationSearch"
                    class="absolute w-full top-12 z-[10000]">
                    <div class="max-w-3xl w-full m-auto">
                        <img 
                            class="absolute z-[1002] w-8 mt-7 ml-8" 
                            src="/storage/images/vendor/leaflet/dist/marker-icon-2x.png">
                        <input 
                            class="relative rounded-full p-10 pl-24 shadow-custom-6 w-full font-medium z-[1001] focus:rounded-3xl focus:shadow-none"
                            v-model="userInput"
                            placeholder="Enter Address"
                            @input="updateLocations"
                            @focus="dropdown=true"
                            autocomplete="false"
                            onfocus="value = ''" 
                            type="text">
                    </div>
                    <ul 
                        class="bg-white relative max-w-3xl w-full m-auto mt-[-3rem] pt-[3rem] list-none rounded-b-3xl shadow-custom-6" 
                        v-if="dropdown">
                        <li 
                            class="py-6 px-8 flex items-center gap-8 hover:bg-gray-300" 
                            v-for="place in places"
                            :key="place.place_id"
                            @click="selectLocation(place)">
                            <img 
                                class="w-8" 
                                src="/storage/images/vendor/leaflet/dist/marker-icon-2x.png">
                            {{place.description}}
                        </li>
                    </ul>
                </div>
                <div 
                    v-else
                    class="absolute top-0 right-0 z-[10000] min-w-[35rem] max-w-3xl h-full overflow-auto">
                    <div class="bg-white rounded-3xl shadow-custom-6 p-8 m-8">
                        <h4 class="mb-8 text-2xl font-semibold">Everything look right?</h4>
                        <div 
                            class="font-light" 
                            @click="locationSearch=true">
                            <div class="px-8 py-6 border-black border border-b-0 rounded-t-3xl">
                                <p>{{event.location.home}} {{event.location.street}}</p>
                            </div>
                            <div class="px-8 py-6 border-black border-b-0 border">
                                <p>{{event.location.city}}</p>
                            </div>
                            <div class="border-black border border-b-0 grid grid-cols-2 divide-x divide-solid items-center">
                                <p class="px-8 py-6">{{event.location.region}}</p>
                                <p class="px-8 py-6 border-black border-l-1">{{event.location.postal_code}}</p>
                            </div>
                            <div class="px-8 py-6 border-black border rounded-b-3xl mb-8">
                                <p>{{event.location.country}}</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-8 text-2xl font-semibold">Venue Name (optional)</h4>
                            <input 
                                class="p-8 border border-black rounded-3xl mb-8" 
                                placeholder="Venue" 
                                v-model="event.location.venue"
                                type="text">
                        </div>
                        <div class="flex justify-between items-center">
                            <p class="text-xl">Hide specific location from users </p>
                            <input 
                                class="w-12 h-12 border border-black rounded-lg" 
                                v-model="event.location.hiddenLocation"
                                type="checkbox">
                        </div>
                    </div>
                </div>
                <l-map 
                    :zoom="map.zoom" 
                    :center="map.center" 
                    style="height:100%;"
                    :options="{ scrollWheelZoom: false, zoomControl: true }">
                    <l-tile-layer :url="map.url" />
                    <l-marker 
                        :icon="icon"
                        :lat-lng="map.center">
                        <l-icon
                            :iconSize="[25, 40]"
                            :iconAnchor="[0,40]">
                            <img src="/storage/images/vendor/leaflet/dist/marker-icon-2x.png" alt="">
                        </l-icon>
                    </l-marker>
                </l-map> 
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, inject } from 'vue';
import "leaflet/dist/leaflet.css";
import { LMap, LTileLayer, LMarker, LIcon } from "@vue-leaflet/vue-leaflet";
import L from "leaflet"; // Import Leaflet

const initializeMapObject = () => {
    return {
        zoom: 14,
        center: event.location.latitude ? { lat: event.location.latitude, lng: event.location.longitude } : { lat: 40.7127753, lng: -74.0059728 },
        url: 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU',
        attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    };
};

const initGoogleMaps = () => {
    autoComplete = new google.maps.places.AutocompleteService();
    service = new google.maps.places.PlacesService(document.getElementById("places"));
};

const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

const map = ref(initializeMapObject());
const icon = L.icon({
    iconUrl: '/storage/images/vendor/leaflet/dist/marker-icon.png',
    iconSize: [32, 37],
    iconAnchor: [16, 37],
});
const userInput = ref('');
const places = ref([]);
const newLoc = ref('');
const dropdown = ref(false);
const locationSearch = ref(!event.location.latitude);

let autoComplete;
let service;

const updateLocations = () => {
    autoComplete.getPlacePredictions({ input: userInput.value }, data => {
        places.value = data;
    });
};

const selectLocation = async (location) => {
    service.getDetails({ placeId: location.place_id }, data => {
        setPlace(data);
    });
    userInput.value = location.description;
    dropdown.value = false;
    locationSearch.value = false;
};

const setPlace = (place) => {
    // Update the event location here based on the place data
    event.location = {
        latitude: place.geometry.location.lat(),
        longitude: place.geometry.location.lng(),
        home: place.name,
        street: place.formatted_address.split(', ')[0],
        city: place.address_components.find(component => component.types.includes('locality')).long_name,
        region: place.address_components.find(component => component.types.includes('administrative_area_level_1')).short_name,
        postal_code: place.address_components.find(component => component.types.includes('postal_code')).long_name,
        country: place.address_components.find(component => component.types.includes('country')).long_name,
    };
};

onMounted(() => {
    let script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw&libraries=places&callback=initMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);

    window.initMap = initGoogleMaps; // Ensure this is bound correctly
});

onUnmounted(() => {
    // Clean up if necessary
    if (window.initMap) {
        delete window.initMap; // Remove the global callback function when the component is destroyed
    }
});
</script>
