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
                                :class="{ 'border-red-500': showError }"
                                class="text-2xl relative p-8 w-full border mb-12 rounded-3xl focus:rounded-t-3xl focus:rounded-b-none h-24"
                                v-model="searchTerm"
                                placeholder="Select remote Locations"
                                @input="filterRemoteLocations"
                                @focus="onDropdown"
                                autocomplete="off"
                                type="text">

                            <!-- Add Error Message -->
                            <p v-if="showError" class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                                Please select at least one remote location
                            </p>

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
import { ref, onMounted, inject, computed } from 'vue';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';
import useVuelidate from '@vuelidate/core';
import { required, minLength } from '@vuelidate/validators';

// 1. Injected Dependencies
const event = inject('event');
const errors = inject('errors');

// 2. State Management
const state = ref({
    searchTerm: '',
    dropdown: false,
    remoteLocationList: [],
    filteredRemoteLocations: [],
    hoveredLocation: null
});

const remoteLocationDrop = ref(null);
const searchInput = ref(null);

// 3. Validation Rules
const rules = {
    remotelocations: { 
        required,
        minLength: minLength(1)
    }
};

// 4. Setup Vuelidate
const $v = useVuelidate(rules, {
    remotelocations: computed(() => event.remotelocations)
});

// 5. Computed Properties
const showError = computed(() => {
    return $v.value.$dirty && $v.value.$error;
});

// 6. Methods
const fetchRemoteLocations = async () => {
    try {
        const response = await axios.get(`/api/remotelocations`);
        state.value.remoteLocationList = response.data;

        if (event.remotelocations?.length > 0) {
            const selectedIds = event.remotelocations.map(loc => loc.id);
            state.value.remoteLocationList = state.value.remoteLocationList.filter(
                loc => !selectedIds.includes(loc.id)
            );
        }

        state.value.filteredRemoteLocations = state.value.remoteLocationList;
    } catch (error) {
        errors.value = { remotelocations: ['Failed to load remote locations'] };
    }
};

const filterRemoteLocations = () => {
    const searchTermLower = state.value.searchTerm.toLowerCase();
    state.value.filteredRemoteLocations = state.value.remoteLocationList.filter(item => 
        item.name.toLowerCase().includes(searchTermLower)
    );
};

const selectRemoteLocation = (item) => {
    if (!event.remotelocations) {
        event.remotelocations = [];
    }

    if (!event.remotelocations.find(loc => loc.id === item.id)) {
        event.remotelocations.push(item);
        state.value.remoteLocationList = state.value.remoteLocationList.filter(
            loc => loc.id !== item.id
        );
        filterRemoteLocations();
        $v.value.$reset();
    }

    state.value.searchTerm = '';
    state.value.dropdown = false;
    searchInput.value?.blur();
};

const removeRemoteLocation = (id) => {
    const removedLocation = event.remotelocations.find(loc => loc.id === id);
    event.remotelocations = event.remotelocations.filter(loc => loc.id !== id);
    
    if (removedLocation) {
        state.value.remoteLocationList.push(removedLocation);
        filterRemoteLocations();
    }

    if (event.remotelocations.length === 0) {
        $v.value.$touch();
    }
};

const handleClickOutside = (event) => {
    const dropdownElement = remoteLocationDrop.value;
    if (dropdownElement && !dropdownElement.contains(event.target)) {
        state.value.dropdown = false;
        if (state.value.searchTerm) {
            state.value.searchTerm = '';
            state.value.filteredRemoteLocations = state.value.remoteLocationList;
        }
    }
};

const onDropdown = () => {
    state.value.dropdown = true;
};

// 7. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { 
                remotelocations: ['At least one remote location is required'] 
            };
        }

        return isValid;
    },
    submitData: () => ({
        remotelocations: event.remotelocations
    })
});

// 8. Lifecycle Hooks
onMounted(fetchRemoteLocations);
</script>
