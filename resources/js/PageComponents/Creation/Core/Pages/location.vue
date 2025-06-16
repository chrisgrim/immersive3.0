<template>
    <div class="w-full">
        <div class="relative w-full">
            <div class="w-full mb-12">
                <h2 v-if="locationSearch" class="text-black">Where is your event located?</h2>
                <h2 v-else class="text-black">Does this look right?</h2>
                <p v-else class="text-gray-500 font-normal mt-4">Make sure to double check your location and address.</p>
            </div>
            
            <div 
                v-if="!locationSearch"
                class="relative h-full overflow-auto flex items-center justify-center mb-8">
                <div class="w-full bg-white rounded-3xl">
                    <div class="font-light">
                        <!-- Venue Input -->
                        <div class="px-8 py-6 border-black border border-b-0 rounded-t-3xl">
                            <input 
                                class="w-full focus:outline-none"
                                :class="{ 'text-red-500': event.location.venue?.length === 80 }"
                                placeholder="Add venue name (optional)" 
                                v-model="event.location.venue"
                                maxlength="80"
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
                </div>
            </div>

            <!-- Secret Location Controls - Moved here and always visible -->
            <div class="w-full mb-8">
                <div class="w-full flex justify-between items-center">
                    <p class="text-xl font-medium">Is your location a secret?</p>
                    <ToggleSwitch 
                        v-model="event.location.hiddenLocationToggle" 
                        leftLabel="No" 
                        rightLabel="Yes" 
                    />
                </div>
                
                <!-- Conditional textarea for location notification instructions -->
                <div v-if="event.location.hiddenLocationToggle" class="mt-6 relative">
                    <textarea 
                        v-model="event.location.hiddenLocation"
                        :class="[
                            'text-2xl font-normal border rounded-2xl p-4 w-full transition-all duration-200',
                            {
                                'border-red-500 focus:border-red-500 focus:shadow-focus-error': hiddenLocationError,
                                'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !hiddenLocationError
                            }
                        ]"
                        placeholder="Please enter how participants will be notified of the location."
                        rows="3"
                        @input="hiddenLocationError = false"
                    ></textarea>
                    <p v-if="hiddenLocationError" 
                       class="text-red-500 text-1xl mt-2 px-4">
                        Please explain how participants will be notified of the location
                    </p>
                </div>
            </div>
            
            <!-- Note about secret locations -->
            <div v-if="event.location.hiddenLocationToggle && locationSearch" class="w-full mb-8 p-4 bg-yellow-50 border border-yellow-200 rounded-2xl">
                <p class="text-gray-700">Please enter address or zipcode and we will hide the details. Multiple events with same zip code will overlap on map.</p>
            </div>


            <div class="w-full overflow-hidden rounded-3xl relative" 
                :class="{ 'h-[45rem]': locationSearch, 'h-[30rem]': !locationSearch }">
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
                    :class="{ 'initial-search': locationSearch }"
                    :options="{ scrollWheelZoom: false, zoomControl: true }">
                    <l-tile-layer :url="map.url" />
                    <l-marker 
                        :lat-lng="map.center"
                        :icon="markerIcon">
                    </l-marker>
                </l-map> 
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

const event = inject('event');
const errors = inject('errors');

// In Laravel Mix, environment variables are accessible via window
const GOOGLE_MAPS_API_KEY = 'AIzaSyBxpUKfSJMC4_3xwLU73AmH-jszjexoriw'; // Fallback to previous working key
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
    if (!window.google?.maps) {
        errors.value = { location: ['Google Maps failed to load'] };
        return;
    }
    
    try {
        // Use importLibrary for Places API
        const { AutocompleteService } = await google.maps.importLibrary("places");
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
            mapRef.value.leafletObject.invalidateSize();
            mapRef.value.leafletObject.setView(map.value.center, map.value.zoom);
        }
    }, 100);
};

