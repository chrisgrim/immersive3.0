<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2 class="text-black">Mobility Advisories</h2>
            
            <!-- Initial Wheelchair Selection -->
            <div v-if="!hasSelectedWheelchair">
                <p class="font-strong mt-6">Is your event wheelchair accessible?</p>
                <div class="flex flex-row flex-wrap gap-8 relative mt-6">
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
                    :max-selections="16"
                    :max-input-length="50"
                />
                <div v-if="(hasSelectedWheelchair && !hasRequiredAdvisories && $v.hasAdditionalAdvisories.$error) || mobilityAdvisories.length >= 16" class="mt-4">
                    <p class="text-red-500 text-1xl">
                        {{ mobilityAdvisories.length >= 16 
                            ? 'Maximum of 16 mobility advisories allowed' 
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

                <!-- Wheelchair Description (anything short of full access) -->
                <div v-if="needsDescription" class="mt-12">
                    <p class="text-neutral-500 font-normal mb-4">Explain what is and is not wheelchair accessible</p>
                    <textarea 
                        v-model="event.advisories.wheelchairDescription"
                        @input="handleDescriptionInput"
                        class="w-full p-4 text-2.5xl md:text-1xl border border-neutral-300 rounded-2xl relative outline-none transition-all duration-200 hover:border-[#222222]"
                        :class="{ 
                            'border-red-500 focus:border-red-500 focus:shadow-focus-error': showDescriptionError,
                            'focus:border-[#222222] focus:shadow-focus-black': !showDescriptionError 
                        }"
                        rows="4"
                    ></textarea>
                    <div class="flex justify-end mt-1 relative text-neutral-500">
                        {{ event.advisories.wheelchairDescription?.length || 0 }}/1000
                        <p v-if="showDescriptionError" 
                           class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                            Please explain the wheelchair access
                        </p>
                    </div>
                </div>
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
import { wheelchairLevel } from '@/composables/wheelchairAccess';

// 2. Constants
const WHEELCHAIR_OPTIONS = [
    { value: 'full', label: 'Fully accessible' },
    { value: 'partial', label: 'Partially accessible' },
    { value: 'none', label: 'Not accessible' }
];

// The automatic chip each answer adds (same names as Advisory::WHEELCHAIR_CHIPS).
const WHEELCHAIR_CHIPS = {
    full: { name: 'Wheelchair Accessible', slug: 'wheelchair-accessible' },
    partial: { name: 'Partially Wheelchair Accessible', slug: 'partially-wheelchair-accessible' },
    none: { name: 'Not Wheelchair Accessible', slug: 'not-wheelchair-accessible' }
};

const WHEELCHAIR_SLUGS = Object.values(WHEELCHAIR_CHIPS).map(chip => chip.slug);


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

const needsDescription = computed(() => 
    hasSelectedWheelchair.value && event.advisories?.wheelchairAccess !== 'full'
);

const descriptionTouched = ref(false);

const showDescriptionError = computed(() => 
    needsDescription.value && descriptionTouched.value && !event.advisories.wheelchairDescription?.trim()
);

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
const createWheelchairAdvisory = (level) => ({
    id: WHEELCHAIR_CHIPS[level].slug,
    name: WHEELCHAIR_CHIPS[level].name,
    slug: WHEELCHAIR_CHIPS[level].slug,
    permanent: true
});

// 7. Event Handlers
const onSelectWheelchair = (level) => {
    wheelchairAdvisory.value = createWheelchairAdvisory(level);
    hasSelectedWheelchair.value = true;
    
    event.advisories = {
        ...event.advisories || {},
        wheelchairAccess: level,
        wheelchairReady: level === 'full',
        wheelchairDescription: level === 'full' ? null : ''
    };

    // Like the sexual content question: ask for the explanation right away.
    descriptionTouched.value = level !== 'full';
};

const handleDescriptionInput = () => {
    descriptionTouched.value = true;
    if (event.advisories.wheelchairDescription?.length > 1000) {
        event.advisories.wheelchairDescription = event.advisories.wheelchairDescription.slice(0, 1000);
    }
};

const itemSelected = (item) => {
    if (mobilityAdvisories.value.length >= 16) {
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
        descriptionTouched.value = false;
        if (event.advisories) {
            event.advisories.wheelchairAccess = null;
            event.advisories.wheelchairReady = null;
            event.advisories.wheelchairDescription = null;
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

        if (needsDescription.value && !event.advisories.wheelchairDescription?.trim()) {
            descriptionTouched.value = true;
            errors.value = { mobility: ['Please explain the wheelchair access'] };
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
        wheelchairAccess: event.advisories?.wheelchairAccess,
        wheelchairDescription: event.advisories?.wheelchairAccess === 'full'
            ? null
            : event.advisories?.wheelchairDescription?.trim()
    })
});

// 10. Lifecycle Hooks
onMounted(async () => {
    await fetchMobilityAdvisories();
    
    const level = wheelchairLevel(event.advisories);
    if (level) {
        event.advisories.wheelchairAccess = level;
        hasSelectedWheelchair.value = true;
        wheelchairAdvisory.value = createWheelchairAdvisory(level);
        
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
    if (mobilityAdvisories.value.length >= 16) {
        return 'Maximum of 16 mobility advisories allowed';
    }
    if (hasSelectedWheelchair.value && !hasRequiredAdvisories.value && $v.value.hasAdditionalAdvisories.$error) {
        return 'Please select at least one additional mobility advisory';
    }
    return null;
});
</script>
