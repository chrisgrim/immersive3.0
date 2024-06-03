<template>
    <div class="relative text-1xl font-medium w-full flex justify-center">
        <div id="nav" class="left-0 h-screen w-1/6">
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

const steps = ['EventType', 'Category', 'Location']; // Define your steps here
const currentStep = ref(steps[0]);

const event = reactive(props.event);
const user = reactive(props.user);
const isSubmitting = ref(false);
const errors = ref({});

const components = {
    EventType,
    Category,
    Location
};

const currentComponent = computed(() => components[currentStep.value]);

const setStep = (step) => {
    if (steps.includes(step)) {
        currentStep.value = step;
    }
};

const getNextStep = () => {
    const currentIndex = steps.indexOf(currentStep.value);
    return steps[currentIndex + 1] || null; // Return null if no next step
};

const onSubmit = async (field, value) => {
    isSubmitting.value = true;
    errors.value = {};

    // Create an object with only the updated field
    const updateData = { [field]: value };

    try {
        const response = await axios.put(`/api/hosting/event/${event.slug}`, updateData);
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
