<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2 class="text-black">Mobility Advisories</h2>
            
            <!-- Initial Wheelchair Selection -->
            <div v-if="!hasSelectedWheelchair">
                <p class="font-strong mt-6">Is your event wheelchair accessible?</p>
                <div class="flex flex-row gap-8 relative mt-6">
                    <button 
                        v-for="option in WHEELCHAIR_OPTIONS" 
                        :key="option.value"
                        @click="onSelectWheelchair(option.value)"
                        class="border-neutral-300 border rounded-2xl flex justify-between items-center hover:border-[#222222] hover:shadow-focus-black transition-all duration-200 px-12 py-8"
                    >
                        <div class="text-left">
                            <p class="font-bold text-3xl">{{ option.label }}</p>
                        </div>
                    </button>
                </div>
                <!-- Add error message -->
                <p v-if="!hasSelectedWheelchair && $v.$dirty" 
                   class="text-red-500 text-1xl mt-2 py-2 leading-tight">
                    Please select whether the event is wheelchair accessible
                </p>
            </div>

            <!-- Additional Advisories Selection -->
            <div v-else class="mt-6">
                <p class="font-strong">Select additional mobility advisories or create your own.</p>
                <Dropdown 
                    class="mt-4"
                    :list="mobilityAdvisoryList"
                    :creatable="true"
                    placeholder="Additional advisories"
                    @onSelect="itemSelected"
                    :error="showAdvisoriesError"
                    :max-selections="10"
                    :max-input-length="50"
                />
                <div v-if="(hasSelectedWheelchair && !hasRequiredAdvisories && $v.hasAdditionalAdvisories.$error) || mobilityAdvisories.length >= 10" class="mt-4">
                    <p class="text-red-500 text-1xl">
                        {{ mobilityAdvisories.length >= 10 
                            ? 'Maximum of 10 mobility advisories allowed' 
                            : 'Please select at least one additional mobility advisory' 
                        }}
                    </p>
                </div>
                <List 
                    class="mt-6"
                    :item-height="'h-24'"
                    :selections="mobilityAdvisories" 
                    @onSelect="itemRemoved"
                />

            </div>
        </div>
    </main>
</template>

<script setup>
// 1. Imports
import { ref, inject, onMounted, computed } from 'vue';
import { required } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

// 2. Constants
const WHEELCHAIR_OPTIONS = [
    { value: true, label: 'Yes' },
    { value: false, label: 'No' }
];

const WHEELCHAIR_SLUGS = ['wheelchair-accessible', 'not-wheelchair-accessible'];

// 3. Injections & State
const event = inject('event');
const errors = inject('errors');

const mobilityAdvisoryList = ref([]);
const hasSelectedWheelchair = ref(false);
const wheelchairAdvisory = ref(null);
const otherAdvisories = ref([]);

// 4. Computed Properties
const mobilityAdvisories = computed(() => 
    wheelchairAdvisory.value ? [wheelchairAdvisory.value, ...otherAdvisories.value] : otherAdvisories.value
);

const hasRequiredAdvisories = computed(() => otherAdvisories.value.length > 0);

// 5. Validation Rules
const rules = {
    hasSelectedWheelchair: { 
        required: (value) => value === true || value === false 
    },
    hasAdditionalAdvisories: { 
        required: () => hasRequiredAdvisories.value 
    }
};

const $v = useVuelidate(rules, {
    hasSelectedWheelchair,
    hasAdditionalAdvisories: computed(() => hasRequiredAdvisories.value)
});

// 6. Helper Functions
const createWheelchairAdvisory = (isAccessible) => ({
    id: isAccessible ? 'wheelchair-accessible' : 'not-wheelchair-accessible',
    name: isAccessible ? 'Wheelchair Accessible' : 'Not Wheelchair Accessible',
    slug: isAccessible ? 'wheelchair-accessible' : 'not-wheelchair-accessible',
    permanent: true
});

// 7. Event Handlers
const onSelectWheelchair = (isAccessible) => {
    wheelchairAdvisory.value = createWheelchairAdvisory(isAccessible);
    hasSelectedWheelchair.value = true;
    
    event.advisories = {
        ...event.advisories || {},
        wheelchairReady: isAccessible
    };
};

const itemSelected = (item) => {
    if (mobilityAdvisories.value.length >= 10) {
        return;
    }
    otherAdvisories.value.push(item);
    mobilityAdvisoryList.value = mobilityAdvisoryList.value.filter(advisory => 
        !otherAdvisories.value.find(selected => selected.id === advisory.id)
    );
};

const itemRemoved = (item) => {
    if (WHEELCHAIR_SLUGS.includes(item.slug)) {
        hasSelectedWheelchair.value = false;
        wheelchairAdvisory.value = null;
        if (event.advisories) {
            event.advisories.wheelchairReady = null;
        }
        return;
    }
    
    otherAdvisories.value = otherAdvisories.value.filter(advisory => advisory.id !== item.id);
    mobilityAdvisoryList.value.push(item);
};

// 8. API Methods
const fetchMobilityAdvisories = async () => {
    const response = await axios.get('/api/mobilityadvisories');
    mobilityAdvisoryList.value = response.data.filter(advisory => 
        !WHEELCHAIR_SLUGS.includes(advisory.slug)
    );
};

// 9. Component API
defineExpose({
    isValid: async () => {
        $v.value.$touch();
        await $v.value.$validate();

        if (!hasSelectedWheelchair.value) {
            errors.value = { mobility: ['Please select whether the event is wheelchair accessible'] };
            return false;
        }

        if (!hasRequiredAdvisories.value) {
            errors.value = { mobility: ['Please select at least one additional mobility advisory'] };
            return false;
        }

        return true;
    },
    submitData: () => ({
        mobilityAdvisories: [...mobilityAdvisories.value].map(advisory => ({
            id: advisory.id,
            name: advisory.name,
            slug: advisory.slug
        })),
        wheelchairReady: Boolean(event.advisories?.wheelchairReady)
    })
});

// 10. Lifecycle Hooks
onMounted(async () => {
    await fetchMobilityAdvisories();
    
    if (event.advisories?.wheelchairReady !== undefined && event.advisories?.wheelchairReady !== null) {
        hasSelectedWheelchair.value = true;
        wheelchairAdvisory.value = createWheelchairAdvisory(event.advisories.wheelchairReady);
        
        if (event.mobility_advisories?.length) {
            event.mobility_advisories.forEach(advisory => {
                if (!WHEELCHAIR_SLUGS.includes(advisory.slug)) {
                    otherAdvisories.value.push(advisory);
                    mobilityAdvisoryList.value = mobilityAdvisoryList.value.filter(
                        listItem => listItem.id !== advisory.id
                    );
                }
            });
        }
    }
});

// Add computed property for error state
const showAdvisoriesError = computed(() => {
    if (mobilityAdvisories.value.length >= 10) {
        return 'Maximum of 10 mobility advisories allowed';
    }
    if (hasSelectedWheelchair.value && !hasRequiredAdvisories.value && $v.value.hasAdditionalAdvisories.$error) {
        return 'Please select at least one additional mobility advisory';
    }
    return null;
});
</script>
