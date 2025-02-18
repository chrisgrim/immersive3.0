<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full gap-8">
            <!-- Name and Description Form -->
            <div>
                <h2 class="text-2xl font-semibold mb-6">What's your post called?</h2>
                <div class="space-y-8">
                    <!-- Name Input -->
                    <div>
                        <textarea 
                            v-model="post.name" 
                            @input="handleNameInput"
                            :class="[
                                'text-4xl p-4 border rounded-2xl w-full',
                                {
                                    'border-red-500 focus:border-red-500 focus:shadow-focus-error': v$.post.name.$error,
                                    'border-[#222222] focus:border-black focus:shadow-focus-black': !v$.post.name.$error
                                }
                            ]"
                            placeholder="Enter post name"
                            rows="2"
                        />
                        <div class="flex justify-end mt-1" 
                             :class="{'text-red-500': isNameNearLimit, 'text-gray-500': !isNameNearLimit}">
                            {{ post.name?.length || 0 }}/100
                        </div>
                        <p v-if="v$.post.name.$error" class="text-red-500 text-sm mt-1 px-4">
                            {{ v$.post.name.$errors[0].$message }}
                        </p>
                    </div>

                    <!-- Description Input -->
                    <div>
                        <p class="text-lg font-medium mb-4">Description</p>
                        <textarea 
                            v-model="post.blurb"
                            @input="handleBlurbInput"
                            :class="[
                                'p-4 border rounded-2xl w-full text-lg',
                                {
                                    'border-red-500 focus:border-red-500 focus:shadow-focus-error': v$.post.blurb.$error,
                                    'border-[#222222] focus:border-black focus:shadow-focus-black': !v$.post.blurb.$error
                                }
                            ]"
                            placeholder="Tell people about your post"
                            rows="4"
                        />
                        <div class="flex justify-end mt-1" 
                             :class="{'text-red-500': isBlurbNearLimit, 'text-gray-500': !isBlurbNearLimit}">
                            {{ post.blurb?.length || 0 }}/255
                        </div>
                        <p v-if="v$.post.blurb.$error" class="text-red-500 text-sm mt-1 px-4">
                            {{ v$.post.blurb.$errors[0].$message }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { inject, computed } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, maxLength } from '@vuelidate/validators';

const post = inject('post');

// Validation rules
const rules = {
    post: {
        name: { 
            required,
            maxLength: maxLength(100)
        },
        blurb: { 
            maxLength: maxLength(255)
        }
    }
};

const v$ = useVuelidate(rules, { post });

// Computed properties
const isNameNearLimit = computed(() => (post.name?.length || 0) > 90);
const isBlurbNearLimit = computed(() => (post.blurb?.length || 0) > 230);

// Methods
const handleNameInput = () => {
    v$.value.post.name.$touch();
    if (post.name?.length > 100) {
        post.name = post.name.slice(0, 100);
    }
};

const handleBlurbInput = () => {
    v$.value.post.blurb.$touch();
    if (post.blurb?.length > 255) {
        post.blurb = post.blurb.slice(0, 255);
    }
};

// Component API
defineExpose({
    isValid: async () => {
        const result = await v$.value.$validate();
        return result;
    },
    submitData: () => {
        if (v$.value.$error) return null;
        
        return {
            name: post.name,
            blurb: post.blurb
        };
    }
});
</script>