<template>
    <div v-bind="$attrs">
        <div class="flex justify-end relative z-30 items-center">
            <!-- If user is logged in -->
            <template v-if="user">
                <!-- On a phone these words would push the picture off screen; the menu has them. -->
                <div
                    class="relative ml-8 hidden md:block"
                    v-if="!user.organizer && !hosting">
                    <a href="/hosting/getting-started">
                        <span class="text-xl font-medium hover:text-black hover:font-semibold">Submit Your Experience</span>
                    </a>
                </div>
                <!-- On the hosting page the organization takes the spot: bigger name, its edit button. -->
                <div v-if="hosting" class="relative ml-8 flex items-center gap-4 min-w-0">
                    <a :href="`/organizers/${hosting.slug}/edit`" aria-label="Edit organization" class="flex-shrink-0 rounded-full bg-gray-100 w-14 h-14 md:w-16 md:h-16 flex items-center justify-center hover:bg-gray-200">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </a>
                    <a :href="`/organizers/${hosting.slug}`" class="text-[2rem] md:text-[2.6rem] leading-tight font-semibold text-black hover:underline truncate max-w-[48rem]">{{ hosting.name }}</a>
                </div>
                <div
                    class="relative ml-8 hidden md:block"
                    v-else-if="user.organizer">
                    <a href="/hosting/events">
                        <span class="text-xl font-medium hover:text-black hover:font-semibold truncate block max-w-[200px]">
                            {{user.organizer.name}}
                        </span>
                    </a>
                </div>
            </template>

            <!-- If user is guest -->
            <template v-else>
                <div class="relative ml-8 hidden md:block">
                    <a href="/register?create=true">
                        <span class="text-xl font-medium hover:text-black hover:font-semibold">Submit Your Experience</span>
                    </a>
                </div>
            </template>
            <div class="relative ml-8">
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
                            <img v-if="hosting?.thumbImagePath" :src="`${imageUrl}${hosting.thumbImagePath}`" class="w-full h-full object-cover" :alt="hosting.name" />
                            <!-- Hosting, and the organization has no picture: its first letter. -->
                            <span v-else-if="hosting" class="text-white text-[1.8rem] font-semibold uppercase leading-none" data-hosting-initial>{{ (hosting.name || '?').trim().charAt(0) }}</span>
                            <template v-else-if="user.thumbImagePath">
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
                <!-- Airbnb's host menu: a sheet that slides in from the right over a dimmed page. -->
                <Teleport to="body">
                    <Transition
                        enter-active-class="transition-opacity duration-300 ease-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition-opacity duration-200 ease-in"
                        leave-to-class="opacity-0">
                        <div v-if="dropdown" class="fixed inset-0 z-[1100] bg-black/40" @click="closeDropdown"></div>
                    </Transition>
                    <Transition
                        enter-active-class="transition-transform duration-300 ease-out"
                        enter-from-class="translate-x-full"
                        leave-active-class="transition-transform duration-200 ease-in"
                        leave-to-class="translate-x-full">
                        <aside
                            v-if="dropdown"
                            role="dialog"
                            aria-modal="true"
                            aria-label="Menu"
                            class="fixed top-0 right-0 bottom-0 z-[1101] w-full max-w-[46rem] bg-white rounded-l-[3.2rem] shadow-[0_0_24px_rgba(0,0,0,0.18)] flex flex-col">
                            <div class="flex justify-end gap-4 px-12 pt-10">
                                <a v-if="user" href="/notifications" aria-label="Notifications" class="relative w-16 h-16 rounded-full bg-[#f7f7f7] hover:bg-[#ebebeb] flex items-center justify-center">
                                    <component :is="RiNotification3Line" class="w-7 h-7 text-[#222222]" />
                                </a>
                                <button type="button" aria-label="Close menu" class="w-16 h-16 rounded-full bg-[#f7f7f7] hover:bg-[#ebebeb] flex items-center justify-center" @click="closeDropdown">
                                    <component :is="RiCloseLine" class="w-7 h-7 text-[#222222]" />
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto px-12 pb-10 [scrollbar-width:none]">
                                <h2 class="mt-6 mb-10 text-[3.2rem] leading-[3.6rem] font-semibold text-[#222222] tracking-tight">Menu</h2>

                                <template v-if="user">
                                    <!-- The big card: where hosting starts or lives. -->
                                    <a :href="hosting ? '#' : (user.organizer ? '/hosting/events' : '/hosting/getting-started')" @click="onCard" class="flex items-center gap-6 p-6 mb-6 rounded-[1.6rem] border border-[#222222] bg-white hover:bg-[#f7f7f7]">
                                        <div class="w-16 h-16 flex-shrink-0 rounded-xl overflow-hidden bg-white flex items-center justify-center">
                                            <img v-if="hosting?.thumbImagePath" :src="`${imageUrl}${hosting.thumbImagePath}`" :alt="hosting.name" class="w-full h-full object-cover" />
                                            <img v-else src="/storage/website-files/Everything_Immersive_logo_Short.png" alt="" class="w-12 h-12" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[1.8rem] leading-[2.2rem] font-medium text-[#222222] transform-none truncate">{{ hosting ? 'Create new event' : (user.organizer ? user.organizer.name : 'List your event') }}</p>
                                            <p class="text-[1.4rem] leading-[1.8rem] text-[#6a6a6a] transform-none mt-1">{{ hosting ? `A new listing for ${hosting.name}` : (user.organizer ? 'Create and manage events' : 'Put your experience on Everything Immersive') }}</p>
                                        </div>
                                    </a>

                                    <a v-for="item in menuTop" :key="item.label" :href="item.href" :class="rowClass">
                                        <component :is="item.icon" class="w-8 h-8 text-[#222222] flex-shrink-0" />
                                        {{ item.label }}
                                        <span v-if="item.dot" class="ml-auto rounded-full bg-red-500 w-3 h-3 flex-shrink-0"></span>
                                    </a>

                                    <div class="my-6 border-t border-[#dddddd]"></div>

                                    <a v-for="item in menuBottom" :key="item.label" :href="item.href" :class="rowClass">
                                        <component :is="item.icon" class="w-8 h-8 text-[#222222] flex-shrink-0" />
                                        {{ item.label }}
                                    </a>

                                    <!-- Log out keeps its own section, so overshooting a row never logs you out. -->
                                    <div class="my-6 border-t border-[#dddddd]"></div>
                                    <button type="button" :class="rowClass" @click="logout">
                                        <component :is="RiLogoutBoxRLine" class="w-8 h-8 text-[#222222] flex-shrink-0" />
                                        Log out
                                    </button>
                                </template>
                                <template v-else>
                                    <a href="/register?create=true" class="flex items-center gap-6 p-6 mb-6 rounded-[1.6rem] border border-[#222222] bg-white hover:bg-[#f7f7f7]">
                                        <div class="w-16 h-16 flex-shrink-0 rounded-xl overflow-hidden bg-white flex items-center justify-center">
                                            <img src="/storage/website-files/Everything_Immersive_logo_Short.png" alt="" class="w-12 h-12" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[1.8rem] leading-[2.2rem] font-medium text-[#222222] transform-none">Submit an event</p>
                                            <p class="text-[1.4rem] leading-[1.8rem] text-[#6a6a6a] transform-none mt-1">Put your experience on Everything Immersive</p>
                                        </div>
                                    </a>
                                    <button type="button" :class="rowClass" @click.prevent="onLogin">
                                        <component :is="RiUserLine" class="w-8 h-8 text-[#222222] flex-shrink-0" />
                                        Log in or sign up
                                    </button>
                                </template>
                            </div>
                        </aside>
                    </Transition>
                </Teleport>
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
    RiCloseLine,
    RiAddLine,
    RiMapPinLine,
    RiCalendarEventLine,
} from '@remixicon/vue';

const props = defineProps(['user', 'hosting']);
const emit = defineEmits(['close']);
const imageUrl = import.meta.env.VITE_IMAGE_URL;

const userColor = computed(() => {
    // An organization's first letter sits on black, Airbnb style.
    if (props.user && props.hosting && !props.hosting.thumbImagePath) return '#222222';
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
    closeDropdown();
    showLogin.value = true;
};

const onToggle = () => {
    dropdown.value ? closeDropdown() : openSheet();
};

const closeDropdown = () => {
    dropdown.value = false;
    document.body.style.overflow = '';
};

// The page behind the sheet stays still while it is open; Escape closes it.
const openSheet = () => {
    dropdown.value = true;
    document.body.style.overflow = 'hidden';
};
const onKey = (e) => {
    if (e.key === 'Escape' && dropdown.value) closeDropdown();
};
window.addEventListener('keydown', onKey);
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});

