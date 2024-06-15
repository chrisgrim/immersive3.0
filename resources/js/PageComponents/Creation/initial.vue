<template>
    <div class="w-full h-screen">
        <div class="max-w-screen-xl p-8 m-auto">
            <div class="w-1/2 m-auto my-28">
                <div>
                    <div class="h-60" id="intro" :class="{ shrink: !!team.name || !!team.description }">
                        <h5 class="font-bold">Step 1</h5>
                        <h3 class="mt-10 mb-28 text-6xl leading-[4rem]">Let's create your organization</h3>
                    </div>
                    <div class="flex flex-row items-center w-full">
                        <div>
                            <!-- Clickable Image/Upload Area -->
                            <div class="w-60 relative">
                                <div class="aspect-square w-full rounded-full">
                                    <label
                                        for="image-upload"
                                        :class="{
                                            'absolute inset-0 flex justify-center items-center cursor-pointer rounded-full hover:border-gray-500 group': true,
                                            'border-4 border-black': !imagePreview,
                                            'border-none': imagePreview,
                                        }"
                                    >
                                        <div class="cursor-pointer overflow-hidden w-full h-full rounded-full group-hover:shadow-2xl relative flex items-center justify-center">
                                            <template v-if="imagePreview">
                                                <img class="absolute inset-0 w-full h-full object-cover" :src="imagePreview" :alt="team.name + `'s account`">
                                            </template>
                                                <component :is="RiImageAddLine" class="group-hover:text-gray-500" style="width: 50px; height: 50px;"/>

                                        </div>
                                    </label>
                                </div>
                            </div>
                            <input type="file" id="image-upload" @change="updateImage" hidden>
                        </div>
                        <div class="ml-8 align-bottom relative flex flex-col w-full">
                            <div class="relative w-full flex">
                                <div v-if="isEditingName || !team.name" class="w-full">
                                    <textarea 
                                        id="Name" 
                                        rows="3" 
                                        placeholder="Team Name" 
                                        ref="nameInput" 
                                        @input="clearError('name')"
                                        v-model="team.name" 
                                        @blur="handleBlurName" 
                                        @focus="handleFocusName"
                                        class="text-4xl p-4 border focus:border-indigo-500 focus:ring-indigo-500 rounded-2xl focus:shadow-lg mt-1 block w-full">
                                    </textarea>
                                </div>
                                <h2 v-else @click="toggleEditName" class="font-bold text-6xl leading-[4rem] w-full flex items-center overflow-hidden">
                                    <span class="w-full">{{ team.name }}</span>
                                </h2>
                            </div>
                            <p v-if="$v.team.name.$dirty && $v.team.name.required.$invalid" class="text-white bg-red-500 text-lg mt-1 px-4 py-2">The team name is required</p>
                            <div v-if="errors && errors.name" class="w-full">
                                <p class="text-white bg-red-500 text-lg mt-1 px-4 py-2">{{ errors.name[0] }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="errors && errors.image" class="w-full mt-4">
                        <p class="text-white bg-red-500 text-lg mt-1 px-4 py-2">{{ errors.image[0] }}</p>
                    </div>
                </div>
                <!-- Team Information Form -->
                <div v-if="team.name || team.description" class="mt-12 w-full overflow-auto">
                    <div class="w-full">
                        <textarea 
                            id="description" 
                            rows="6" 
                            placeholder="Now please add a description about your organization for our users." 
                            ref="descriptionInput"
                            @input="clearError('description')"
                            v-model="team.description" 
                            @blur="handleBlurDescription" 
                            @focus="handleFocusDescription"
                            :class="{
                                'p-4 border focus:border-indigo-500 focus:ring-indigo-500 rounded-2xl focus:shadow-lg mt-1 block w-full': true,
                                'border-0': !isEditingDescription && team.description
                            }"
                            required>
                        </textarea>
                    </div>
                    <p v-if="$v.team.description.$dirty && $v.team.description.$invalid" class="text-white bg-red-500 text-lg mt-1 px-4 py-2">The team description is required</p>
                    <div v-if="errors && errors.description" class="w-full">
                        <p class="text-white bg-red-500 text-lg mt-1 px-4 py-2">{{ errors.description[0] }}</p>
                    </div>
                </div>
                <div v-if="team.description" class="w-full mt-12">
                    <div class="grid grid-cols-3 gap-4">
                        <div 
                            v-for="media in socialMediaList" 
                            :key="media.name" 
                            @click="handleDivClick(media.name)"
                            :class="{
                                'border-[#e5e7eb] text-gray-400': !team[media.model] && currentMedia !== media.name,
                                'border-black text-black border-2 bg-gray-100': team[media.model] || currentMedia === media.name,
                            }"
                            class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-colors duration-200"
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
                            <!-- Validation message for email -->
                            <p v-if="media.name === 'email' && $v.team.email.$dirty && $v.team.email.email.$invalid" 
                               class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                                The email must be a valid email
                            </p>
                            <p v-if="media.name === 'website' && $v.team.website.$dirty && $v.team.website.url.$invalid" 
                               class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                                Please be sure to have https:// and .com
                            </p>
                        </div>
                    </div>
                </div>
                <div v-if="team.name && team.description" class="w-full flex justify-end">
                    <button 
                        class="text-right p-4 rounded-2xl bg-black text-white mt-8"
                        :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }"
                        :disabled="isSubmitting"
                        @click="onSubmit"
                    >
                        Create Team
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, nextTick, shallowRef } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, email, maxLength, url } from '@vuelidate/validators';

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

// Validation rules
const rules = {
    team: {
        name: {
            required,
            maxLength: maxLength(120),
        },
        description: {
            required,
            maxLength: maxLength(20000),
        },
        email: {
            email,
        },
        website: {
            url
        }
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

const socialMediaList = shallowRef([
    { name: 'website', icon: RiSearchLine, placeholder: 'Website', model: 'website', showInput: false },
    { name: 'email', icon: RiMailLine, placeholder: 'Email', model: 'email', showInput: false },
    { name: 'instagramHandle', icon: RiInstagramLine, placeholder: 'Instagram Handle', model: 'instagramHandle', showInput: false },
    { name: 'twitterHandle', icon: RiTwitterLine, placeholder: 'Twitter Handle', model: 'twitterHandle', showInput: false },
    { name: 'facebookHandle', icon: RiFacebookLine, placeholder: 'Facebook Handle', model: 'facebookHandle', showInput: false },
    { name: 'patreon', icon: RiPatreonLine, placeholder: 'Patreon Handle', model: 'patreon', showInput: false }
]);

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