const selectLocation = async (location) => {
    try {
        // Use the new Place class with importLibrary
        const { Place } = await google.maps.importLibrary("places");
        const placeResult = new Place({ id: location.place_id });
        
        // Fetch the necessary fields with correct field names
        await placeResult.fetchFields({
            fields: ["formattedAddress", "addressComponents", "displayName", "location"]
        });
        
        setPlace(placeResult);
        userInput.value = location.description;
        dropdown.value = false;
        locationSearch.value = false;
        
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
                    
                    mapRef.value.leafletObject.setView([lat, lng], map.value.zoom);
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
    // Helper function to extract address components
    const getAddressComponent = (type, preferLong = false) => {
        if (place.addressComponents) {
            const component = place.addressComponents.find(component => 
                component.types && component.types.includes(type)
            );
            
            if (component) {
                if (preferLong) {
                    // Return long name (e.g., "Canada" instead of "CA")
                    return component.longText || component.Fg || '';
                } else {
                    // Return short name (e.g., "CA" instead of "Canada")
                    return component.Fg || '';
                }
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
                getAddressComponent('postal_town');
                
    const region = getAddressComponent('administrative_area_level_1');
    const postal_code = getAddressComponent('postal_code');
    const country = getAddressComponent('country'); // Short name (e.g., "CA")
    const country_long = getAddressComponent('country', true); // Long name (e.g., "Canada")
    
    const currentVenue = event.location?.venue || '';
    
    // Update the event location
    event.location = {
        latitude: lat,
        longitude: lng,
        home: displayName,
        street: street,
        city: city || '',
        region: region || '',
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
        
        // Set timezone based on coordinates
        setTimezoneFromCoordinates(lat, lng);
    }
};

// Simple function to get timezone from coordinates
const setTimezoneFromCoordinates = async (lat, lng) => {
    if (!lat || !lng) return;
    
    try {
        const geoNamesUrl = `https://secure.geonames.org/timezoneJSON?lat=${lat}&lng=${lng}&username=chgrim`;
        const response = await fetch(geoNamesUrl);
        const data = await response.json();
        
        if (data.timezoneId) {
            event.timezone = data.timezoneId;
        }
    } catch (error) {
        console.error('Error getting timezone:', error);
    }
};

onMounted(() => {
    const loadGoogleMapsApi = () => {
        return new Promise((resolve, reject) => {
            if (window.google?.maps) {
                initGoogleMaps();
                resolve();
                return;
            }

            const script = document.createElement('script');
            const scriptUrl = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=places&v=weekly&callback=initMap`;
            
            script.src = scriptUrl;
            script.async = true;
            script.defer = true;
            
            script.onload = async () => {
                try {
                    await initGoogleMaps();
                    resolve();
                } catch (error) {
                    console.error('Error during initialization:', error);
                    errors.value = { location: ['Google Maps Places service failed to load'] };
                    reject(error);
                }
            };
            
            script.onerror = (error) => {
                console.error('Script loading error:', error);
                errors.value = { location: ['Failed to load map service'] };
                reject(error);
            };
            
            document.head.appendChild(script);
        });
    };

    loadGoogleMapsApi().catch((error) => {
        console.error('Failed to initialize location services:', error);
        errors.value = { location: ['Failed to initialize location services'] };
    });

    // Add a global initMap function to ensure callback works
    window.initMap = function() {
        // Callback function for Google Maps initialization
    };
});

onUnmounted(() => {
    autoComplete.value = null;
});

defineExpose({
    isValid: async () => {
        // Reset error states
        hiddenLocationError.value = false;
        addressInputError.value = false;
        
        // If in search mode and no address entered
        if (locationSearch.value && !userInput.value) {
            errors.value = { location: ['Please enter and select an address'] };
            addressInputError.value = true;
            return false;
        }
        
        // Validate venue length
        if (event.location.venue?.length > 80) {
            errors.value = { location: ['Venue name cannot exceed 80 characters'] };
            return false;
        }

        // Validate hidden location description if location is secret
        if (event.location.hiddenLocationToggle && !event.location.hiddenLocation) {
            errors.value = { location: ['Please explain how participants will be notified of the location'] };
            hiddenLocationError.value = true;
            return false;
        }

        const isValid = event.location && 
                       event.location.latitude && 
                       event.location.longitude && 
                       event.location.city;
        
        if (!isValid) {
            errors.value = { location: ['Please select a valid location'] };
            // If in search mode, highlight the address input
            if (locationSearch.value) {
                addressInputError.value = true;
            }
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
            country_long: event.location.country_long,
            venue: event.location.venue,
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
