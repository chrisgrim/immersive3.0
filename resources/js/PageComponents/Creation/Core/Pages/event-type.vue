<template>
    <main class="w-full min-h-fit">
        <!-- Initial Acceptance Screen -->
        <div v-if="!state.userAccepts && event.hasLocation === null" class="w-full">
            <div class="flex justify-center w-full">
                <div class="w-full">
                    <h3 class="mt-10 text-6xl mb-8">The right fit:</h3>
                    <p class="font-light">Immersive experiences are experiences that have some type of immersive aspect. They must meet these standards:</p>
                    <div class="rounded-2xl overflow-hidden my-12 w-1/2">
                        <img 
                            src="https://a0.muscache.com/pictures/aca23391-4bab-4ddb-91e3-3934147bbcac.jpg" 
                            alt="Immersive Experience Example"
                        >
                    </div>
                    <ul class="text-2xl ml-0 mt-8 font-light">
                        <li><span class="font-semibold">Immersive: </span>Must include some way for the user to interact</li>
                        <li><span class="font-semibold">Safe: </span>If the event is in person it needs to be safe for the users.</li>
                    </ul>
                </div>
            </div>
            <div class="w-full flex justify-start mt-8">
                <button 
                    class="p-4 bg-black text-white rounded-2xl" 
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
                <h2>What type of event are you hosting?</h2>
                <div class="pt-16 flex flex-col gap-8">
                    <button 
                        v-for="option in eventTypeOptions" 
                        :key="option.value"
                        @click="onSelect(option.value)"
                        :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.hasLocation === option.value }"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center w-full hover:border-2 hover:border-black h-48 p-8"
                    >
                        <div class="w-full text-left">
                            <h4 class="font-bold text-3xl">
                                {{ option.label }}
                            </h4>
                            <p class="text-1xl mt-4 text-gray-700 font-light">
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
import { ref, inject, computed } from 'vue';
import useVuelidate from '@vuelidate/core';
import { required } from '@vuelidate/validators';

// 2. Injected Dependencies
const event = inject('event');
const errors = inject('errors');

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
    hasEventTypeError: false
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
    hasAccepted: computed(() => state.value.userAccepts),
    hasLocation: computed(() => event.hasLocation)
});

// 7. Methods
const handleAccept = () => {
    state.value.userAccepts = true;
    state.value.hasAcceptanceError = false;
};

const onSelect = (hasLocation) => {
    event.hasLocation = hasLocation;
    state.value.hasEventTypeError = false;
};

// 8. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        
        // Only check acceptance if hasLocation hasn't been set yet
        if (event.hasLocation === null && !state.value.userAccepts) {
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
        return {
            hasLocation: event.hasLocation
        };
    }
});
</script>
