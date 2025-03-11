<template>
    <div class="relative text-1xl font-medium w-full h-screen flex flex-col">
        <!-- Top Navigation Bar -->
        <div class="flex-none h-24">
            <div class="mx-auto p-8 md:p-16 h-full flex justify-between items-center">
                <!-- Left: EI Logo/Link -->
                <a href="/hosting/events" class="text-5xl font-bold hover:opacity-70">
                    EI
                </a>

                <!-- Right: Questions & Exit -->
                <div class="flex items-center gap-6">
                    <button class="px-6 py-3 text-black hover:bg-gray-100 rounded-lg">
                        Questions
                    </button>
                    <a 
                        href="/hosting/events" 
                        class="px-6 py-3 border border-black hover:bg-gray-100 rounded-lg"
                    >
                        Exit
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Area (with proper scrolling) -->
        <div class="flex-1 overflow-y-auto">
            <div class="max-w-screen-xl mx-auto min-h-full flex">
                <div :class="['w-full mx-auto pt-40 md:pb-20', containerWidthClass]">
                    <div class="h-full flex p-8">
                        <component :is="currentComponent" ref="currentComponentRef" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation (fixed at bottom) -->
        <div class="flex-none">
            <!-- Progress Bar -->
            <div class="w-full h-[5px] bg-gray-200">
                <div 
                    class="h-full bg-black transition-all duration-300" 
                    :style="{ width: `${progress}%` }"
                />
            </div>

            <!-- Navigation Controls -->
            <div class="flex justify-between items-center px-16 py-8 mx-auto">
                <button 
                    v-if="!isFirstStep && currentStep !== 'EventType'"
                    @click="goToPrevious"
                    class="px-6 py-3 text-black hover:bg-gray-100 rounded-lg"
                >
                    Back
                </button>
                <div v-else class="w-[88px]"></div> <!-- Spacer -->

                <button 
                    @click="goToNext"
                    :disabled="isSubmitting || !isComponentReady"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors flex items-center gap-2': true,
                        'bg-black text-white hover:bg-gray-800': !isSubmitting,
                        'bg-gray-300 text-gray-500 cursor-not-allowed': isSubmitting
                    }"
                >
                    <svg 
                        v-if="isSubmitting"
                        class="animate-spin h-5 w-5" 
                        xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24"
                    >
                        <circle 
                            class="opacity-25" 
                            cx="12" 
                            cy="12" 
                            r="10" 
                            stroke="currentColor" 
                            stroke-width="4"
                        />
                        <path 
                            class="opacity-75" 
                            fill="currentColor" 
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                    {{ isSubmitting ? (isLastStep ? 'Finishing...' : 'Saving...') : (isLastStep ? 'Submit' : 'Next') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed, onMounted } from 'vue';
import EventType from './Pages/event-type.vue';
import Category from './Pages/category.vue';
import Genres from './Pages/genres.vue';
import Location from './Pages/location.vue';
import Remote from './Pages/remote.vue'; // Import Remote component
import Description from './Pages/description.vue';
import Name from './Pages/name.vue';
import Dates from './Pages/dates.vue';
import Tickets from './Pages/tickets.vue';
import Images from './Pages/images.vue';
import Advisories from './Pages/advisories.vue';
import Content from './Pages/Advisories/content.vue';
import Mobility from './Pages/Advisories/mobility.vue';
import Review from './Pages/review.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
});

const event = reactive(props.event);
const user = reactive(props.user);
const currentStep = ref('EventType');
const isSubmitting = ref(false);
const errors = ref({});
const currentComponentRef = ref(null);


