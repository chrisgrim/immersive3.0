<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>Describe your event</h2>
                <p class="text-gray-500 font-normal mt-4">Let our users know everything about your event.</p>
                <div class="mt-6">
                    <textarea 
                        name="description" 
                        class="text-2xl font-normal border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-8" 
                        v-model="event.description" 
                        @input="$v.event.description.$touch"
                        placeholder=""
                        rows="8" />
                    <div class="flex justify-end mt-1 text-gray-500">
                        {{ event.description?.length || 0 }}/2000
                    </div>
                    <p v-if="$v.event.description.$dirty && $v.event.description.maxLength.$invalid" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        Event description is too long.
                    </p>
                    <p v-if="$v.event.description.$dirty && $v.event.description.required.$invalid" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        Event description is required.
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { inject } from 'vue';
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

// Expose methods for parent
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        console.log('Description validation:', {
            description: event.description,
            validationError: $v.value.$error,
            isValid
        });
        return isValid;
    },
    submitData: () => {
        const data = {
            description: event.description
        };
        console.log('Submitting description data:', data);
        return data;
    }
});
</script>
