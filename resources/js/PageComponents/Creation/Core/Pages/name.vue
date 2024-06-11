<template>
    <main class="w-full">
        <div class="flex w-full">
            <div class="w-full">
                <h2>Give your event a name</h2>
                <p class="text-gray-500 font-normal mt-4">Enter a unique name for your event</p>
                <div class="mt-6">
                    <textarea 
                        name="name" 
                        class="text-4xl font-normal border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-8" 
                        v-model="event.name" 
                        @input="$v.event.name.$touch"
                        placeholder=""
                        rows="3" />
                    <p v-if="$v.event.name.$dirty && $v.event.name.maxLength.$invalid" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        Event name is too long.
                    </p>
                    <p v-if="$v.event.name.$dirty && $v.event.name.required.$invalid" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        Event name is required
                    </p>
                    <div v-if="event.name">
                        <p class="text-gray-500 font-normal mt-12">Tag Line</p>
                        <textarea 
                            name="tag_line" 
                            class="text-2xl border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-4" 
                            v-model="event.tag_line" 
                            @input="$v.event.tag_line.$touch"
                            placeholder=""
                            rows="2" />
                        <p v-if="$v.event.tag_line.$dirty && $v.event.tag_line.maxLength.$invalid" 
                           class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                            Event tag_line is too long.
                        </p>
                        <p v-if="$v.event.tag_line.$dirty && $v.event.tag_line.required.$invalid" 
                           class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                            Tag line is required
                        </p>
                    </div>
                    <div class="w-full flex justify-end">
                        <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
                    </div>
                </div>
            </div>
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
        name: {
            required,
            maxLength: maxLength(255),
        },
        tag_line: {
            required,
            maxLength: maxLength(255),
        },
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

    await onSubmit({ name: event.name, tag_line: event.tag_line });
    setStep('NextStep'); // Adjust 'NextStep' to the actual next step name
};
</script>
