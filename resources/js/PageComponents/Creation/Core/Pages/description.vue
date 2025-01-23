<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>Describe your event</h2>
                <p class="text-gray-500 font-normal mt-4">Let our users know everything about your event.</p>
                <div class="mt-6">
                    <textarea 
                        name="description" 
                        class="text-2xl font-normal border border-[#222222] focus:border-black rounded-2xl p-4 w-full mt-8" 
                        :class="{ 
                            'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showError,
                            'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !showError 
                        }"
                        v-model="event.description" 
                        @input="handleInput"
                        placeholder="Tell us about your event..."
                        rows="8" />
                    <div class="flex justify-end mt-1" 
                         :class="{'text-red-500': isNearLimit, 'text-gray-500': !isNearLimit}">
                        {{ characterCount }}/2000
                    </div>
                    <p v-if="showMaxLengthError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Event description is too long.
                    </p>
                    <p v-if="showRequiredError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Event description is required.
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { inject, computed } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';

const event = inject('event');
const errors = inject('errors');

// Setup Vuelidate
const rules = {
    event: {
        description: {
            required,
            maxLength: maxLength(2000),
        }
    }
};
const $v = useVuelidate(rules, { event });

// Computed Properties
const characterCount = computed(() => event.description?.length || 0);

const isNearLimit = computed(() => {
    const count = characterCount.value;
    return count > 1800;
});

const showError = computed(() => {
    return $v.value.event.description.$dirty && $v.value.event.description.$error;
});

const showMaxLengthError = computed(() => {
    return $v.value.event.description.$dirty && 
           $v.value.event.description.maxLength.$invalid;
});

const showRequiredError = computed(() => {
    return $v.value.event.description.$dirty && 
           $v.value.event.description.required.$invalid;
});

// Methods
const handleInput = () => {
    $v.value.event.description.$touch();
    if (event.description?.length > 2000) {
        event.description = event.description.slice(0, 2000);
    }
};

// Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { 
                description: ['Please provide a valid description'] 
            };
        }
        
        return isValid;
    },
    submitData: () => ({
        description: event.description
    })
});
</script>