// On the hosting page the card makes a new event: the page itself does it, with its own checks.
const onCard = (e) => {
    if (!props.hosting) return;
    e.preventDefault();
    closeDropdown();
    window.dispatchEvent(new CustomEvent('hosting-create-event'));
};

const rowClass = 'w-full flex items-center gap-6 py-[1.4rem] text-[1.6rem] leading-[2rem] text-[#222222] hover:underline text-left';

const menuTop = computed(() => [
    ...(props.user?.hasMessages ? [{ label: 'Messages', href: '/inbox', icon: RiMessage3Line, dot: props.user.unread }] : []),
    { label: 'Profile', href: `/users/${props.user?.id}`, icon: RiUserLine },
    props.user?.organizer
        ? { label: 'Your organizations', href: '/teams', icon: RiHome4Line }
        : { label: 'List your event', href: '/hosting/getting-started', icon: RiAddLine },
    // Venues: the venues page, where the first one is added too, so anyone
    // hosting (or keeping a venue) can find it before they have one.
    ...(props.user?.venues || props.user?.organizer
        ? [{
            label: props.user.venues === 1 ? 'Your venue' : (props.user.venues ? 'Your venues' : 'Venues'),
            href: '/hosting/venues',
            icon: RiMapPinLine,
        }]
        : []),
    ...(props.user?.organizer ? [{ label: 'Your events', href: '/hosting/events', icon: RiCalendarEventLine }] : []),
]);

const menuBottom = computed(() => [
    { label: 'Account settings', href: '/account-settings', icon: RiSettings3Line },
    { label: 'Communities', href: '/communities', icon: RiTeamLine },
    ...(props.user?.isCurator ? [{ label: 'Admin dashboard', href: '/admin/dashboard', icon: RiShieldCheckLine }] : []),
]);

// Register the directive
const vClickOutside = ClickOutsideDirective;

// Add inheritAttrs option to handle the class warning
defineOptions({
    inheritAttrs: true
})
</script>
