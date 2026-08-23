<template>
    <div v-bind="$attrs">
        <div class="flex justify-end relative z-30 items-center">
            <!-- If user is logged in -->
            <template v-if="user">
                <div
                    class="relative ml-8"
                    v-if="!user.organizer">
                    <a href="/hosting/getting-started">
                        <span class="text-xl font-medium hover:text-black hover:font-semibold">Submit Your Experience</span>
                    </a>
                </div>
                <div
                    class="relative ml-8"
                    v-if="user.organizer">
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
                                class="rounded-full bg-default-red w-6 h-6 absolute top-[-0.5rem] right-[-0.5rem] border border-white"
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
                    class="z-[1100] mt-8 min-w-[26rem] rounded-2xl overflow-hidden block shadow-custom-1 absolute right-0 top-full bg-white py-2">
                    <template v-if="user">
                        <a
                            v-if="user.hasMessages"
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            href="/inbox">
                            <component :is="RiMessage3Line" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Messages
                            <div v-if="user.unread" class="ml-auto rounded-full bg-red-500 w-3 h-3 flex-shrink-0"></div>
                        </a>
                        <a
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            :href="`/users/${user.id}`">
                            <component :is="RiUserLine" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Profile
                        </a>

                        <div class="my-2 border-t border-neutral-200"></div>

                        <a
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            href="/notifications">
                            <component :is="RiNotification3Line" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Notifications
                        </a>
                        <a
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            href="/account-settings">
                            <component :is="RiSettings3Line" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Account settings
                        </a>

                        <div class="my-2 border-t border-neutral-200"></div>

                        <a
                            v-if="!user.organizer"
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            href="/hosting/getting-started">
                            <component :is="RiHome4Line" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            List Your Event
                        </a>
                        <a
                            v-else
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            href="/teams">
                            <component :is="RiHome4Line" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Organizations
                        </a>
                        <a
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            href="/communities">
                            <component :is="RiTeamLine" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Communities
                        </a>
                        <a
                            v-if="user.isCurator"
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            href="/admin/dashboard">
                            <component :is="RiShieldCheckLine" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Admin Dashboard
                        </a>

                        <!-- Logout sat flush against the navigation items above it, so
                             overshooting Admin Dashboard by one row logged you out. Give it
                             its own section. -->
                        <div class="my-2 border-t border-neutral-200"></div>
                        <div
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            @click="logout">
                            <component :is="RiLogoutBoxRLine" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                            Log out
                        </div>
                    </template>
                    <template v-else>
                        <div
                            class="font-medium text-xl px-6 py-4 cursor-pointer flex gap-x-4 items-center whitespace-nowrap w-full hover:bg-neutral-100"
                            @click.prevent="onLogin">
                            <component :is="RiUserLine" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
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
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Teleport, Transition } from 'vue';
import Login from '../../Auth/login.vue';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective';
import {
    RiMessage3Line,
    RiUserLine,
    RiNotification3Line,
    RiSettings3Line,
    RiTeamLine,
    RiHome4Line,
    RiShieldCheckLine,
    RiLogoutBoxRLine,
} from '@remixicon/vue';

const props = defineProps(['user']);
const emit = defineEmits(['close']);
const imageUrl = import.meta.env.VITE_IMAGE_URL;

const userColor = computed(() => {
    return props.user ? props.user.hexColor : '#717171';
});

const dropdown = ref(false);
const showLogin = ref(false);

// Lets any component (e.g. favorite-event.vue) request the login modal without
// a shared store — mirrors show-gallery.vue's own CustomEvent pattern. Named +
// removed on unmount so remounts (SPA nav, HMR) don't stack duplicate listeners.
const openLoginModal = () => {
    showLogin.value = true;
};
window.addEventListener('open-login-modal', openLoginModal);
onUnmounted(() => {
    window.removeEventListener('open-login-modal', openLoginModal);
});

const logout = async () => {
    // A session that's already expired/invalidated server-side (idle
    // timeout, logged out in another tab, session row pruned) makes this
    // POST 401 — the user is already logged out from the server's
    // perspective, so that's not a failure worth surfacing. Reload either
    // way to drop the stale client-side "logged in" UI.
    try {
        await axios.post('/logout');
    } catch (error) {
        console.error('Error logging out:', error);
    }
    location.reload();
};

const onLogin = () => {
    dropdown.value = false;
    showLogin.value = true;
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
