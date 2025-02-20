<template>
    <div v-bind="$attrs">
        <div class="flex justify-end relative z-30 items-center">
            <!-- If user is logged in -->
            <template v-if="user">
                <div 
                    class="relative ml-8" 
                    v-if="!user.hasCreatedOrganizers">
                    <a href="/hosting/getting-started">
                        <span class="text-xl font-medium hover:text-black hover:font-semibold">Submit Your Experience</span>
                    </a>
                </div>
                <div 
                    class="relative ml-8" 
                    v-if="user.hasCreatedOrganizers && user.organizer">
                    <a href="/hosting/events">
                        <span class="text-xl font-medium hover:text-black hover:font-semibold truncate block max-w-[200px]">
                            {{user.organizer.name}}
                        </span>
                    </a>
                </div>
            </template>

            <!-- If user is guest -->
            <template v-else>
                <div class="relative ml-8">
                    <a href="/register?create=true">
                        <span class="text-xl font-medium hover:text-black hover:font-semibold">Submit Your Experience</span>
                    </a>
                </div>
            </template>
            <div class="relative ml-8" v-click-outside="closeDropdown">
                <div class="w-12 h-12">
                    <div 
                        :style="{ background: userColor }" 
                        @click="onToggle" 
                        :class="{ 'shadow-custom-2': dropdown }"
                        class="cursor-pointer overflow-hidden flex justify-center items-center w-12 h-12 rounded-full hover:shadow-custom-2">
                        <!-- If user is logged in -->
                        <template v-if="user">
                            <div 
                                class="rounded-full bg-default-red w-4 h-4 absolute top-0 right-0 border border-white"
                                v-if="user.unread"></div>
                            <template v-if="user.thumbImagePath">
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
                                    class="w-12 h-12"
                                    :alt="`${user.name}’s account`">
                            </template>
                            <template v-else>
                                <svg class="w-10 h-10 fill-white">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-user-line`"></use>
                                </svg>
                            </template>
                        </template>
                        <!-- If user is a guest -->
                        <template v-else>
                            <svg class="w-10 h-10 fill-white">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-user-line`"></use>
                            </svg>
                        </template>
                    </div>
                </div>
                <ul 
                    v-if="dropdown"
                    class="z-10 mt-8 min-w-[24rem] rounded-3xl overflow-hidden block shadow-custom-1 absolute right-0 top-full bg-white py-4">
                    <template v-if="user">
                        <a 
                            v-if="user.hasMessages"
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            href="/inbox">
                            Inbox
                            <div v-if="user.unread" class="ml-2 rounded-full bg-red-300 w-4 h-4 top-0 right-0"></div>
                        </a>
                        <a
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            href="/communities">
                            Communities
                        </a>
                        <a 
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            :href="`/users/${user.id}/edit`">
                            User Settings
                        </a>
                        <a 
                            v-if="!user.hasCreatedOrganizers"
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            href="/hosting/getting-started">
                            List Your Event
                        </a>
                        <a 
                            v-else
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            href="/teams">
                            Organizations
                        </a>
                        <a 
                            v-if="user.isCurator"
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            href="/admin/dashboard">
                            Admin Dashboard
                        </a>
                        <div 
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            @click="logout">
                            Logout
                        </div>
                    </template>
                    <template v-else>
                        <div 
                            class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
                            @click.prevent="onLogin">
                            Login / Sign Up
                        </div>
                    </template>
                </ul>
            </div>
        </div>

        <!-- Add Teleport with modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="transform opacity-0"
                enter-to-class="transform opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="transform opacity-100"
                leave-to-class="transform opacity-0"
            >
                <div v-if="showLogin" class="relative z-[100]">
                    <!-- Background overlay -->
                    <div class="fixed inset-0 bg-black bg-opacity-25 transition-opacity"></div>

                    <!-- Modal panel -->
                    <div class="fixed inset-0 z-[100] overflow-y-auto">
                        <div class="flex min-h-full items-center justify-center p-4">
                            <div 
                                class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 w-full max-w-[40rem]"
                            >
                                <!-- Close button - made larger and more visible -->
                                <button 
                                    @click="showLogin = false"
                                    class="absolute right-6 top-6 rounded-full text-neutral-400 hover:bg-neutral-100 hover:text-neutral-500 z-10 p-2"
                                >
                                    <span class="sr-only">Close</span>
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>

                                <!-- Login component -->
                                <Login @close="showLogin = false" class="bg-white" />
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Teleport, Transition } from 'vue';
import Login from '../../Auth/login.vue';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective';

const props = defineProps(['user']);
const emit = defineEmits(['close']);
const imageUrl = import.meta.env.VITE_IMAGE_URL;

const userColor = computed(() => {
    return props.user ? props.user.hexColor : '#717171';
});

const dropdown = ref(false);
const showLogin = ref(false);

const logout = async () => {
    await axios.post('/logout');
    location.reload();
};

const onLogin = () => {
    console.log('onLogin clicked');
    console.log('dropdown before:', dropdown.value);
    dropdown.value = false;
    console.log('dropdown after:', dropdown.value);
    console.log('showLogin before:', showLogin.value);
    showLogin.value = true;
    console.log('showLogin after:', showLogin.value);
};

const onToggle = () => {
    dropdown.value = !dropdown.value;
};

const closeDropdown = () => {
    dropdown.value = false;
};

// Register the directive
const vClickOutside = ClickOutsideDirective;

// Add inheritAttrs option to handle the class warning
defineOptions({
    inheritAttrs: true
})
</script>
