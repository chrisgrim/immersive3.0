<template>
    <div class="w-full">
        <div class="relative w-full">
            <div class="w-full mb-16">
                <h2 v-if="locationSearch">Where is your event located?</h2>
                <h2 v-else>Does this look right?</h2>
                <p v-if="locationSearch" class="text-gray-500 font-normal mt-4">Enter your address and select from the dropdown.</p>
                <p v-else class="text-gray-500 font-normal mt-4">Make sure to double check your location and address.</p>
            </div>
            <div 
                v-if="!locationSearch"
                class="relative h-full overflow-auto flex items-center justify-center">
                <div class="w-full bg-white rounded-3xl">
                    <div class="font-light">
                        <!-- Venue Input -->
                        <div class="px-8 py-6 border-black border border-b-0 rounded-t-3xl">
                            <input 
                                class="w-full focus:outline-none" 
                                placeholder="Add venue name (optional)" 
                                v-model="event.location.venue"
                                type="text">
                        </div>
                        <!-- Address Details (clickable) -->
                        <div 
                            class="cursor-pointer" 
                            @click="locationSearch=true">
                            <div class="px-8 py-6 border-black border border-b-0">
                                <p class="font-semibold">{{event.location.home}}</p>
                            </div>
                            <div class="px-8 py-6 border-black border-b-0 border">
                                <p class="font-semibold">{{event.location.city}}</p>
                            </div>
                            <div class="border-black border border-b-0 grid grid-cols-2 divide-x divide-solid items-center">
                                <p class="px-8 py-6 font-semibold">{{event.location.region}}</p>
                                <p class="px-8 py-6 border-black border-l-1 font-semibold">{{event.location.postal_code}}</p>
                            </div>
                            <div class="px-8 py-6 border-black border rounded-b-3xl">
                                <p class="font-semibold">{{event.location.country}}</p>
                            </div>
                        </div>
                    </div>
                    <div class="w-full flex justify-between items-center py-8">
                        <p class="text-xl">Hide specific location from users </p>
                        <div @click="toggleHiddenLocation" class="w-12 h-12 cursor-pointer flex justify-center items-center">
                            <component :is="event.location.hiddenLocationToggle ? RiCheckboxLine : RiCheckboxBlankLine" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full overflow-hidden rounded-3xl relative" 
                :class="{ 'h-[45rem]': locationSearch, 'h-[30rem]': !locationSearch }">
                <div 
                    v-if="locationSearch"
                    class="absolute w-full top-12 z-[10000]">
                    <div class="max-w-3xl w-full m-auto">
                        <img 
                            class="absolute z-[1002] w-8 mt-7 ml-8" 
                            src="/storage/images/vendor/leaflet/dist/marker-icon-2x.png">
                        <input 
                            class="relative rounded-full p-10 pl-24 shadow-custom-6 w-full font-medium z-40 border-neutral-300 focus:shadow-none"
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
                            class="py-5 px-8 flex items-center gap-8 hover:bg-gray-300 first:border-t first:border-neutral-300" 
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
                
                <l-map 
                    ref="mapRef"
                    :key="locationSearch"
                    :zoom="map.zoom" 
                    :center="map.center" 
                    style="height:100%; width:100%;"
                    @ready="onMapReady"
                    :options="{ scrollWheelZoom: false, zoomControl: true }">
                    <l-tile-layer :url="map.url" />
                    <l-marker 
                        :lat-lng="map.center"
                        :icon="icon">
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
import L from "leaflet";
import { RiCheckboxBlankLine, RiCheckboxLine } from "@remixicon/vue";

const event = inject('event');
const errors = inject('errors');

const GOOGLE_MAPS_API_KEY = 'AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw';
const DEFAULT_COORDINATES = { lat: 40.7127753, lng: -74.0059728 };

const map = ref(initializeMapObject());
const autoComplete = ref(null);
const service = ref(null);
const userInput = ref('');
const places = ref([]);
const dropdown = ref(false);
const locationSearch = ref(!event.location.latitude);

