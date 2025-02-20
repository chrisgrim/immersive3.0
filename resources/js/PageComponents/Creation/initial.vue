<template>
    <div class="w-full min-h-screen">
        <div class="max-w-screen-xl p-10 m-auto">
            <div class="w-full md:w-1/2 m-auto my-12 sm:my-28">
                <div>
                    <div class="h-40 sm:h-60" id="intro" :class="{ shrink: !!team.name || !!team.description }">
                        <h5 class="font-bold">Step 1</h5>
                        <h3 class="mt-6 sm:mt-10 mb-16 sm:mb-28 text-4xl sm:text-6xl leading-[3rem] sm:leading-[4rem]">
                            Let's create your organization
                        </h3>
                    </div>
                    
                    <!-- Name and Image Section -->
                    <div class="flex flex-col sm:flex-row items-center sm:items-start w-full gap-8">
                        <!-- Image Upload -->
                        <div class="w-48 sm:w-60">
                            <p class="text-black font-medium mb-4">Profile Image</p>
                            <div class="relative">
                                <div class="aspect-square w-full rounded-full">
                                    <label
                                        for="image-upload"
                                        :class="{
                                            'absolute inset-0 flex justify-center items-center cursor-pointer rounded-full group': true,
                                            'border-2 border-black': !imagePreview,
                                            'border-none': imagePreview,
                                        }"
                                    >
                                        <div class="cursor-pointer overflow-hidden w-full h-full rounded-full relative flex items-center justify-center">
                                            <template v-if="imagePreview">
                                                <img class="absolute inset-0 w-full h-full object-cover" :src="imagePreview" :alt="team.name + `'s account`">
                                            </template>
                                            <component :is="RiImageAddLine" class="w-12 h-12 group-hover:text-neutral-500"/>
                                        </div>
                                    </label>
                                </div>
                                <input type="file" id="image-upload" @change="updateImage" accept="image/*" class="hidden">
                            </div>
                        </div>

                        <!-- Name Input -->
                        <div class="flex-1 w-full">
                            <div class="relative w-full">
                                <p class="text-black font-medium mb-4">Name</p>
                                <textarea 
                                    id="Name" 
                                    rows="3" 
                                    placeholder="Organization Name" 
                                    ref="nameInput" 
                                    @input="handleNameInput"
                                    v-model="team.name" 
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
                                    {{ team.name?.length || 0 }}/80
                                </div>
                                <!-- Name Error Messages -->
                                <p v-if="showNameMaxLengthError" 
                                   class="text-red-500 text-1xl mt-1 px-4">
                                    Organization name is too long.
                                </p>
                                <p v-if="showNameRequiredError" 
                                   class="text-red-500 text-1xl mt-1 px-4">
                                    Organization name is required
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Description Section -->
                    <div v-if="team.name" class="mt-8 sm:mt-12 w-full">
                        <div class="w-full">
                            <p class="text-black font-medium mb-4">Description</p>
                            <textarea 
                                id="description" 
                                rows="6" 
                                placeholder="Add a description about your organization" 
                                ref="descriptionInput"
                                @input="handleDescriptionInput"
                                v-model="team.description" 
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
                                {{ team.description?.length || 0 }}/2000
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Section -->
                    <div v-if="team.description" class="w-full mt-8 sm:mt-12">
                        <p class="text-black font-medium mb-4">Social Media</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div 
                                v-for="media in socialMediaList" 
                                :key="media.name" 
                                @click="handleDivClick(media.name)"
                                class="relative h-36 sm:h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-colors duration-200"
                                :class="{
                                    'border-neutral-300 text-gray-400': !team[media.model] && currentMedia !== media.name && !showValidationError(media),
                                    'border-[#222222] text-black border-2': team[media.model] && !showValidationError(media) || currentMedia === media.name,
                                    'border-red-500 focus:border-red-500 focus:shadow-focus-error': showValidationError(media)
                                }"
                            >
                                <div class="flex items-start justify-start w-full h-16 rounded-2xl">
                                    <component
                                        :is="media.icon"
                                        class="w-12 h-12"
                                        :class="{
                                            'text-[#adadad]': !team[media.model] && currentMedia !== media.name,
                                            'text-black': team[media.model] || currentMedia === media.name,
                                        }"
                                    />
                                </div>
                                <div v-if="currentMedia === media.name" class="w-full">
                                    <textarea 
                                        :placeholder="media.placeholder" 
                                        v-model="team[media.model]" 
                                        @blur="handleInputBlur(media.name)"
                                        @input="media.inputHandler && media.inputHandler($event)"
                                        rows="2"
                                        class="p-2 mt-2 border-none focus:border-black focus:ring-black rounded-md focus:shadow-lg w-full text-lg resize-none"
                                        @click.stop
                                        :ref="el => inputRefs[media.name] = el"
                                    ></textarea>
                                </div>
                                <div v-else class="overflow-hidden w-full">
                                    <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap"
                                        :class="{
                                            'text-[#adadad]': !team[media.model],
                                            'text-black': team[media.model],
                                        }">
                                        {{ team[media.model] || media.placeholder }}
                                    </h4>
                                </div>
                                <!-- Updated validation messages -->
                                <p v-if="media.name === 'email' && $v.team.email.$dirty && $v.team.email.email.$invalid" 
                                   class="text-red-500 text-sm mt-1 absolute bottom-2">
                                    The email must be a valid email
                                </p>
                                <p v-if="media.name === 'website' && team.website && $v.team.website.$dirty && $v.team.website.$invalid" 
                                   class="text-red-500 text-sm mt-1 absolute bottom-2">
                                    Website must start with https:// (e.g., https://example.com)
                                </p>
                                <p v-if="['instagramHandle', 'twitterHandle', 'facebookHandle', 'patreon'].includes(media.name) && 
                                          team[media.model] && 
                                          $v.value?.team?.[media.model]?.$dirty && 
                                          $v.value?.team?.[media.model]?.maxLength?.$invalid" 
                                   class="text-red-500 text-sm mt-1 absolute bottom-2">
                                    {{ media.placeholder }} is too long (max {{ media.maxLength }} characters)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div v-if="team.name && team.description" class="w-full flex justify-center sm:justify-end mt-8 sm:mt-12">
                        <button 
                            class="w-full sm:w-auto text-center sm:text-right p-4 rounded-2xl transition-colors duration-200"
                            :class="{
                                'bg-black text-white hover:bg-gray-800': isFormComplete,
                                'bg-gray-200 text-gray-400 cursor-not-allowed': !isFormComplete || isSubmitting
                            }"
                            :disabled="!isFormComplete || isSubmitting"
                            @click="onSubmit"
                        >
                            {{ isSubmitting ? 'Creating...' : 'Create Organization' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, nextTick, computed } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, email, maxLength, helpers } from '@vuelidate/validators';

