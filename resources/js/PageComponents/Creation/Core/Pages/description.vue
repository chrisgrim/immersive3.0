<template>
    <main class="w-full">
        <div class="w-full">
            <h2>Create your description</h2>
            <p class="text-gray-500 font-normal mt-4">Let our users know everything about your event.</p>
            <div class="mt-6">
                <textarea 
                    name="description" 
                    class="text-3xl font-normal border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-8" 
                    v-model="event.description" 
                    @input="$v.event.description.$touch"
                    placeholder=""
                    rows="8" />
                <p v-if="$v.event.description.$dirty && $v.event.description.maxLength.$invalid" 
                   class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                    Event description is too long.
                </p>
            </div>
        </div>
        <div class="w-full flex justify-end">
            <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
        </div>
    </main>
</template>

<script setup>
import { inject } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';

// Inject dependencies provided by the parent
const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');
const errors = inject('errors');

// Setup Vuelidate for form validation
const rules = {
    event: {
        description: {
            required,
            maxLength: maxLength(30000),
        }
    }
};
const $v = useVuelidate(rules, { event });

// Handle form submission
const handleSubmit = async () => {
    errors.value = {};
    const isFormValid = await $v.value.$validate();
    if (!isFormValid) {
        return;
    }

    await onSubmit({ description: event.description });
    setStep('NextStep'); // Adjust 'NextStep' to the actual next step name
};
</script>
