<template>
    <div class="m-auto w-full px-8 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
        <div class="flex flex-col md:flex-row">
            <div class="w-full inline-block md:w-2/6 md:px-8 lg:p-20">
                <div class="sticky top-16 items-center flex flex-col">
                    <!-- Clickable Image/Upload Area -->
                    <div class="w-full relative">
                        <div class="aspect-square w-full rounded-full overflow-hidden">
                            <label for="image-upload" class="absolute inset-0 bg-[#717171] rounded-full flex justify-center items-center cursor-pointer">
                                <div 
                                    :style="{ background: user.hexColor}" 
                                    class="cursor-pointer overflow-hidden flex w-full h-full rounded-full hover:shadow-custom-2 ">
                                    <!-- -->
                                    <!-- If user is logged in -->
                                    <template v-if="user">
                                        <template v-if="user.largeImagePath">
                                            <picture>
                                                <source 
                                                    type="image/webp" 
                                                    :srcset="`${imageUrl}${user.thumbImagePath}`"> 
                                                <img 
                                                    class="w-full h-full"
                                                    :src="`${imageUrl}${user.thumbImagePath.slice(0, -4)}jpg?timestamp=${new Date().getTime()}`" 
                                                    :alt="user.name + `'s account`">
                                            </picture>
                                        </template>
                                        <template v-else-if="user.gravatar">
                                            <img 
                                                :src="user.gravatar" 
                                                class="w-full h-full"
                                                :alt="user.name + `'s account`">
                                        </template>
                                        <template v-else>
                                            <svg class="w-full h-full fill-white p-12">
                                                <use :xlink:href="`/storage/website-files/icons.svg#ri-user-line`" />
                                            </svg>
                                        </template>
                                    </template>
                                </div>
                            </label>
                        </div>
                    </div>
                    <input type="file" id="image-upload" @change="updateImage" hidden>
                </div>
            </div>
            <!-- User information Section -->
            <div v-if="owner" class="w-full flex flex-col gap-16">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg w-full">
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
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
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

const updateImage = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    user.image = URL.createObjectURL(file);

    isUploading.value = true;
    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post(`/users/${user.id}`, formData);
        user.thumbImagePath = `${response.data.thumbImagePath}?${new Date().getTime()}`;
    } catch (error) {
        if (error.response && error.response.data.error) {
            alert(error.response.data.error);
        } else {
            alert(error);
        }
    } finally {
        isUploading.value = false; // End the upload indicator
    }
};

watch([() => props.owner, () => props.loaduser], ([newOwner, newLoaduser], [oldOwner, oldLoaduser]) => {
    if (newOwner && newLoaduser && newOwner.id === newLoaduser.id) {
        user.email = newOwner.email;
        Object.assign(user, newLoaduser);
    }
}, { immediate: true });

</script>