import { 
    RiSearchLine,
    RiMailLine,
    RiInstagramLine,
    RiTwitterLine,
    RiFacebookLine,
    RiPatreonLine,
    RiImageAddLine
} from "@remixicon/vue";

// Define props
const props = defineProps({
    team: {
        type: Object,
        default: () => ({
            name: '',
            description: '',
            website: '',
            email: '',
            instagramHandle: '',
            twitterHandle: '',
            facebookHandle: '',
            patreon: '',
            thumbImagePath: ''
        })
    },
    user: {
        type: Object,
        required: true
    }
});

// Create a reactive team object for use with Vuelidate
const team = reactive(props.team);

// Updated URL validator that requires https://
const customURL = helpers.regex(/^https:\/\/([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/);

// Update the validation rules
const rules = {
    team: {
        name: {
            required,
            maxLength: maxLength(80),
        },
        description: {
            required,
            maxLength: maxLength(2000),
        },
        email: {
            email,
        },
        website: {
            url: (value) => {
                if (!value) return true; // Allow empty website
                return customURL(value);
            }
        },
        // Add new validations for social media handles
        instagramHandle: { maxLength: maxLength(30) },
        twitterHandle: { maxLength: maxLength(15) },
        facebookHandle: { maxLength: maxLength(50) },
        patreon: { maxLength: maxLength(30) }
    },
};

// Initialize Vuelidate
const $v = useVuelidate(rules, { team });

// Other reactive state
const currentMedia = ref(null);
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const isEditingName = ref(!team.name);
const nameInput = ref(null);
const isEditingDescription = ref(!team.description);
const descriptionInput = ref(null);
const imageFile = ref(null);
const imagePreview = ref(null);
const errors = ref({});
const isSubmitting = ref(false);
const inputRefs = reactive({});

// Add debounce for website validation
let websiteTimeout;
const handleWebsiteInput = (event) => {
    clearTimeout(websiteTimeout);
    websiteTimeout = setTimeout(() => {
        if (team.website) {
            $v.value.team.website.$touch();
        }
    }, 500); // Wait 500ms after typing stops before validating
};

// Computed Properties
const isFormComplete = computed(() => {
    const hasRequiredFields = team.name?.trim() && team.description?.trim();
    const hasValidWebsite = !team.website || (team.website && !$v.value.team.website.$invalid);
    const hasValidEmail = !team.email || (team.email && !$v.value.team.email.$invalid);
    
    return hasRequiredFields && hasValidWebsite && hasValidEmail;
});

const showNameError = computed(() => {
    return $v.value.team.name.$dirty && $v.value.team.name.$error;
});

const showNameMaxLengthError = computed(() => {
    return $v.value.team.name.$dirty && $v.value.team.name.maxLength.$invalid;
});

const showNameRequiredError = computed(() => {
    return $v.value.team.name.$dirty && $v.value.team.name.required.$invalid;
});

const isNameNearLimit = computed(() => {
    const count = team.name?.length || 0;
    return count > 65;
});

const showDescriptionError = computed(() => {
    return $v.value.team.description.$dirty && $v.value.team.description.$error;
});

const showDescriptionMaxLengthError = computed(() => {
    return $v.value.team.description.$dirty && $v.value.team.description.maxLength.$invalid;
});

const showDescriptionRequiredError = computed(() => {
    return $v.value.team.description.$dirty && $v.value.team.description.required.$invalid;
});

const isDescriptionNearLimit = computed(() => {
    const count = team.description?.length || 0;
    return count > 1800;
});

const showValidationError = (media) => {
    if (!media?.model || !team[media.model]) return false;
    
    if (media.name === 'website') {
        return $v.value?.team?.website?.$dirty && $v.value?.team?.website?.$invalid;
    }
    if (media.name === 'email') {
        return $v.value?.team?.email?.$dirty && $v.value?.team?.email?.$invalid;
    }
    // Add validation for social media handles
    if (['instagramHandle', 'twitterHandle', 'facebookHandle', 'patreon'].includes(media.name)) {
        return $v.value?.team?.[media.model]?.$dirty && $v.value?.team?.[media.model]?.$invalid;
    }
    return false;
};

// Methods
const updateImage = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    imageFile.value = file; // Store the file for later upload
    imagePreview.value = URL.createObjectURL(file); // Create a preview URL for the image
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
    for (const key in team) {
        formData.append(key, team[key]);
    }
    if (imageFile.value) {
        formData.append('image', imageFile.value);
    }

    try {
        // Create the organizer with image upload
        const response = await axios.post('/organizers', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        if (response.data.redirect) {
            window.location.href = response.data.redirect;
        }
    } catch (error) {
        if (error.response && error.response.data && error.response.data.errors) {
            console.log(error);
            errors.value = error.response.data.errors;
        } else {
            alert('Failed to update team information');
        }
    } finally {
        isSubmitting.value = false;
    }
};

const handleBlurName = () => {
    if (document.activeElement !== nameInput.value) {
        isEditingName.value = false;
    }
};

const handleFocusName = () => {
    isEditingName.value = true;
};

const handleBlurDescription = () => {
    if (document.activeElement !== descriptionInput.value) {
        isEditingDescription.value = false;
    }
};

const handleInputBlur = (mediaName) => {
    currentMedia.value = null;
    if (team[mediaName]) {
        $v.value.team[mediaName].$touch();
    }
};

const handleFocusDescription = () => {
    isEditingDescription.value = true;
};

const handleDivClick = (mediaName) => {
    currentMedia.value = mediaName;
    nextTick(() => {
        if (inputRefs[mediaName]) {
            inputRefs[mediaName].focus();
        } else {
            console.log('inputRefs not available for media:', mediaName);
        }
    });
};

const toggleEditName = () => {
    isEditingName.value = true;
    nextTick(() => {
        nameInput.value.focus();
    });
};

const toggleEditDescription = () => {
    isEditingDescription.value = true;
    nextTick(() => {
        descriptionInput.value.focus();
    });
};

const clearError = (field) => {
    if (errors.value[field]) {
        delete errors.value[field];
    }
};

const handleNameInput = () => {
    $v.value.team.name.$touch();
    if (team.name?.length > 80) {
        team.name = team.name.slice(0, 80);
    }
};

const handleDescriptionInput = () => {
    $v.value.team.description.$touch();
    if (team.description?.length > 2000) {
        team.description = team.description.slice(0, 2000);
    }
};

// Add input handlers for social media
const handleSocialInput = (media) => {
    if (!media || !media.model || !media.maxLength) return;
    
    if (team[media.model]?.length > media.maxLength) {
        team[media.model] = team[media.model].slice(0, media.maxLength);
        $v.value?.team?.[media.model]?.$touch();
    }
};

// Update socialMediaList with maxLength and inputHandler
const socialMediaList = [
    { 
        name: 'website', 
        icon: RiSearchLine, 
        placeholder: 'Website (must start with https://)', 
        model: 'website',
        inputHandler: handleWebsiteInput 
    },
    { 
        name: 'email', 
        icon: RiMailLine, 
        placeholder: 'Email', 
        model: 'email' 
    },
    { 
        name: 'instagramHandle', 
        icon: RiInstagramLine, 
        placeholder: 'Instagram Handle', 
        model: 'instagramHandle',
        maxLength: 30,
        inputHandler: handleSocialInput 
    },
    { 
        name: 'twitterHandle', 
        icon: RiTwitterLine, 
        placeholder: 'Twitter Handle', 
        model: 'twitterHandle',
        maxLength: 15,
        inputHandler: handleSocialInput 
    },
    { 
        name: 'facebookHandle', 
        icon: RiFacebookLine, 
        placeholder: 'Facebook Handle', 
        model: 'facebookHandle',
        maxLength: 50,
        inputHandler: handleSocialInput 
    },
    { 
        name: 'patreon', 
        icon: RiPatreonLine, 
        placeholder: 'Patreon Handle', 
        model: 'patreon',
        maxLength: 30,
        inputHandler: handleSocialInput 
    }
];

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

    /* Add responsive text sizing for textareas */
    @media (max-width: 640px) {
        textarea {
            font-size: 16px !important; /* Prevents zoom on mobile */
        }
    }
</style>
