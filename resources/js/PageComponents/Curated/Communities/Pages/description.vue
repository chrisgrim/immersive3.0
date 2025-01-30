<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>Community Description</h2>
                <p class="text-neutral-500 font-normal mt-4">Tell users about your community</p>
                <div class="mt-6">
                    <textarea 
                        name="description" 
                        class="text-2xl font-normal border rounded-2xl p-4 w-full mt-8" 
                        :class="{ 
                            'border-red-500 focus:shadow-focus-error': showError,
                            'border-[#222222] focus:shadow-focus-black': !showError 
                        }"
                        v-model="community.description" 
                        @input="handleInput"
                        placeholder="Describe your community..."
                        rows="12" 
                    />
                    <div class="flex justify-end mt-1" 
                         :class="{'text-red-500': isNearLimit, 'text-neutral-500': !isNearLimit}">
                        {{ characterCount }}/5000
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

const community = inject('community');
const errors = inject('errors');

// Validation rules
const rules = {
    community: {
        description: { required, maxLength: maxLength(5000) }
    }
};

const v$ = useVuelidate(rules, { community });

// Computed properties
const characterCount = computed(() => community.description?.length || 0);
const isNearLimit = computed(() => characterCount.value > 4900);
const showError = computed(() => v$.value.community.description.$dirty && v$.value.community.description.$error);

// Methods
const handleInput = () => {
    v$.value.community.description.$touch();
    if (community.description?.length > 5000) {
        community.description = community.description.slice(0, 5000);
    }
};

// Component API
defineExpose({
    isValid: async () => {
        await v$.value.$validate();
        const isValid = !v$.value.$error;
        if (!isValid) {
            errors.value = { description: ['Please provide a valid description'] };
        }
        return isValid;
    },
    submitData: () => ({
        description: community.description
    })
});
</script>