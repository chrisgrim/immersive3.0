<template>
    <div class="w-full h-screen">
        <div class="max-w-screen-xl p-8 m-auto">
            <div class="w-1/2 m-auto my-36">
                <div>
                    <div class="h-60" id="intro" :class="{ shrink: !!community.name || !!community.blurb }">
                        <h5 class="font-bold">Step 1</h5>
                        <h3 class="mt-10 mb-28 text-6xl leading-[4rem]">Let's create your community</h3>
                    </div>
                    <div class="flex flex-row items-center w-full">
                        <div class="align-bottom relative flex flex-col w-full">
                            <!-- Name Input -->
                            <div class="relative w-full">
                                <p class="text-black font-medium mb-4">Name</p>
                                <textarea 
                                    id="Name" 
                                    rows="3" 
                                    placeholder="Community Name" 
                                    ref="nameInput" 
                                    @input="handleNameInput"
                                    v-model="community.name" 
                                    :class="[
                                        'text-4xl p-4 border rounded-2xl mt-1 block w-full',
                                        {
                                            'border-red-500 focus:border-red-500 focus:shadow-focus-error': showNameError,
                                            'border-[#222222] focus:border-black focus:shadow-focus-black': !showNameError
                                        }
                                    ]"
                                />
                                <!-- Name Character Count -->
                                <div class="flex justify-end mt-1" 
                                     :class="{'text-red-500': isNameNearLimit, 'text-gray-500': !isNameNearLimit}">
                                    {{ community.name?.length || 0 }}/80
                                </div>
                                <!-- Name Error Messages -->
                                <p v-if="showNameMaxLengthError" 
                                   class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                                    Community name is too long.
                                </p>
                                <p v-if="showNameRequiredError" 
                                   class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                                    Community name is required
                                </p>
                            </div>
                            <p v-if="$v.community.name.$dirty && $v.community.name.required.$invalid" class="text-white bg-red-500 text-lg mt-1 px-4 py-2">The community name is required</p>
                            <div v-if="errors && errors.name" class="w-full">
                                <p class="text-white bg-red-500 text-lg mt-1 px-4 py-2">{{ errors.name[0] }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="errors && errors.image" class="w-full mt-4">
                        <p class="text-white bg-red-500 text-lg mt-1 px-4 py-2">{{ errors.image[0] }}</p>
                    </div>
                </div>

                <!-- Blurb -->
                <div v-if="community.name" class="mt-12 w-full">
                    <div class="w-full">
                        <p class="text-black font-medium mb-4">Blurb</p>
                        <textarea 
                            id="blurb" 
                            rows="3" 
                            placeholder="Add a short blurb about your community" 
                            ref="blurbInput"
                            @input="handleBlurbInput"
                            @blur="handleBlurBlurb"
                            @focus="handleFocusBlurb"
                            v-model="community.blurb" 
                            :class="[
                                'p-4 border rounded-2xl mt-1 block w-full',
                                {
                                    'border-red-500 focus:border-red-500 focus:shadow-focus-error': showBlurbError,
                                    'border-[#222222] focus:border-black focus:shadow-focus-black': !showBlurbError
                                }
                            ]"
                        />
                        <!-- Blurb Character Count -->
                        <div class="flex justify-end mt-1" 
                             :class="{'text-red-500': isBlurbNearLimit, 'text-gray-500': !isBlurbNearLimit}">
                            {{ community.blurb?.length || 0 }}/160
                        </div>
                        <!-- Blurb Error Messages -->
                        <p v-if="showBlurbMaxLengthError" 
                           class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                            Community blurb is too long.
                        </p>
                        <p v-if="showBlurbRequiredError" 
                           class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                            Community blurb is required
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div v-if="community.blurb" class="mt-12 w-full">
                    <div class="w-full">
                        <p class="text-black font-medium mb-4">Description</p>
                        <textarea 
                            id="description" 
                            rows="6" 
                            placeholder="Add a detailed description of your community" 
                            ref="descriptionInput"
                            @input="handleDescriptionInput"
                            v-model="community.description" 
                            :class="[
                                'p-4 border rounded-2xl mt-1 block w-full',
                                {
                                    'border-red-500 focus:border-red-500 focus:shadow-focus-error': showDescriptionError,
                                    'border-[#222222] focus:border-black focus:shadow-focus-black': !showDescriptionError
                                }
                            ]"
                        />
                        <!-- Description Character Count -->
                        <div class="flex justify-end mt-1" 
                             :class="{'text-red-500': isDescriptionNearLimit, 'text-gray-500': !isDescriptionNearLimit}">
                            {{ community.description?.length || 0 }}/2000
                        </div>
                        <!-- Description Error Messages -->
                        <p v-if="showDescriptionMaxLengthError" 
                           class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                            Description is too long.
                        </p>
                        <p v-if="showDescriptionRequiredError" 
                           class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                            Description is required
                        </p>
                    </div>
                </div>

                <!-- Update the image section -->
                <div v-if="community.name && community.blurb && community.description" class="mt-12">
                    <p class="text-black font-medium mb-4">Image</p>
                    <div class="w-full relative">
                        <div class="aspect-[16/9] w-full">
                            <label
                                for="image-upload"
                                class="rounded-2xl block relative w-full h-full"
                                :class="{
                                    'cursor-pointer group': true,
                                    'border-2 border-black': !imagePreview,
                                    'border-none': imagePreview,
                                }"
                            >
                                <div class="w-full h-full relative flex items-center justify-center">
                                    <img 
                                        v-if="imagePreview" 
                                        :src="imagePreview" 
                                        :alt="community.name + `'s image`"
                                        class="absolute inset-0 w-full h-full object-cover rounded-2xl"
                                    >
                                    <div v-else class="flex flex-col items-center justify-center">
                                        <component 
                                            :is="RiImageAddLine" 
                                            class="w-12 h-12 group-hover:text-neutral-500" 
                                        />
                                        <span class="mt-2 text-gray-500">Add a cover image</span>
                                    </div>
                                </div>
                            </label>
                            <input 
                                type="file" 
                                id="image-upload" 
                                @change="updateImage" 
                                accept="image/*"
                                class="hidden"
                            >
                        </div>
                    </div>
                </div>

                <div v-if="community.name && community.blurb && community.description" class="w-full flex justify-end mt-12">
                    <button 
                        class="text-right p-4 rounded-2xl transition-colors duration-200"
                        :class="{
                            'bg-black text-white hover:bg-gray-800': isFormComplete,
                            'bg-gray-200 text-gray-400 cursor-not-allowed': !isFormComplete || isSubmitting
                        }"
                        :disabled="!isFormComplete || isSubmitting"
                        @click="onSubmit"
                    >
                        {{ isSubmitting ? 'Creating...' : 'Create Community' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, nextTick, computed } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, maxLength } from '@vuelidate/validators';
