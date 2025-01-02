<template>
    <div class="m-auto w-full px-8 py-8 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
        <div class="flex flex-col md:flex-row gap-16">
            <div class="md:w-auto">
                <div class="flex flex-col items-center shadow-custom-6 w-full md:w-[30rem] p-8 py-16 rounded-3xl">
                    
                    <!-- Profile Image Section -->
                    <div class="w-44 flex-shrink-0">
                        <div class="relative w-full">
                            <div class="relative w-full aspect-square">
                                <!-- Add the file input -->
                                <input 
                                    type="file"
                                    id="image-upload"
                                    @change="updateImage"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="hidden"
                                />
                                <label for="image-upload" class="absolute inset-0 flex justify-center items-center cursor-pointer">
                                    <div 
                                        :style="{ background: user.hexColor }" 
                                        class="w-full h-full rounded-full overflow-hidden hover:border-neutral-300 transition-all shadow-sm"
                                    >
                                        <!-- Loading Spinner -->
                                        <div v-if="isUploading" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10">
                                            <svg class="animate-spin h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </div>
                                        
                                        <!-- Image Display Logic -->
                                        <template v-if="user">
                                            <template v-if="user.images && user.images.length > 0">
                                                <picture>
                                                    <source 
                                                        type="image/webp" 
                                                        :srcset="`${imageUrl}${user.images[0].thumb_image_path}`"> 
                                                    <img 
                                                        class="w-full h-full object-cover"
                                                        :src="`${imageUrl}${user.images[0].thumb_image_path.slice(0, -4)}jpg?timestamp=${new Date().getTime()}`" 
                                                        :alt="user.name + `'s account`">
                                                </picture>
                                            </template>
                                            <template v-else-if="user.thumbImagePath">
                                                <picture>
                                                    <source 
                                                        type="image/webp" 
                                                        :srcset="`${imageUrl}${user.thumbImagePath}`"> 
                                                    <img 
                                                        class="w-full h-full object-cover"
                                                        :src="`${imageUrl}${user.thumbImagePath.slice(0, -4)}jpg?timestamp=${new Date().getTime()}`" 
                                                        :alt="user.name + `'s account`">
                                                </picture>
                                            </template>
                                            <template v-else-if="user.gravatar">
                                                <img 
                                                    :src="user.gravatar" 
                                                    class="w-full h-full object-cover"
                                                    :alt="user.name + `'s account`">
                                            </template>
                                            <template v-else>
                                                <svg class="w-full h-full p-6 text-neutral-500" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="presentation" focusable="false" style="display: block; fill: currentcolor;">
                                                    <path d="m16 .7c-8.437 0-15.3 6.863-15.3 15.3s6.863 15.3 15.3 15.3 15.3-6.863 15.3-15.3-6.863-15.3-15.3-15.3zm0 28c-4.021 0-7.605-1.884-9.933-4.81a12.425 12.425 0 0 1 6.451-4.4 6.507 6.507 0 0 1 -3.018-5.49c0-3.584 2.916-6.5 6.5-6.5s6.5 2.916 6.5 6.5a6.513 6.513 0 0 1 -3.019 5.491 12.42 12.42 0 0 1 6.452 4.4c-2.328 2.925-5.912 4.809-9.933 4.809z"></path>
                                                </svg>
                                            </template>
                                        </template>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- User Info Section -->
                    <div class="flex-grow">
                        <div class="flex justify-between mt-8">
                            <div>
                                <h1 class="text-4xl font-medium leading-tight text-center">{{ user.name }}</h1>
                                <p class="mt-4 font-medium text-1xl text-center">{{ userType }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="imageError" class="mt-4 text-red-500 text-center text-sm">
                    {{ imageError }}
                </div>
            </div>

            <!-- User information Section -->
            <div v-if="owner" class="flex-1 flex flex-col gap-16 md:px-8">
                <div class="pb-16 bg-white w-full border-b border-neutral-200">
                    <section>
                        <header>
                            <h2 class="text-3xl font-medium text-gray-900">Profile Information</h2>
                            <p class="mt-1 text-xl text-gray-600">Update your account's profile information and email address.</p>
                        </header>
                        <form class="mt-6 space-y-6">
                            <div>
                                <label for="name" class="block font-medium text-xl text-gray-700"><span>Name</span></label>
                                <input id="name" type="text" v-model="user.name" :readonly="!owner" :disabled="!owner"
                                    class="p-4 border focus:border-indigo-500 focus:ring-indigo-500 rounded-md focus:shadow-lg mt-1 block w-full"
                                    required autofocus autocomplete="name">
                                <div class="mt-2" style="display: none;">
                                    <p class="text-xl text-red-600"></p>
                                </div>
                            </div>
                            <div>
                                <label for="email" class="block font-medium text-xl text-gray-700"><span>Email</span></label>
                                <input id="email" type="email" v-model="user.email" :readonly="!owner" :disabled="!owner"
                                    class="p-4 border focus:border-indigo-500 focus:ring-indigo-500 rounded-md focus:shadow-lg mt-1 block w-full"
                                    required autocomplete="username">
                                <div class="mt-2" style="display: none;">
                                    <p class="text-xl text-red-600"></p>
                                </div>
                                <div data-lastpass-icon-root=""
                                    style="position: relative !important; height: 0px !important; width: 0px !important; float: left !important;">
                                </div>
                            </div>
                            <p v-if="!user.email_verified_at" class="mt-1 text-xl text-gray-600 bg-blue-300 p-4 text-white">Please check your email to verify your account.</p>
                            <div class="flex items-center gap-4">
                                <button @click.prevent="onSubmit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 disable:bg-gray-200 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    :disabled="disabled">
                                    Save
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
                <div class="pb-40 md:pb-16">
                    <section>
                        <header>
                            <h2 class="text-3xl font-medium text-gray-900">Update Password</h2>
                            <p class="mt-1 text-xl text-gray-600">Passwords must be at least 8 characters long.</p>
                        </header>
                        <form class="mt-6 space-y-6" @submit.prevent="submitForm">
                            <div>
                                <label for="current_password" class="block font-medium text-xl text-gray-700">
                                    <span>Current Password</span>
                                </label>
                                <input id="current_password" v-model="currentPassword" type="password" autocomplete="current-password"
                                       class="p-4 border focus:border-indigo-500 focus:ring-indigo-500 rounded-md focus:shadow-sm mt-1 block w-full">
                                <div class="mt-2" style="display: none;"><p class="text-xl text-red-600"></p></div>
                            </div>
                            <div>
                                <label for="password" class="block font-medium text-xl text-gray-700">
                                    <span>New Password</span>
                                </label>
                                <input id="password" v-model="newPassword" type="password" autocomplete="new-password"
                                       class="p-4 border focus:border-indigo-500 focus:ring-indigo-500 rounded-md focus:shadow-sm mt-1 block w-full">
                                <div class="mt-2" style="display: none;"><p class="text-xl text-red-600"></p></div>
                            </div>
                            <div>
                                <label for="password_confirmation" class="block font-medium text-xl text-gray-700">
                                    <span>Confirm Password</span>
                                </label>
                                <input id="password_confirmation" v-model="confirmPassword" type="password" autocomplete="new-password"
                                       class="p-4 border focus:border-indigo-500 focus:ring-indigo-500 rounded-md focus:shadow-sm mt-1 block w-full">
                                <div class="mt-2" style="display: none;"><p class="text-xl text-red-600"></p></div>
                            </div>
                            <div class="flex items-center gap-4">
                                <button type="submit"
                                        :disabled="disabled"
                                        class="inline-flex items-center px-4 py-2 disabled:bg-gray-200 bg-gray-800 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Save
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
        <transition name="slide-fade">
            <div
                v-if="updated"
                class="fixed top-16 right-16 z-[2003] rounded-2xl bg-green-500 p-8">
                <p class="text-white">Your profile has been updated.</p>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';

const props = defineProps(['loaduser', 'owner']);
const user = reactive({ ...props.loaduser });
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const onSent = ref(false);
const updated = ref(false);
const currentPassword = ref(null);
const newPassword = ref(null);
const confirmPassword = ref(null);
const isUploading = ref(false);
const disabled = ref(false);
const imageError = ref(null);
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

const userType = computed(() => {
    if (user.isAdmin) return 'Admin';
    if (user.isCurator) return 'Curator';
    if (user.isModerator) return 'Moderator';
    return 'Guest';
});


// Methods
const onSubmit = async () => {
    disabled.value = true;
    try {
        const response = await axios.post(`/users/${user.id}`, user); // Assuming 'user' is the payload
        Object.assign(user, response.data);
        updated.value = true;
        setTimeout(() => (updated.value = false), 3000);
    } catch (err) {
        console.error(err);
    } finally {
        disabled.value = false;
    }
};

const resend = async () => {
    try {
        await axios.post('/email/verification-notification');
        onSent.value = true;
        setTimeout(() => (onSent.value = false), 10000);
    } catch (err) {
        console.error(err);
    }
};

const validateImage = (file) => {
    imageError.value = null;
    
    // Check file type
    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        imageError.value = 'Please upload a JPG, PNG, or WebP image.';
        return false;
    }
    
    // Check file size
    if (file.size > MAX_FILE_SIZE) {
        imageError.value = 'Image must be less than 5MB.';
        return false;
    }
    
    return true;
};

const updateImage = async (event) => {
    const file = event.target.files[0];
    if (!file) return;
    
    // Reset error
    imageError.value = null;
    
    // Validate image
    if (!validateImage(file)) {
        event.target.value = ''; // Clear the input
        return;
    }

    isUploading.value = true;
    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post(`/users/${user.id}`, formData);
        
        if (response.data.images && response.data.images.length > 0) {
            if (!user.images) {
                user.images = [];
            }
            user.images = response.data.images;
            updated.value = true;
            setTimeout(() => (updated.value = false), 3000);
        } else {
            throw new Error('No image data received');
        }
    } catch (error) {
        console.error('Upload error:', error);
        imageError.value = error.response?.data?.error || 'Failed to upload image. Please try again.';
    } finally {
        isUploading.value = false;
        event.target.value = ''; // Clear the input
    }
};

watch([() => props.owner, () => props.loaduser], ([newOwner, newLoaduser], [oldOwner, oldLoaduser]) => {
    if (newOwner && newLoaduser && newOwner.id === newLoaduser.id) {
        user.email = newOwner.email;
        Object.assign(user, newLoaduser);
    }
}, { immediate: true });

</script>
