<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2>Mobility Advisories</h2>
            
            <!-- Initial Wheelchair Selection -->
            <div v-if="!hasSelectedWheelchair">
                <p class="text-gray-500 font-normal mt-4">Is your event wheelchair accessible?</p>
                <div class="flex flex-row gap-8 relative mt-6">
                    <button 
                        v-for="option in wheelchairOptions" 
                        :key="option.value"
                        @click="onSelectWheelchair(option.value)"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center hover:shadow-[0_0_0_1.5px_black] hover:border-black px-12 py-8"
                        :class="{ 'border-red-500': $v?.event?.advisories?.wheelchairReady?.$error }"
                    >
                        <div class="text-left">
                            <h4 class="font-bold text-3xl">{{ option.label }}</h4>
                        </div>
                    </button>
                </div>
                <p v-if="$v?.event?.advisories?.wheelchairReady?.$error" 
                   class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                    Please select if your event is wheelchair accessible
                </p>
            </div>

            <!-- Additional Advisories Selection -->
            <div v-else class="mt-6">
                <p class="text-gray-500 font-normal">Select additional mobility restrictions</p>
                <Dropdown 
                    class="mt-4"
                    :list="mobilityAdvisoryList"
                    :creatable="true"
                    placeholder="Additional advisories"
                    @onSelect="itemSelected" 
                />
                <List 
                    class="mt-6"
                    :selections="mobilityAdvisories" 
                    @onSelect="itemRemoved"
                    :disabledItems="[]"
                />
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, onMounted, computed } from 'vue';
import { required } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import axios from 'axios';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

// Keep only needed injections
const event = inject('event');
const errors = inject('errors');

// Constants
const wheelchairOptions = [
    { value: true, label: 'Yes' },
    { value: false, label: 'No' }
];

// State
const mobilityAdvisoryList = ref([]);
const hasSelectedWheelchair = ref(false);
const wheelchairAdvisory = ref(null);
const otherAdvisories = ref([]);

// Computed
const mobilityAdvisories = computed(() => 
    wheelchairAdvisory.value ? [wheelchairAdvisory.value, ...otherAdvisories.value] : otherAdvisories.value
);

// Helpers
const createWheelchairAdvisory = (isAccessible) => ({
    id: isAccessible ? 'wheelchair-accessible' : 'not-wheelchair-accessible',
    name: isAccessible ? 'Wheelchair Accessible' : 'Not Wheelchair Accessible',
    slug: isAccessible ? 'wheelchair-accessible' : 'not-wheelchair-accessible',
    permanent: true
});

// Event handlers
const onSelectWheelchair = (isAccessible) => {
    wheelchairAdvisory.value = createWheelchairAdvisory(isAccessible);
    hasSelectedWheelchair.value = true;
    
    if (!event.advisories) {
        event.advisories = {};
    }
    
    event.advisories.wheelchairReady = isAccessible;
};

const itemSelected = (item) => {
    if (!mobilityAdvisoryList.value.find(advisory => advisory.id === item.id)) {
        mobilityAdvisoryList.value.push(item);
    }
    
    otherAdvisories.value.push(item);
    mobilityAdvisoryList.value = mobilityAdvisoryList.value.filter(advisory => 
        !otherAdvisories.value.find(selected => selected.id === advisory.id)
    );
};

const itemRemoved = (item) => {
    if (['wheelchair-accessible', 'not-wheelchair-accessible'].includes(item.slug)) {
        hasSelectedWheelchair.value = false;
        wheelchairAdvisory.value = null;
        return;
    }
    
    otherAdvisories.value = otherAdvisories.value.filter(advisory => advisory.id !== item.id);
    mobilityAdvisoryList.value.push(item);
};

// Replace handleSubmit with defineExpose
defineExpose({
    isValid: async () => {
        const isValid = await $v.value.$validate();
        console.log('Mobility Advisories validation:', {
            hasSelectedWheelchair: hasSelectedWheelchair.value,
            wheelchairReady: event.advisories?.wheelchairReady,
            mobilityAdvisoriesCount: mobilityAdvisories.value.length,
            validationError: $v.value.$error,
            isValid
        });
        return isValid;
    },
    submitData: () => {
        const data = {
            mobilityAdvisories: mobilityAdvisories.value,
            wheelchairReady: wheelchairAdvisory.value?.slug === 'wheelchair-accessible'
        };
        console.log('Submitting mobility advisories data:', data);
        return data;
    }
});

// API calls
const fetchMobilityAdvisories = async () => {
    try {
        const response = await axios.get('/api/mobilityadvisories');
        mobilityAdvisoryList.value = response.data.filter(advisory => 
            !['wheelchair-accessible', 'not-wheelchair-accessible'].includes(advisory.slug)
        );
    } catch (error) {
        console.error('Failed to fetch mobility advisories:', error);
    }
};

// Validation rules
const rules = {
    event: {
        advisories: {
            wheelchairReady: { required }
        }
    }
};

const $v = useVuelidate(rules, {
    event: {
        advisories: event.advisories || { wheelchairReady: null }
    }
});

// Lifecycle
onMounted(async () => {
    await fetchMobilityAdvisories();
    
    if (event.advisories?.wheelchairReady !== undefined) {
        onSelectWheelchair(event.advisories.wheelchairReady === 1);
        
        if (event.mobility_advisories?.length) {
            event.mobility_advisories.forEach(advisory => {
                if (!['wheelchair-accessible', 'not-wheelchair-accessible'].includes(advisory.slug)) {
                    itemSelected(advisory);
                }
            });
        }
    }
});
</script>