import { RiImageAddLine } from "@remixicon/vue";

// Define props
const props = defineProps({
    user: {
        type: Object,
        required: true
    }
});

// Create a reactive community object
const community = reactive({
    name: '',
    blurb: '',
    description: ''
});

// Validation rules
const rules = {
    community: {
        name: { 
            required, 
            maxLength: maxLength(80) 
        },
        blurb: { 
            required, 
            maxLength: maxLength(160) 
        },
        description: { 
            required, 
            maxLength: maxLength(2000) 
        }
    }
};

// Initialize Vuelidate
const $v = useVuelidate(rules, { community });

// Refs
const nameInput = ref(null);
const blurbInput = ref(null);
const descriptionInput = ref(null);
const imageFile = ref(null);
const imagePreview = ref(null);
const errors = ref({});
const isSubmitting = ref(false);

// Add to script setup, before the methods
const isFormComplete = computed(() => {
    return (
        community.name?.trim() && 
        community.blurb?.trim() && 
        community.description?.trim() && 
        imageFile.value && 
        !$v.value.$invalid
    );
});

// Methods
const updateImage = (event) => {
    const file = event.target.files[0];
    if (!file) return;
    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
};

const onSubmit = async () => {
    isSubmitting.value = true;
    errors.value = {};
    
    const isFormValid = await $v.value.$validate();
    if (!isFormValid) {
        isSubmitting.value = false;
        return;
    }

    const formData = new FormData();
    formData.append('name', community.name);
    formData.append('blurb', community.blurb);
    if (community.description) {
        formData.append('description', community.description);
    }
    if (imageFile.value) {
        formData.append('image', imageFile.value);
    }

    try {
        const response = await axios.post('/communities', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        window.location.href = `/communities/${response.data.slug}`;
    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            console.error('Failed to create community:', error);
        }
    } finally {
        isSubmitting.value = false;
    }
};

const handleBlurBlurb = () => {
    if (document.activeElement !== blurbInput.value) {
        isEditingBlurb.value = false;
    }
};

const handleFocusBlurb = () => {
    isEditingBlurb.value = true;
};

const clearError = (field) => {
    if (errors.value[field]) {
        delete errors.value[field];
    }
};

const handleNameInput = () => {
    $v.value.community.name.$touch();
    if (community.name?.length > 80) {
        community.name = community.name.slice(0, 80);
    }
};

const handleBlurbInput = () => {
    $v.value.community.blurb.$touch();
    if (community.blurb?.length > 160) {
        community.blurb = community.blurb.slice(0, 160);
    }
};

const handleDescriptionInput = () => {
    $v.value.community.description.$touch();
    if (community.description?.length > 2000) {
        community.description = community.description.slice(0, 2000);
    }
};

// Name validations
const showNameError = computed(() => {
    return $v.value.community.name.$dirty && $v.value.community.name.$error;
});

const showNameMaxLengthError = computed(() => {
    return $v.value.community.name.$dirty && $v.value.community.name.maxLength.$invalid;
});

const showNameRequiredError = computed(() => {
    return $v.value.community.name.$dirty && $v.value.community.name.required.$invalid;
});

const isNameNearLimit = computed(() => {
    const count = community.name?.length || 0;
    return count > 50;
});

// Blurb validations
const showBlurbError = computed(() => {
    return $v.value.community.blurb.$dirty && $v.value.community.blurb.$error;
});

const showBlurbMaxLengthError = computed(() => {
    return $v.value.community.blurb.$dirty && $v.value.community.blurb.maxLength.$invalid;
});

const showBlurbRequiredError = computed(() => {
    return $v.value.community.blurb.$dirty && $v.value.community.blurb.required.$invalid;
});

const isBlurbNearLimit = computed(() => {
    const count = community.blurb?.length || 0;
    return count > 140;
});

// Description validations
const showDescriptionError = computed(() => {
    return $v.value.community.description.$dirty && $v.value.community.description.$error;
});

const showDescriptionMaxLengthError = computed(() => {
    return $v.value.community.description.$dirty && $v.value.community.description.maxLength.$invalid;
});

const showDescriptionRequiredError = computed(() => {
    return $v.value.community.description.$dirty && $v.value.community.description.required.$invalid;
});

const isDescriptionNearLimit = computed(() => {
    const count = community.description?.length || 0;
    return count > 1900;
});
</script>

<style>
#intro {
    transition: height 1.25s ease-in, transform 1.25s ease-in, opacity 1.25s ease-in;
    overflow: hidden;
}

.shrink {
    height: 0;
    opacity: 0;
}
</style>