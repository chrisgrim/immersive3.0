<template>
    <div 
        class="fixed bottom-0 left-0 right-0 h-36 bg-white border-t border-neutral-200 z-40"
        :style="{
            transform: `translateY(${isHidden ? '100%' : '0'})`,
            transition: 'transform 0.3s ease-in-out'
        }">
        <div class="h-full flex justify-between items-center px-16">
            <a href="/" class="flex flex-col items-center gap-1" :class="isHome ? 'text-primary font-medium' : 'text-neutral-600'">
                <div class="text-3xl font-bold">EI</div>
                <span class="text-base">Discover</span>
            </a>

            <a :href="eventsLink" 
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
const SCROLL_THRESHOLD = 100; // Amount of scrolling needed before showing/hiding

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
});

onUnmounted(() => {
    window.removeEventListener('scroll', debouncedScroll);
});
</script>