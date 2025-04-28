<template>
    <main class="w-full min-h-fit">
        <!-- Loading State -->
        <div v-if="state.checkingEventHistory" class="w-full flex justify-center items-center py-12">
            <svg class="animate-spin h-10 w-10 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        
        <!-- Initial Acceptance Screen -->
        <div v-else-if="!state.userAccepts && event.hasLocation === null && !state.hasCreatedEventBefore" class="w-full">
            <div class="flex justify-center w-full">
                <div class="w-full">
                    <h3 class="mt-10 text-6xl mb-8">Before We Start:</h3>
                    <p class="text-2xl">Does the project you are submitting belong on Everything Immersive?</p>
                    
                    <div class="mt-8">
                        <p class="text-2xl">We define "immersive" as:</p>
                        <p class="font-light text-2xl mt-2 italic">That which meaningfully puts the audience on the same level as the primary action in a story/environment, usually physically and/or narratively.</p>
                        <p class="font-light text-2xl mt-4">For example: the audience is part of the world in an immersive piece, even if it is mainly as a physical obstacle.</p>
                    </div>
                    
                    <div class="mt-8">
                        <p class="text-blacktext-3xl">Projects should be able to answer YES to AT LEAST ONE of the following questions:</p>
                        <ul class="mt-4 space-y-2 text-2xl">
                            <li>• Can the audience freely explore a physical or virtual space under their own power?</li>
                            <li>• Can the audience directly influence the work from inside the work? (As opposed to, say, giving suggestions in an improv show.)</li>
                            <li>• Is the audience sensorily surrounded by the work?</li>
                            <li>• Is the work a site-specific performance or a work of installation art?</li>
                            <li>• Is this an escape game, theatrical game, puzzle hunt, or murder mystery style experience?</li>
                            <li>• Is this a LARP?</li>
                            <li>• Is this a piece of themed entertainment, a roadside attraction, a museum, or a themed bar/restaurant?</li>
                            <li>• Is this a work of virtual or augmented reality?</li>
                            <li>• Is the work a magic performance, experiential marketing pop-up, or make extensive use of projection mapping?</li>
                            <li>• Is this a gathering or class for immersive, themed entertainment, XR and/or experiential professionals?</li>
                        </ul>
                        <p class="font-medium text-xl mt-4">If you answered "yes" to ANY of the questions above, you're good to submit!</p>
                    </div>
                </div>
            </div>
            <div class="w-full flex justify-start mt-8">
                <button 
                    class="p-4 bg-black text-white rounded-2xl hover:bg-neutral-800 transition-colors" 
                    @click="handleAccept"
                >
                    I accept
                </button>
            </div>
            <p v-if="showAcceptanceError" 
               class="text-red-500 text-1xl mt-4 mb-8">
                Please accept before continuing
            </p>
        </div>

        <!-- Event Type Selection -->
        <div v-else class="w-full">
            <div class="flex flex-col w-full">
                <h2 class="text-black">What type of event are you hosting?</h2>
                <div class="pt-16 flex flex-col gap-8">
                    <button 
                        v-for="option in eventTypeOptions" 
                        :key="option.value"
                        @click="onSelect(option.value)"
                        :class="[
                            'border rounded-2xl flex justify-between items-center w-full h-48 p-8 transition-all duration-200',
                            {
                                'border-[#222222] shadow-focus-black': event.hasLocation === option.value,
                                'border-neutral-200 hover:border-[#222222] hover:shadow-focus-black hover:bg-neutral-50': event.hasLocation !== option.value
                            }
                        ]"
                    >
                        <div class="w-full text-left">
                            <p class="font-bold text-3xl">
                                {{ option.label }}
                            </p>
                            <p class="text-1xl mt-4 text-neutral-700 font-light">
                                {{ option.description }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>
            <p v-if="showEventTypeError" 
               class="text-red-500 text-1xl mt-4 mb-8">
                Please choose an event type
            </p>
        </div>
    </main>
</template>

<script setup>
// 1. Imports
import { ref, inject, computed, onMounted } from 'vue';
import useVuelidate from '@vuelidate/core';
import { required } from '@vuelidate/validators';
import axios from 'axios';

// 2. Injected Dependencies
const event = inject('event');
const errors = inject('errors');
const user = inject('user');

// 3. Constants
const eventTypeOptions = [
    {
        value: true,
        label: 'In Person',
        description: 'Real world events that guests will be part of.'
    },
    {
        value: false,
        label: 'Online Only',
        description: 'Guests will join the event virtually.'
    }
];

// 4. State Management
const state = ref({
    userAccepts: false,
    hasAcceptanceError: false,
    hasEventTypeError: false,
    hasCreatedEventBefore: false,
    checkingEventHistory: true
});

// 5. Computed Properties
const showAcceptanceError = computed(() => {
    return state.value.hasAcceptanceError && !state.value.userAccepts;
});

const showEventTypeError = computed(() => {
    return state.value.hasEventTypeError && event.hasLocation === null;
});

// 6. Validation Rules
const rules = {
    hasAccepted: { required },
    hasLocation: { required }
};

const $v = useVuelidate(rules, {
    hasAccepted: computed(() => state.value.userAccepts || state.value.hasCreatedEventBefore),
    hasLocation: computed(() => event.hasLocation)
});

// 7. Methods
const handleAccept = () => {
    state.value.userAccepts = true;
    state.value.hasAcceptanceError = false;
    
    // Save this preference in localStorage
    localStorage.setItem('ei_has_accepted_guidelines', 'true');
};

// Debug method to clear localStorage (can be triggered from browser console)
window.clearEIGuidelines = () => {
    localStorage.removeItem('ei_has_accepted_guidelines');
    console.log('Cleared acceptance guidelines from localStorage. Refresh to see the acceptance screen.');
};

const onSelect = (hasLocation) => {
    event.hasLocation = hasLocation;
    event.attendance_type_id = hasLocation === true ? 1 : 2;
    state.value.hasEventTypeError = false;
};

const checkEventCreationHistory = async () => {
    try {
        state.value.checkingEventHistory = true;
        
        // For debugging - remove this in production
        console.log('Checking event creation history');
        
        // Check if user has accepted guidelines before via localStorage first
        if (localStorage.getItem('ei_has_accepted_guidelines') === 'true') {
            console.log('User has previously accepted guidelines (localStorage)');
            state.value.hasCreatedEventBefore = true;
            state.value.checkingEventHistory = false;
            return;
        }
        
        // Otherwise, check via API
        console.log('Making API call to check event history');
        const response = await axios.get('/hosting/event/user/has-created-events');
        console.log('API response:', response.data);
        
        // IMPORTANT: For new users, default to false instead of true
        state.value.hasCreatedEventBefore = response.data.hasCreatedEvents === true;
        
        // If they have created events before, save this in localStorage for future reference
        if (state.value.hasCreatedEventBefore) {
            localStorage.setItem('ei_has_accepted_guidelines', 'true');
        }
    } catch (error) {
        console.error('Error checking event creation history:', error);
        // CHANGED: For errors, default to showing the acceptance screen (false)
        // This ensures new users see the screen even if there's an API error
        state.value.hasCreatedEventBefore = false;
    } finally {
        state.value.checkingEventHistory = false;
    }
};

// Prefetch the user status immediately on component creation
checkEventCreationHistory();

// 8. Lifecycle Hooks
onMounted(() => {
    // The checkEventCreationHistory function is already called on creation
    // No need to call it again unless we want to refresh
});

// 9. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        
        // Only check acceptance if hasLocation hasn't been set yet and user hasn't created events before
        if (event.hasLocation === null && !state.value.hasCreatedEventBefore && !state.value.userAccepts) {
            state.value.hasAcceptanceError = true;
            errors.value = { eventType: ['Please accept before continuing'] };
            return false;
        }
        
        if (event.hasLocation === null) {
            state.value.hasEventTypeError = true;
            errors.value = { eventType: ['Please select an event type'] };
            return false;
        }
        
        return true;
    },
    submitData: () => {
        // Map hasLocation to attendance_type_id
        // true (In Person) = 1, false (Remote) = 2
        const attendanceTypeId = event.hasLocation === true ? 1 : 2;
        
        return {
            hasLocation: event.hasLocation,
            attendance_type_id: attendanceTypeId
        };
    }
});
</script>
