<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>Community Name</h2>
                <p class="text-gray-500 font-normal mt-4">Enter a name for your community</p>
                <div class="mt-6">
                    <!-- Name Input -->
                    <textarea 
                        name="name" 
                        :class="[
                            'text-4xl font-normal border rounded-2xl p-4 w-full mt-8',
                            {
                                'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showNameError,
                                'border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black]': !showNameError
                            }
                        ]"
                        v-model="community.name" 
                        @input="handleNameInput"
                        placeholder="Enter community name"
                        rows="2" 
                    />
                    
                    <!-- Name Character Count -->
                    <div class="flex justify-end mt-1" 
                         :class="{'text-red-500': isNameNearLimit, 'text-gray-500': !isNameNearLimit}">
                        {{ community.name?.length || 0 }}/100
                    </div>

                    <!-- Blurb Input -->
                    <div class="mt-8">
                        <p class="text-gray-500 font-normal">Blurb</p>
                        <textarea 
                            name="blurb" 
                            class="text-2xl border border-[#222222] focus:border-black rounded-2xl p-4 w-full mt-4" 
                            :class="{ 
                                'border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showBlurbError,
                                'focus:shadow-[0_0_0_1.5px_black]': !showBlurbError 
                            }"
                            v-model="community.blurb" 
                            @input="handleBlurbInput"
                            placeholder="Enter a short description"
                            rows="3" 
                        />

                        <!-- Blurb Character Count -->
                        <div class="flex justify-end mt-1" 
                             :class="{'text-red-500': isBlurbNearLimit, 'text-gray-500': !isBlurbNearLimit}">
                            {{ community.blurb?.length || 0 }}/254
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

const community = inject('community');
const errors = inject('errors');

// Validation rules
const rules = {
    community: {
        name: { required, maxLength: maxLength(100) },
        blurb: { required, maxLength: maxLength(254) }
    }
};

const v$ = useVuelidate(rules, { community });

// Computed properties
const showNameError = computed(() => v$.value.community.name.$dirty && v$.value.community.name.$error);
const isNameNearLimit = computed(() => (community.name?.length || 0) > 90);

const showBlurbError = computed(() => v$.value.community.blurb.$dirty && v$.value.community.blurb.$error);
const isBlurbNearLimit = computed(() => (community.blurb?.length || 0) > 244);

// Methods
const handleNameInput = () => {
    v$.value.community.name.$touch();
    if (community.name?.length > 100) {
        community.name = community.name.slice(0, 100);
    }
};

const handleBlurbInput = () => {
    v$.value.community.blurb.$touch();
    if (community.blurb?.length > 254) {
        community.blurb = community.blurb.slice(0, 254);
    }
};

// Component API
defineExpose({
    isValid: async () => {
        await v$.value.$validate();
        const isValid = !v$.value.$error;
        if (!isValid) {
            errors.value = { name: ['Please provide valid name and blurb'] };
        }
        return isValid;
    },
    submitData: () => ({
        name: community.name,
        blurb: community.blurb
    })
});
</script>