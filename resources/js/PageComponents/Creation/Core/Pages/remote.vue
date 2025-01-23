<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2>How will users access your remote event?</h2>
            
            <div class="mt-6">
                <p class="font-strong">Select or create remote locations for your event.</p>
                <Dropdown 
                    class="mt-4"
                    :list="state.remoteLocationList"
                    :creatable="true"
                    :loading="state.isLoading"
                    placeholder="Select remote locations"
                    @onSelect="itemSelected"
                    @onSearch="term => fetchRemoteLocations(term)"
                    :error="showDropdownError"
                    :max-selections="10"
                    :max-input-length="50"
                />
                <div v-if="showError" class="mt-4">
                    <p class="text-red-500 text-1xl">
                        Please select at least one remote location
                    </p>
                </div>
                <div v-if="showMaxError" class="mt-4">
                    <p class="text-red-500 text-1xl">
                        Maximum of 10 remote locations allowed
                    </p>
                </div>
                <List 
                    class="mt-6"
                    :item-height="'h-24'"
                    :selections="event.remotelocations || []" 
                    @onSelect="itemRemoved"
                />

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
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

// Injected Dependencies
const event = inject('event');
const errors = inject('errors');

// State Management
const state = ref({
    remoteLocationList: [],
    searchTerm: '',
    isLoading: false
});

// Validation Rules
const rules = {
    remotelocations: { 
        required,
        minLength: minLength(1)
    }
};

const $v = useVuelidate(rules, {
    remotelocations: computed(() => event.remotelocations)
});

// Computed Properties
const showError = computed(() => {
    return $v.value.$dirty && $v.value.$error;
});

const showMaxError = computed(() => {
    return event.remotelocations?.length >= 10;
});

const showDropdownError = computed(() => {
    if (event.remotelocations?.length >= 10) {
        return 'Maximum of 10 remote locations allowed';
    }
    if ($v.value.$dirty && $v.value.$error) {
        return 'Please select at least one remote location';
    }
    return null;
});

// Methods
const fetchRemoteLocations = async (search = '') => {
    try {
        state.value.isLoading = true;
        const response = await axios.get('/api/remotelocations', {
            params: {
                search,
                selected: event.remotelocations?.map(loc => loc.id) || [],
                ...(search === '' && { limit: 10 })
            }
        });
        
        state.value.remoteLocationList = response.data;
    } catch (error) {
        errors.value = { remotelocations: ['Failed to load remote locations'] };
    } finally {
        state.value.isLoading = false;
    }
};

const itemSelected = (item) => {
    if (!event.remotelocations) {
        event.remotelocations = [];
    }
    
    // Check if item already exists (by id or name)
    const exists = event.remotelocations.some(loc => 
        loc.id === item.id || loc.name.toLowerCase() === item.name.toLowerCase()
    );
    
    if (!exists) {
        event.remotelocations.push(item);
        state.value.remoteLocationList = state.value.remoteLocationList.filter(loc => 
            loc.id !== item.id && loc.name.toLowerCase() !== item.name.toLowerCase()
        );
        $v.value.$reset();
    }
};

const itemRemoved = (item) => {
    event.remotelocations = event.remotelocations.filter(loc => loc.id !== item.id);
    state.value.remoteLocationList.push(item);
    
    if (event.remotelocations.length === 0) {
        $v.value.$touch();
    }
};

// Component API
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

// Lifecycle Hooks
onMounted(fetchRemoteLocations);
</script>
