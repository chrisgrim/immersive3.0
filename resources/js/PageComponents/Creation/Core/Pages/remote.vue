<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>How will users access your remote event?</h2>
                
                <div class="w-full mt-14">
                    <div class="w-full relative" ref="remoteLocationDrop" v-click-outside="handleClickOutside">
                        <div class="w-full relative">
                            <!-- Dropdown Arrow -->
                            <svg 
                                :class="{'rotate-90': dropdown}"
                                class="w-10 h-10 fill-black absolute z-10 right-4 top-8">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                            </svg>

                            <!-- Search Input -->
                            <input 
                                ref="searchInput"
                                class="text-2xl relative p-8 w-full border mb-12 rounded-3xl focus:rounded-t-3xl focus:rounded-b-none h-24"
                                v-model="searchTerm"
                                placeholder="Select remote Locations"
                                @input="filterRemoteLocations"
                                @focus="onDropdown"
                                autocomplete="off"
                                type="text">

                            <!-- Dropdown List -->
                            <ul 
                                class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border-[#e5e7eb] border max-h-[40rem]" 
                                v-if="dropdown">
                                <li 
                                    v-for="item in filteredRemoteLocations"
                                    :key="item.id"
                                    class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300" 
                                    @click="selectRemoteLocation(item)"
                                    @mousedown.stop.prevent
                                >
                                    {{ item.name }}
                                </li>
                            </ul>

                            <!-- Selected Locations -->
                            <div v-if="event.remotelocations && event.remotelocations.length > 0">
                                <p class="text-xl mt-8">Selected locations:</p>
                                <ul class="mt-4 flex flex-wrap gap-6 mx-0">
                                    <li 
                                        v-for="location in event.remotelocations"
                                        :key="location.id"
                                        class="border h-24 border-[#e5e7eb] flex text-[#222222] px-6 pb-4 rounded-2xl relative flex flex-col justify-end hover:border-black hover:bg-gray-100 hover:shadow-[0_0_0_1.5px_black]"
                                        @mouseenter="hoveredLocation = location.id"
                                        @mouseleave="hoveredLocation = null"
                                    >
                                        <div 
                                            @click="removeRemoteLocation(location.id)" 
                                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                                        >
                                            <component :is="hoveredLocation === location.id ? RiCloseCircleFill : RiCloseCircleLine" />
                                        </div>
                                        <span class="mt-auto">{{ location.name }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';

const event = inject('event');
const errors = inject('errors');

const searchTerm = ref('');
const dropdown = ref(false);
const remoteLocationList = ref([]);
const filteredRemoteLocations = ref([]);
const hoveredLocation = ref(null);
const remoteLocationDrop = ref(null);
const searchInput = ref(null);

const fetchRemoteLocations = async () => {
    try {
        const response = await axios.get(`/api/remotelocations`);
        remoteLocationList.value = response.data;

        // Filter out already selected remote locations
        if (event.remotelocations && event.remotelocations.length > 0) {
            const selectedIds = event.remotelocations.map(loc => loc.id);
            remoteLocationList.value = remoteLocationList.value.filter(loc => !selectedIds.includes(loc.id));
        }

        filteredRemoteLocations.value = remoteLocationList.value;
    } catch (error) {
        console.error('Failed to fetch remote locations:', error);
    }
};

const filterRemoteLocations = () => {
    const searchTermLower = searchTerm.value.toLowerCase();
    filteredRemoteLocations.value = remoteLocationList.value.filter(item => 
        item.name.toLowerCase().includes(searchTermLower)
    );
};

const onDropdown = () => {
    dropdown.value = true;
};

const closeDropdown = () => {
    dropdown.value = false;
};

const handleClickOutside = (event) => {
    const dropdownElement = remoteLocationDrop.value;
    if (dropdownElement && !dropdownElement.contains(event.target)) {
        closeDropdown();
        if (searchTerm.value) {
            searchTerm.value = '';
            filteredRemoteLocations.value = remoteLocationList.value;
        }
    }
};

const selectRemoteLocation = (item) => {
    if (!event.remotelocations) {
        event.remotelocations = [];
    }

    const alreadySelected = event.remotelocations.find(loc => loc.id === item.id);

    if (!alreadySelected) {
        event.remotelocations.push(item);
        // Remove the selected item from remoteLocationList
        remoteLocationList.value = remoteLocationList.value.filter(loc => loc.id !== item.id);
        // Update the filtered list
        filterRemoteLocations();
    }

    searchTerm.value = '';
    dropdown.value = false;
    searchInput.value.blur();  // Remove focus from the input field
};

const removeRemoteLocation = (id) => {
    const removedLocation = event.remotelocations.find(loc => loc.id === id);
    event.remotelocations = event.remotelocations.filter(loc => loc.id !== id);
    // Add the removed item back to remoteLocationList
    if (removedLocation) {
        remoteLocationList.value.push(removedLocation);
        // Update the filtered list
        filterRemoteLocations();
    }
};

// Replace handleSubmit with defineExpose
defineExpose({
    isValid: async () => {
        const isValid = event.remotelocations && event.remotelocations.length > 0;
        console.log('Remote validation:', {
            hasRemoteLocations: isValid,
            locationCount: event.remotelocations?.length || 0
        });
        return isValid;
    },
    submitData: () => {
        const data = {
            remotelocations: event.remotelocations
        };
        console.log('Submitting remote locations data:', data);
        return data;
    }
});

onMounted(fetchRemoteLocations);
</script>
