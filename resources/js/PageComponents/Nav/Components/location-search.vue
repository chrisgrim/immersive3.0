<template>
    <div style="width:100%" v-click-outside="() => dropdown = false">
        <div class="w-full z-[10000]">
            <div class="w-full m-auto">
                <svg class="absolute top-8 left-8 w-8 h-8 fill-black z-[1002]">
                	<use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                </svg>
                <input 
                    ref="loc"
                    class="relative rounded-full p-7 pl-24 border border-neutral-300 w-full font-normal z-[1001] focus:border-none focus:rounded-full focus:shadow-custom-7"
                    v-model="searchInput"
                    placeholder="Search by City"
                    @input="updateLocations"
                    @focus="dropdown=true"
                    autocomplete="false"
                    onfocus="value = ''" 
                    type="text">
            </div>
            <ul 
                class="bg-white relative w-full m-auto overflow-hidden mt-8 p-8 list-none rounded-5xl shadow-custom-7" 
                v-if="dropdown">
                <li 
                    class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100" 
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
        <div id="places" />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

// Define props
const props = defineProps({
    value: {
        type: Object,
        required: true,
    }
});

// Data
const searchInput = ref(null);
const searchOptions = ref([]);
const places = ref(initializePlaces());
const city = ref(new URL(window.location.href).searchParams.get("city"));
const dropdown = ref(false);

// Methods
const updateLocations = () => {
    dropdown.value = searchInput.value.length ? true : false;
    autoComplete.getPlacePredictions({ input: searchInput.value, types: ['(cities)'] }, data => {
        places.value = data;
    });
};

const selectLocation = (location) => {
    service.getDetails({ placeId: location.place_id }, data => {
        setPlace(data);
    });
};

const setPlace = (place) => {
    saveSearchData(place);
    window.location.href = `/index/search?city=${place.name}&searchType=inPerson&live=false&lat=${place.geometry.location.lat()}&lng=${place.geometry.location.lng()}`;
};

const saveSearchData = (place) => {
    axios.post('/search/storedata', { type: 'location', name: place.name });
};

function initializePlaces() {
    return [
        { place_id: 'ChIJOwg_06VPwokRYv534QaPC8g', description: 'New York, NY, USA' },
        { place_id: 'ChIJE9on3F3HwoAR9AhGJW_fL-I', description: 'Los Angeles, CA, USA' },
        { place_id: 'ChIJIQBpAG2ahYAR_6128GcTUEo', description: 'San Francisco, CA, USA' }
    ];
}

const initGoogleMaps = () => {
    // Initialize your Google Maps services here
    autoComplete = new google.maps.places.AutocompleteService();
    service = new google.maps.places.PlacesService(document.getElementById("places"));
};

let autoComplete;
let service;

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