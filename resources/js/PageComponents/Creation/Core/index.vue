<template>
    <div class="relative text-1xl font-medium w-full">
        <!-- Top Navigation Bar -->
        <div class="fixed top-0 left-0 right-0 h-24 z-50">
            <div class="mx-auto p-16 h-full flex justify-between items-center">
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

        <!-- Main Content (adjusted padding for top nav) -->
        <div id="content" class="relative max-w-screen-xl m-auto pt-24 w-full">
            <div class="w-full lg:w-1/2 m-auto h-screen">
                <component :is="currentComponent" ref="currentComponentRef" />
            </div>
        </div>

        <!-- Bottom Navigation -->
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t">
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
                <div v-else class="w-[88px]"></div> <!-- Spacer to maintain layout -->

                
                <button 
                    @click="goToNext"
                    class="px-6 py-3 bg-black text-white hover:bg-gray-800 rounded-lg"
                    :disabled="isSubmitting"
                >
                    {{ isLastStep ? 'Finish' : 'Next' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import Nav from './nav.vue';
import EventType from './Pages/event-type.vue';
import Category from './Pages/category.vue';
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

const stepsWithLocation = ['EventType', 'Category', 'Location', 'Description', 'Name', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility'];
const stepsWithoutLocation = ['EventType', 'Category', 'Remote', 'Description', 'Name', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility'];

const components = {
    EventType,
    Category,
    Location,
    Remote, // Add Remote component
    Description,
    Name,
    Dates,
    Tickets,
    Images,
    Advisories,
    Content,
    Mobility
};

const steps = computed(() => event.hasLocation ? stepsWithLocation : stepsWithoutLocation);

const currentComponent = computed(() => components[currentStep.value]);

const currentStepIndex = computed(() => steps.value.indexOf(currentStep.value));
const isFirstStep = computed(() => currentStepIndex.value === 0);
const isLastStep = computed(() => currentStepIndex.value === steps.value.length - 1);
const progress = computed(() => ((currentStepIndex.value + 1) / steps.value.length) * 100);

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
    'Location': '3',
    'Remote': '3', // Same as Location since they're mutually exclusive
    'Description': '4',
    'Name': '5',
    'Dates': '6',
    'Tickets': '7',
    'Images': '8',
    'Advisories': '9',
    'Content': 'A',
    'Mobility': 'B'
};

// Reverse mapping for initialization
const REVERSE_STEP_MAP = Object.fromEntries(
    Object.entries(STEP_MAP).map(([key, value]) => [value, key])
);

const goToNext = async () => {
    try {
        const isValid = await currentComponentRef.value.isValid();
        if (!isValid) return;

        const submitData = await currentComponentRef.value.submitData();
        
        // Check if event is published (status is 'p' or 'e')
        if (event.status === 'p' || event.status === 'e') {
            // Don't change the status if event is published
            submitData.status = event.status;
        } else {
            // Only update status if it's a forward progression
            const currentStepValue = STEP_MAP[currentStep.value];
            const existingStepValue = event.status || '0';
            
            if (currentStepValue > existingStepValue) {
                submitData.status = currentStepValue;
            } else {
                submitData.status = existingStepValue;
            }
        }

        isSubmitting.value = true;
        const response = await axios.post(`/api/hosting/event/${event.slug}`, submitData);
        
        if (response.data.event) {
            Object.assign(event, response.data.event);
        }

        if (!isLastStep.value) {
            const nextStep = steps.value[currentStepIndex.value + 1];
            setStep(nextStep);
        }
    } catch (error) {
        console.error('Submission error:', error);
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        }
    } finally {
        isSubmitting.value = false;
    }
};

const setStep = (step) => {
    if (steps.value.includes(step)) {
        currentStep.value = step;
    }
};

onMounted(() => {
    if (event.status && REVERSE_STEP_MAP[event.status]) {
        // Find the next step after the saved status
        const savedStep = REVERSE_STEP_MAP[event.status];
        const currentIndex = steps.value.indexOf(savedStep);
        
        // If we found the step and it's not the last step
        if (currentIndex !== -1 && currentIndex < steps.value.length - 1) {
            // Set to the next step
            currentStep.value = steps.value[currentIndex + 1];
        } else {
            // Fallback to the saved step if we're at the end
            currentStep.value = savedStep;
        }
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