const icon = L.divIcon({
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

const initGoogleMaps = () => {
    if (!window.google?.maps?.places) {
        errors.value = { location: ['Google Maps failed to load'] };
        return;
    }
    autoComplete.value = new window.google.maps.places.AutocompleteService();
    service.value = new window.google.maps.places.PlacesService(document.createElement('div'));
};

const updateLocations = () => {
    if (!autoComplete.value) {
        errors.value = { location: ['Location service not available'] };
        return;
    }
    autoComplete.value.getPlacePredictions({ input: userInput.value }, data => {
        places.value = data || [];
    });
};

const mapRef = ref(null);

const onMapReady = () => {
    setTimeout(() => {
        if (mapRef.value) {
            mapRef.value.leafletObject.invalidateSize();
            mapRef.value.leafletObject.setView(map.value.center, map.value.zoom);
        }
    }, 100);
};

const selectLocation = async (location) => {
    if (!service.value) {
        errors.value = { location: ['Location service not available'] };
        return;
    }
    service.value.getDetails({ placeId: location.place_id }, data => {
        if (data) {
            setPlace(data);
            userInput.value = location.description;
            dropdown.value = false;
            locationSearch.value = false;
            
            setTimeout(() => {
                if (mapRef.value) {
                    mapRef.value.leafletObject.invalidateSize();
                    mapRef.value.leafletObject.setView(
                        [data.geometry.location.lat(), data.geometry.location.lng()],
                        map.value.zoom
                    );
                }
            }, 100);
        }
    });
};

const setPlace = (place) => {
    const getAddressComponent = (type) => {
        const component = place.address_components?.find(component => 
            component.types.includes(type)
        );
        return component?.long_name || component?.short_name || '';
    };

    const street = place.formatted_address?.split(', ')[0] || '';

    event.location = {
        latitude: place.geometry?.location.lat() || 0,
        longitude: place.geometry?.location.lng() || 0,
        home: place.name || street || '',
        street: street,
        city: getAddressComponent('locality') || 
              getAddressComponent('sublocality') || 
              getAddressComponent('postal_town') || '',
        region: getAddressComponent('administrative_area_level_1') || '',
        postal_code: getAddressComponent('postal_code') || '',
        country: getAddressComponent('country') || '',
        hiddenLocationToggle: event.location?.hiddenLocationToggle || false
    };

    if (place.geometry?.location) {
        map.value.center = { 
            lat: place.geometry.location.lat(), 
            lng: place.geometry.location.lng() 
        };
    }
};

const toggleHiddenLocation = () => {
    event.location.hiddenLocationToggle = !event.location.hiddenLocationToggle;
};

onMounted(() => {
    const loadGoogleMapsApi = () => {
        return new Promise((resolve, reject) => {
            if (window.google?.maps?.places) {
                initGoogleMaps();
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=places`;
            script.async = true;
            script.defer = true;
            
            script.onload = () => {
                setTimeout(() => {
                    if (window.google?.maps?.places) {
                        initGoogleMaps();
                        resolve();
                    } else {
                        errors.value = { location: ['Google Maps Places service failed to load'] };
                        reject(new Error('Places service not available'));
                    }
                }, 500);
            };
            script.onerror = (error) => {
                errors.value = { location: ['Failed to load map service'] };
                reject(error);
            };
            
            document.head.appendChild(script);
        });
    };

    loadGoogleMapsApi().catch(() => {
        errors.value = { location: ['Failed to initialize location services'] };
    });
});

onUnmounted(() => {
    autoComplete.value = null;
    service.value = null;
});

defineExpose({
    isValid: async () => {
        const isValid = event.location && 
                       event.location.latitude && 
                       event.location.longitude && 
                       event.location.city;
        
        if (!isValid) {
            errors.value = { location: ['Please select a valid location'] };
        }
        
        return isValid;
    },
    submitData: () => ({
        location: {
            latitude: event.location.latitude,
            longitude: event.location.longitude,
            home: event.location.home,
            street: event.location.street,
            city: event.location.city,
            region: event.location.region,
            postal_code: event.location.postal_code,
            country: event.location.country,
            venue: event.location.venue,
            hiddenLocationToggle: event.location.hiddenLocationToggle
        }
    })
});
</script>
