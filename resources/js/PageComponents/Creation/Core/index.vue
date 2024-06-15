<template>
    <div class="relative text-1xl font-medium w-full flex justify-center">
        <div id="nav" class="left-0 h-screen w-1/6 z-50 bg-white">
            <Nav 
                :current-step="currentStep" 
                :event="event" 
                :user="user" 
                @set-step="setStep" 
                @submit="onSubmit" 
            />
        </div>
        <div id="content" class="relative max-w-screen-xl p-8 m-auto w-5/6">
            <div class="w-full lg:w-1/2 m-auto">
                <component :is="currentComponent" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed } from 'vue';
import axios from 'axios';
import Nav from './nav.vue';
import EventType from './Pages/event-type.vue';
import Category from './Pages/category.vue';
import Location from './Pages/location.vue';
import Remote from './Pages/remote.vue'; // Import Remote component
import Description from './Pages/description.vue';
import Name from './Pages/name.vue';
import Dates from './Pages/dates.vue';
import Pricing from './Pages/pricing.vue';

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

const stepsWithLocation = ['EventType', 'Category', 'Location', 'Description', 'Name', 'Dates', 'Pricing'];
const stepsWithoutLocation = ['EventType', 'Category', 'Remote', 'Description', 'Name', 'Dates', 'Pricing'];

const components = {
    EventType,
    Category,
    Location,
    Remote, // Add Remote component
    Description,
    Name,
    Dates,
    Pricing
};

const steps = computed(() => event.hasLocation ? stepsWithLocation : stepsWithoutLocation);

const currentComponent = computed(() => components[currentStep.value]);

const setStep = (step) => {
    if (steps.value.includes(step)) {
        currentStep.value = step;
    }
};

const getNextStep = () => {
    const currentIndex = steps.value.indexOf(currentStep.value);
    return steps.value[currentIndex + 1] || null; // Return null if no next step
};

const onSubmit = async (updateData) => {
    isSubmitting.value = true;
    errors.value = {};

    try {
        const response = await axios.put(`/api/hosting/event/${event.slug}`, updateData);
        Object.assign(event, response.data.event);
        console.log(response.data);
        // Handle successful submission and move to the next step
        const nextStep = getNextStep();
        if (nextStep) {
            setStep(nextStep);
        }
    } catch (error) {
        if (error.response && error.response.data && error.response.data.errors) {
            errors.value = error.response.data.errors;
        } else {
            alert('Failed to submit data');
        }
    } finally {
        isSubmitting.value = false;
    }
};

// Provide the shared state and submission method to child components
provide('event', event);
provide('user', user);
provide('isSubmitting', isSubmitting);
provide('errors', errors);
provide('onSubmit', onSubmit);
provide('setStep', setStep); // Provide setStep to child components
</script>
