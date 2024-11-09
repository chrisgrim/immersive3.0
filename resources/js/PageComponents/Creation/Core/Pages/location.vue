<template>
    <div>
        <div class="fixed overflow-auto h-[calc(100vh-8rem)] relative">
            <div class="fixed top-0 right-0 bottom-[8rem] w-screen flex items-center justify-center">
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
                    class="absolute top-0 right-0 bottom-0 z-[10000] min-w-[40rem] max-w-4xl h-full overflow-auto flex items-center justify-center">
                    <div class="w-full bg-white rounded-3xl shadow-custom-6 p-8 m-8">
                        <h4 class="mb-8 text-2xl font-semibold">Everything look right?</h4>
                        <div 
                            class="font-light" 
                            @click="locationSearch=true">
                            <div class="px-8 py-6 border-black border border-b-0 rounded-t-3xl">
                                <p>{{event.location.home}}</p>
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
                        <div class="w-full flex justify-between items-center">
                            <p class="text-xl">Hide specific location from users </p>
                            <div @click="toggleHiddenLocation" class="w-12 h-12 cursor-pointer flex justify-center items-center">
                                <component :is="event.location.hiddenLocationToggle ? RiCheckboxLine : RiCheckboxBlankLine" />
                            </div>
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
                            <img class="h-16 w-16" src="/storage/images/vendor/leaflet/dist/marker-icon-2x.png" alt="">
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
import { RiCheckboxBlankLine, RiCheckboxLine } from "@remixicon/vue";

const initializeMapObject = () => {
    return {
        zoom: 14,
        center: event.location.latitude ? { lat: event.location.latitude, lng: event.location.longitude } : { lat: 40.7127753, lng: -74.0059728 },
        url: 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU',
        attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    };
};

const autoComplete = ref(null);
const service = ref(null);

const initGoogleMaps = () => {
    if (!window.google || !window.google.maps) {
        console.error('Google Maps not loaded');
        return;
    }
    autoComplete.value = new window.google.maps.places.AutocompleteService();
    service.value = new window.google.maps.places.PlacesService(document.createElement('div'));
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
const dropdown = ref(false);
const locationSearch = ref(!event.location.latitude);

const updateLocations = () => {
    if (!autoComplete.value) {
        console.warn('AutocompleteService not initialized');
        return;
    }
    autoComplete.value.getPlacePredictions({ input: userInput.value }, data => {
        places.value = data || [];
    });
};

const selectLocation = async (location) => {
    if (!service.value) {
        console.warn('Places Service not initialized');
        return;
    }
    service.value.getDetails({ placeId: location.place_id }, data => {
        if (data) {
            setPlace(data);
            userInput.value = location.description;
            dropdown.value = false;
            locationSearch.value = false;
        }
    });
};

const setPlace = (place) => {
    // Helper function to safely get address component
    const getAddressComponent = (type) => {
        const component = place.address_components?.find(component => 
            component.types.includes(type)
        );
        return component?.long_name || component?.short_name || '';
    };

    // Get the street address (first line of formatted address or empty string)
    const street = place.formatted_address?.split(', ')[0] || '';

    // Update the event location with fallbacks
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

    // Update map center if coordinates are available
    if (place.geometry?.location) {
        map.value.center = { 
            lat: place.geometry.location.lat(), 
            lng: place.geometry.location.lng() 
        };
    }
};

const toggleHiddenLocation = () => {
    event.location.hiddenLocationToggle = !event.location.hiddenLocationToggle;
    console.log("hiddenLocationToggle:", event.location.hiddenLocationToggle);
};

onMounted(() => {
    const loadGoogleMapsApi = () => {
        return new Promise((resolve, reject) => {
            if (window.google && window.google.maps) {
                initGoogleMaps();
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js' +
                '?key=AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw' +
                '&libraries=places' +
                '&loading=async';
            
            script.async = true;
            script.defer = true;
            
            script.onload = () => {
                // Wait a brief moment to ensure Google Maps is fully initialized
                setTimeout(() => {
                    initGoogleMaps();
                    resolve();
                }, 100);
            };
            script.onerror = (error) => reject(error);
            
            document.head.appendChild(script);
        });
    };

    loadGoogleMapsApi()
        .catch(error => {
            console.error('Error loading Google Maps API:', error);
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
        
        console.log('Location validation:', {
            hasLocation: !!event.location,
            hasCoordinates: !!(event.location?.latitude && event.location?.longitude),
            hasCity: !!event.location?.city,
            isValid
        });
        
        return isValid;
    },
    submitData: () => {
        const data = {
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
        };
        console.log('Submitting location data:', data);
        return data;
    }
});
</script>

