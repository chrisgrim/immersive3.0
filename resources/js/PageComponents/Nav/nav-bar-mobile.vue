<template>
    <div
        class="fixed bottom-0 left-0 right-0 h-36 bg-white border-t border-neutral-200 z-[400]"
        :style="{
            transform: `translateY(${isHidden || isSearchOpen || isDetailOpen ? '100%' : '0'})`,
            transition: 'transform 0.3s ease-in-out'
        }">
        <div class="h-full flex justify-between items-center px-16">
            <a href="/" class="flex flex-col items-center gap-1" :class="isHome ? 'text-primary font-medium' : 'text-neutral-600'">
                <img src="/storage/website-files/Everything_Immersive_logo_Short.png" alt="EI" class="w-10 h-10" />
                <span class="text-base">Discover</span>
            </a>

            <a href="/hosting/events" 
               class="flex flex-col items-center gap-1" 
               :class="isEvents ? 'text-primary font-medium' : 'text-neutral-600'">
                <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 4H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2zM16 2v4M8 2v4M3 10h18"/>
                </svg>
                <span class="text-base">Events</span>
            </a>

            <a href="/inbox" 
               class="flex flex-col items-center gap-1" 
               :class="isInbox ? 'text-primary font-medium' : 'text-neutral-600'">
                <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
                <span class="text-base">Inbox</span>
            </a>

            <a href="/menu" 
               class="flex flex-col items-center gap-1" 
               :class="isMenu ? 'text-primary font-medium' : 'text-neutral-600'">
                <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="text-base">Menu</span>
            </a>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true
    }
});

const isHidden = ref(false);
// nav-search-mobile.vue is a separate root component — see its own comment
// on the matching dispatch for why a window event is what ties the two
// together, instead of a prop. Initialized from window.location, NOT just
// false-until-the-event-says-otherwise: nav-search-mobile.vue and this
// component are two independently-resolving async components
// (defineAsyncComponent), and every page transition in this app is a full
// reload (window.location.href, not client-side routing — see
// nav-search-mobile.vue's handleSearch/handleBack), so this only needs to
// be right ONCE per mount, synchronously. Relying solely on the event for
// the initial value was a real bug (caught in review): with both chunks
// already warm in the browser cache (the common case for a returning
// visitor, not just a cold first load), nav-search-mobile.vue's immediate
// watcher can dispatch before this component's onMounted has even attached
// the listener below, silently losing the very first "hide" signal and
// leaving the bar wrongly visible over a results/map/At-Home page's own
// back arrow for the whole visit. The listener below still handles every
// LATER change correctly (e.g. opening the search overlay while already on
// the home page) — this only fixes the unreliable initial value.
//
// That pathname guess assumed every non-home page has nav-search-mobile.vue
// mounted to correct it via the event below — true for the search/home
// pages it was written for, but nav-search-mobile.vue only actually renders
// on pages that @include nav.index-mobile (see that partial). Pages that
// mount this bar WITHOUT it — Profile, Menu, Account Settings, etc. — have
// nothing to ever dispatch the correction, so the guess of "open" stuck
// forever and permanently hid the bar there (caught from a live report:
// the bottom nav was silently missing on /users/{id} and /menu). Checking
// for that element's presence in the server-rendered HTML (synchronous,
// same timing guarantee as the pathname check) keeps the original fix for
// search/home intact while no longer mis-firing on every other page.
const hasSearchMobile = !!document.querySelector('vue-nav-search-mobile');
const isSearchOpen = ref(hasSearchMobile && window.location.pathname !== '/');
const handleSearchToggle = (event) => {
    isSearchOpen.value = !!event.detail?.open;
};
// Same idea, from Profile/index.vue — a drilled-into saved search's editor
// or a liked event's detail already has its own back button up top, so the
// bottom bar is redundant chrome on that sub-screen, not a real nav choice.
// Initial value also reads window.__hideBottomNavBar, a synchronous flag a
// plain Blade page can set inline (see curated/posts/show.blade.php) for a
// page that's ALWAYS in this state for its whole lifetime, with no runtime
// toggling — same event-timing risk as isSearchOpen's own fix above would
// otherwise apply here too, so a static page uses a static inline-script
// flag (guaranteed to run before this async component even starts
// resolving) instead of a dispatched event.
const isDetailOpen = ref(!!window.__hideBottomNavBar);
const handleDetailToggle = (event) => {
    isDetailOpen.value = !!event.detail?.open;
};
const isHome = ref(window.location.pathname === '/');
const isEvents = ref(window.location.pathname === '/hosting/events' || window.location.pathname === '/hosting/getting-started');
const isInbox = ref(window.location.pathname === '/inbox');
const isMenu = ref(window.location.pathname === '/menu');

const eventsLink = computed(() => {
    // Check if user has any events or organizers
    const hasEvents = props.user.events?.length > 0;
    const hasOrganizers = props.user.organizers?.length > 0;
    
    return hasEvents || hasOrganizers ? '/hosting/events' : '/hosting/getting-started';
});

let lastScrollY = window.scrollY;
let scrollUpAmount = 0;
let scrollDownAmount = 0;
const SCROLL_THRESHOLD = window.innerHeight; // Amount of scrolling needed before showing/hiding (full screen height)

const handleScroll = () => {
    const currentScrollY = window.scrollY;
    const isScrollingDown = currentScrollY > lastScrollY;
    const scrollDifference = Math.abs(currentScrollY - lastScrollY);
    
    if (isScrollingDown) {
        // Reset the up counter when direction changes
        scrollUpAmount = 0;
        scrollDownAmount += scrollDifference;
        
        // Hide the navbar when scrolled down past threshold
        if (scrollDownAmount > SCROLL_THRESHOLD) {
            isHidden.value = true;
            scrollDownAmount = 0; // Reset after action
        }
    } else {
        // Reset the down counter when direction changes
        scrollDownAmount = 0;
        scrollUpAmount += scrollDifference;
        
        // Show the navbar only after scrolling up past threshold
        if (scrollUpAmount > SCROLL_THRESHOLD) {
            isHidden.value = false;
            scrollUpAmount = 0; // Reset after action
        }
    }
    
    lastScrollY = currentScrollY;
};

let scrollTimeout;
const debouncedScroll = () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(handleScroll, 10);
};

onMounted(() => {
    window.addEventListener('scroll', debouncedScroll);
    window.addEventListener('mobile-search-toggle', handleSearchToggle);
    window.addEventListener('profile-detail-toggle', handleDetailToggle);
});

onUnmounted(() => {
    window.removeEventListener('scroll', debouncedScroll);
    window.removeEventListener('mobile-search-toggle', handleSearchToggle);
    window.removeEventListener('profile-detail-toggle', handleDetailToggle);
});
</script>