const stepsWithLocation = ['EventType', 'Category', 'Genres', 'Location', 'Description', 'Name', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility', 'Review'];
const stepsWithoutLocation = ['EventType', 'Category', 'Genres', 'Remote', 'Description', 'Name', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility', 'Review'];

const components = {
    EventType,
    Category,
    Genres,
    Location,
    Remote, // Add Remote component
    Description,
    Name,
    Dates,
    Tickets,
    Images,
    Advisories,
    Content,
    Mobility,
    Review  // Add this line
};

const steps = computed(() => event.hasLocation ? stepsWithLocation : stepsWithoutLocation);

const currentComponent = computed(() => components[currentStep.value]);

const currentStepIndex = computed(() => steps.value.indexOf(currentStep.value));
const isFirstStep = computed(() => currentStepIndex.value === 0);
const isLastStep = computed(() => currentStepIndex.value === steps.value.length - 1);
const progress = computed(() => ((currentStepIndex.value + 1) / steps.value.length) * 100);
const isComponentReady = ref(true); // Default to true

const containerWidthClass = computed(() => {
    const componentName = currentComponent.value.__name?.toLowerCase();
    
    if (componentName === 'dates') return 'lg:w-full';
    if (componentName === 'images') return 'lg:w-2/3';
    return 'lg:w-1/2';
});

const goToPrevious = () => {
    if (!isFirstStep.value) {
        const prevStep = steps.value[currentStepIndex.value - 1];
        setStep(prevStep);
    }
};

// Step mapping with numeric values for easy comparison
const STEP_MAP = {
    'EventType': '1',
    'Category': '2',
    'Genres': '3',
    'Location': '4',
    'Remote': '4',
    'Description': '5',
    'Name': '6',
    'Dates': '7',
    'Tickets': '8',
    'Images': '9',
    'Advisories': 'A',
    'Content': 'B',
    'Mobility': 'C',
    'Review': 'D'
};

provide('setComponentReady', (ready) => {
    isComponentReady.value = ready;
});

// Reverse mapping for initialization
const REVERSE_STEP_MAP = Object.fromEntries(
    Object.entries(STEP_MAP).map(([key, value]) => [value, key])
);

const submitEvent = async () => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(`/hosting/event/${event.slug}/submit`);
        window.location.href = '/hosting/events?submitted=' + encodeURIComponent(event.name);
    } catch (error) {
        console.error('Submission error:', error);
    } finally {
        isSubmitting.value = false;
    }
};

const goToNext = async () => {
    try {
        const isValid = await currentComponentRef.value.isValid();
        if (!isValid) return;

        if (isLastStep.value) {
            await submitEvent();
            return;
        }

        const submitData = await currentComponentRef.value.submitData();
        console.log(submitData);
        if (!event.status || (event.status !== 'p' && event.status !== 'e')) {
            const currentStepValue = STEP_MAP[currentStep.value];
            const existingStepValue = event.status || '0';
            
            submitData.status = currentStepValue > existingStepValue 
                ? currentStepValue 
                : existingStepValue;
        }

        isSubmitting.value = true;
        const response = await axios.post(`/api/hosting/event/${event.slug}`, submitData);
        
        if (response.data.event) {
            Object.assign(event, response.data.event);
        }

        const nextStep = steps.value[currentStepIndex.value + 1];
        setStep(nextStep);
    } catch (error) {
        console.error('Error:', error);
    } finally {
        isSubmitting.value = false;
    }
};

const updateUrl = (step) => {
    const url = new URL(window.location);
    url.searchParams.set('view', step);
    window.history.pushState({}, '', url);
};

const setStep = (step) => {
    if (steps.value.includes(step)) {
        currentStep.value = step;
        updateUrl(step);
    }
};

onMounted(() => {
    // Remove the automatic hasLocation override
    // Check for view parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const viewStep = urlParams.get('view');
    
    if (viewStep && steps.value.includes(viewStep)) {
        currentStep.value = viewStep;
    } else {
        // Existing step navigation logic
        if (event.status && REVERSE_STEP_MAP[event.status]) {
            const savedStep = REVERSE_STEP_MAP[event.status];
            const currentIndex = steps.value.indexOf(savedStep);
            
            if (currentIndex !== -1 && currentIndex < steps.value.length - 1) {
                currentStep.value = steps.value[currentIndex + 1];
            } else {
                currentStep.value = savedStep;
            }
        }

        // Update URL to match initial step
        updateUrl(currentStep.value);
    }

    // Force correct component based on hasLocation
    if (currentStep.value === 'Remote' && event.hasLocation) {
        currentStep.value = 'Location';
        updateUrl('Location');
    } else if (currentStep.value === 'Location' && !event.hasLocation) {
        currentStep.value = 'Remote';
        updateUrl('Remote');
    }
});

// Provide shared state
provide('event', event);
provide('user', user);
provide('isSubmitting', isSubmitting);
provide('errors', errors);
</script>

<style scoped>
/* Optional: Add transition for hover effects */
a, button {
    transition: all 0.2s ease-in-out;
}
</style>
