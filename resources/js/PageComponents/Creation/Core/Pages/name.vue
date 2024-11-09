<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>What's your event called?</h2>
                <p class="text-gray-500 font-normal mt-4">Enter a unique name for your event</p>
                <div class="mt-6">
                    <textarea 
                        name="name" 
                        class="text-4xl font-normal border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-8" 
                        v-model="event.name" 
                        @input="$v.event.name.$touch"
                        placeholder=""
                        rows="3" />
                    <div class="flex justify-end mt-1 text-gray-500">
                        {{ event.name?.length || 0 }}/100
                    </div>
                    <p v-if="$v.event.name.$dirty && $v.event.name.maxLength.$invalid" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        Event name is too long.
                    </p>
                    <p v-if="$v.event.name.$dirty && $v.event.name.required.$invalid" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        Event name is required
                    </p>
                    <div v-if="event.name">
                        <p class="text-gray-500 font-normal">Tag Line</p>
                        <textarea 
                            name="tag_line" 
                            class="text-2xl border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-4" 
                            v-model="event.tag_line" 
                            @input="$v.event.tag_line.$touch"
                            placeholder=""
                            rows="2" />
                        <div v-if="(event.tag_line?.length || 0) > 235" class="flex justify-end mt-1 text-gray-500">
                            {{ event.tag_line?.length || 0 }}/255
                        </div>
                        <p v-if="$v.event.tag_line.$dirty && $v.event.tag_line.maxLength.$invalid" 
                           class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                            Event tag line is too long.
                        </p>
                        <p v-if="$v.event.tag_line.$dirty && $v.event.tag_line.required.$invalid" 
                           class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                            Tag line is required
                        </p>
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

const event = inject('event');
const errors = inject('errors');

// Setup Vuelidate
const rules = {
    event: {
        name: {
            required,
            maxLength: maxLength(100),
        },
        tag_line: {
            required,
            maxLength: maxLength(255),
        },
    }
};
const $v = useVuelidate(rules, { event });

// Expose methods for parent
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        console.log('Name validation:', {
            name: event.name,
            tagLine: event.tag_line,
            validationError: $v.value.$error,
            isValid
        });
        return isValid;
    },
    submitData: () => {
        const data = {
            name: event.name,
            tag_line: event.tag_line
        };
        console.log('Submitting name data:', data);
        return data;
    }
});
</script>
