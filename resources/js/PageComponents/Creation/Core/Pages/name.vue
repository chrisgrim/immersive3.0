<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>What's your event called?</h2>
                <p class="text-gray-500 font-normal mt-4">Enter a unique name for your event</p>
                <div class="mt-6">
                    <!-- Event Name Input -->
                    <textarea 
                        name="name" 
                        class="text-4xl font-normal border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-8" 
                        :class="{ 
                            'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showNameError,
                            'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !showNameError 
                        }"
                        v-model="event.name" 
                        @input="handleNameInput"
                        placeholder="Enter event name"
                        rows="3" 
                    />
                    
                    <!-- Name Character Count -->
                    <div class="flex justify-end mt-1" 
                         :class="{'text-red-500': isNameNearLimit, 'text-gray-500': !isNameNearLimit}">
                        {{ event.name?.length || 0 }}/100
                    </div>

                    <!-- Name Error Messages -->
                    <p v-if="showNameMaxLengthError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Event name is too long.
                    </p>
                    <p v-if="showNameRequiredError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Event name is required
                    </p>

                    <!-- Tag Line Section -->
                    <div v-if="event.name">
                        <p class="text-gray-500 font-normal">Tag Line</p>
                        <textarea 
                            name="tag_line" 
                            class="text-2xl border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-4" 
                            :class="{ 
                                'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showTagLineError,
                                'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !showTagLineError 
                            }"
                            v-model="event.tag_line" 
                            @input="handleTagLineInput"
                            placeholder="Enter a catchy tagline"
                            rows="2" 
                        />

                        <!-- Tag Line Character Count -->
                        <div class="flex justify-end mt-1 relative" 
                             :class="{'text-red-500': isTagLineNearLimit, 'text-gray-500': !isTagLineNearLimit}">
                            {{ event.tag_line?.length || 0 }}/255
                                <!-- Tag Line Error Messages -->
                            <p v-if="showTagLineMaxLengthError" 
                            class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                Event tag line is too long.
                            </p>
                            <p v-if="showTagLineRequiredError" 
                            class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                Tag line is required
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { inject, computed } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';

// 1. Injected Dependencies
const event = inject('event');
const errors = inject('errors');

// 2. Validation Rules
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

// 3. Setup Vuelidate
const $v = useVuelidate(rules, { event });

// 4. Computed Properties
// Name validations
const showNameError = computed(() => {
    return $v.value.event.name.$dirty && $v.value.event.name.$error;
});

const showNameMaxLengthError = computed(() => {
    return $v.value.event.name.$dirty && $v.value.event.name.maxLength.$invalid;
});

const showNameRequiredError = computed(() => {
    return $v.value.event.name.$dirty && $v.value.event.name.required.$invalid;
});

const isNameNearLimit = computed(() => {
    const count = event.name?.length || 0;
    return count > 90;
});

// Tag line validations
const showTagLineError = computed(() => {
    return $v.value.event.tag_line.$dirty && $v.value.event.tag_line.$error;
});

const showTagLineMaxLengthError = computed(() => {
    return $v.value.event.tag_line.$dirty && $v.value.event.tag_line.maxLength.$invalid;
});

const showTagLineRequiredError = computed(() => {
    return $v.value.event.tag_line.$dirty && $v.value.event.tag_line.required.$invalid;
});

const isTagLineNearLimit = computed(() => {
    const count = event.tag_line?.length || 0;
    return count > 235;
});

// 5. Methods
const handleNameInput = () => {
    $v.value.event.name.$touch();
    if (event.name?.length > 100) {
        event.name = event.name.slice(0, 100);
    }
};

const handleTagLineInput = () => {
    $v.value.event.tag_line.$touch();
    if (event.tag_line?.length > 255) {
        event.tag_line = event.tag_line.slice(0, 255);
    }
};

// 6. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { 
                name: ['Please provide valid event name and tag line'] 
            };
        }
        
        return isValid;
    },
    submitData: () => ({
        name: event.name,
        tag_line: event.tag_line
    })
});
</script>
