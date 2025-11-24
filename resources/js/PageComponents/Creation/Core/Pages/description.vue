<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2 class="text-black">Describe your event</h2>
                <p class="text-neutral-500 font-normal mt-4">Let our users know everything about your event.</p>
                <div class="mt-6">
                    <textarea 
                        name="description" 
                        class="text-2.5xl font-normal border rounded-2xl p-4 w-full mt-8" 
                        :class="{ 
                            'border-red-500 focus:border-red-500 focus:shadow-focus-error': showError,
                            'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !showError 
                        }"
                        v-model="event.description" 
                        @input="handleInput"
                        placeholder="Tell us about your event..."
                        rows="14" 
                    />
                    <div class="flex justify-end mt-1" 
                         :class="{'text-red-500': isNearLimit, 'text-neutral-500': !isNearLimit}">
                        {{ characterCount }}/5000
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
            maxLength: maxLength(5000),
        }
    }
};
const $v = useVuelidate(rules, { event });

// Computed Properties
const characterCount = computed(() => event.description?.length || 0);

const isNearLimit = computed(() => {
    const count = characterCount.value;
    return count > 4500;
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
    if (event.description?.length > 5000) {
        event.description = event.description.slice(0, 5000);
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
