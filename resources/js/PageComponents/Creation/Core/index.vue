<template>
    <div class="relative text-1xl font-medium w-full h-screen flex flex-col">
        <!-- Top Navigation Bar -->
        <div class="h-24 fixed md:relative top-0 left-0 right-0 w-full flex-none bg-white z-[1000]">
        <!-- <div class="flex-none h-24"> -->
            <div class="mx-auto p-8 md:p-16 h-full flex justify-between items-center">
                <!-- Left: EI Logo/Link -->
                <a href="/hosting/events" class="flex flex-col items-center gap-1" :class="isHome ? 'text-primary font-medium' : 'text-neutral-600'">
                    <img src="/storage/website-files/Everything_Immersive_logo_Short.png" alt="EI" class="w-12 h-12" />
                </a>

                <!-- Right: Questions & Exit -->
                <div class="flex items-center gap-6">
                    <a 
                        href="mailto:support@everythingimmersive.com?subject=Event Creation Question"
                        class="px-6 py-3 text-black hover:bg-gray-100 rounded-lg"
                    >
                        Questions
                    </a>
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
        <div class="md:flex-1 md:overflow-y-auto pt-24 md:pt-0 pb-40 md:pb-0">
            <div 
                class="mx-auto md:min-h-full md:flex"
                :class="currentStep !== 'Dates' ? 'max-w-screen-xl' : ''"
            >
                <div :class="['w-full mx-auto', containerWidthClass]">
                    <div class="h-full md:flex px-8 pb-8">
                        <component :is="currentComponent" ref="currentComponentRef" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation (fixed at bottom) -->
        <div class="fixed md:relative bottom-0 left-0 right-0 w-full flex-none bg-white z-[1000]">
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

                <!-- Organization & Event Name (centered) -->
                <div class="flex-1 text-center">
                    <p class="font-medium text-gray-500">
                        {{ event?.organizer?.name || 'My Organization' }}
                        <span v-if="event.name" class="mr-4">:</span>
                        <span v-if="event.name" class="font-bold text-gray-700">{{ event.name }}</span>
                    </p>
                </div>

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
const isHome = ref(false); // Add missing isHome property

const stepsWithLocation = ['EventType', 'Name', 'Category', 'Genres', 'Location', 'Description', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility', 'Review'];
const stepsWithoutLocation = ['EventType', 'Name', 'Category', 'Genres', 'Remote', 'Description', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility', 'Review'];

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

const steps = computed(() => (event.attendance_type_id === 1 || event.hasLocation) ? stepsWithLocation : stepsWithoutLocation);

const currentComponent = computed(() => components[currentStep.value]);

const currentStepIndex = computed(() => steps.value.indexOf(currentStep.value));
const isFirstStep = computed(() => currentStepIndex.value === 0);
const isLastStep = computed(() => currentStepIndex.value === steps.value.length - 1);
const progress = computed(() => ((currentStepIndex.value + 1) / steps.value.length) * 100);
const isComponentReady = ref(true); // Default to true

const containerWidthClass = computed(() => {
    const componentName = currentComponent.value.__name?.toLowerCase();
    
    if (componentName === 'dates') return 'lg:w-full pt-40 pb-0';
    if (componentName === 'images') return 'lg:w-2/3 pt-40 md:pb-20';
    return 'lg:w-1/2 pt-40 md:pb-20';
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
    'Name': '2',
    'Category': '3',
    'Genres': '4',
    'Location': '5',
    'Remote': '5',
    'Description': '6',
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
        if (!event.status || (event.status !== 'p' && event.status !== 'e')) {
            const currentStepValue = STEP_MAP[currentStep.value];
            const existingStepValue = event.status || '0';
            
            const statusToSet = currentStepValue > existingStepValue 
                ? currentStepValue 
                : existingStepValue;
            
            // Handle both FormData and regular objects
            if (submitData instanceof FormData) {
                submitData.append('status', statusToSet);
            } else {
                submitData.status = statusToSet;
            }
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
        
        // Check if it's a 409 Conflict due to duplicate name
        if (currentStep.value === 'Name' && 
            error.response?.status === 409 && 
            currentComponentRef.value.handleDuplicateError) {
            
            // Let the Name component handle the duplicate error
            const handled = currentComponentRef.value.handleDuplicateError(error);
            if (handled) {
                // Error was handled by the component, no need to set errors here
                return;
            }
        }
        
        // Handle other errors
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            errors.value = {
                general: [error.response.data.message]
            };
        }
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

    // Force correct component based on attendance_type_id or hasLocation
    if (currentStep.value === 'Remote' && (event.attendance_type_id === 1 || event.hasLocation)) {
        currentStep.value = 'Location';
        updateUrl('Location');
    } else if (currentStep.value === 'Location' && event.attendance_type_id === 2 && !event.hasLocation) {
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
