<template>
    <div 
        class="flex gap-8 border w-full bg-white fixed bottom-0 left-0 z-30 flex py-6 px-10 min-h-48 shadow-custom-1"
        :class="{ 'translate-y-full': !showBar }"
        style="transition: transform 0.3s ease-in-out;"
    >
        <div 
            v-if="!ticketsVisible"
            class="inline-block w-1/2 flex flex-col justify-center">
            <h3 
                class="font-bold text-3.5xl underline" 
                @click="toggleTickets"
                style="font-family: 'Montserrat', sans-serif;">{{ event.price_range }}</h3>
        </div>

        <div 
            v-if="ticketsVisible"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black bg-opacity-50">
            <div 
                v-click-outside="handleClickOutside"
                class="w-full bg-white rounded-t-3xl max-h-[80vh] overflow-y-auto">
                <div class="flex justify-end p-6">
                    <button 
                        class="border-none"
                        @click="toggleTickets">
                        <svg class="w-10 h-10">
                            <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                        </svg>
                    </button>
                </div>
                <div class="px-10 pt-6 pb-10 flex items-center justify-between">
                    <h4 class="font-semibold text-4.5xl" style="font-family: 'Montserrat', sans-serif;">Ticket Details</h4>
                </div>
                <div
                    class="py-4 px-10"
                    v-for="ticket in event.first_show_tickets" 
                    :key="ticket.name">
                    <div class="flex justify-between">
                        <div>
                            <h4 class="text-3xl" style="font-family: 'Montserrat', sans-serif;"> {{ ticket.name }} </h4>
                            <p class="text-xl text-gray-500"> {{ ticket.description }} </p>
                        </div>
                        <p class="text-3xl font-semibold flex items-center">{{ formatTicketPrice(ticket) }}</p>
                    </div>
                </div>
                <div class="flex p-10">
                    <a 
                        :href="eventUrl"
                        @click="storeClick"
                        class="w-full"
                        rel="noreferrer noopener" 
                        target="_blank">
                        <button class="font-medium text-3.5xl py-6 px-4 rounded-2xl w-full border-none text-white float-right bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1">
                            <span v-if="remaining && remaining.length">{{ event.call_to_action ? event.call_to_action : 'Get Tickets' }}</span>
                            <span v-else>View Event</span>
                        </button>
                    </a>
                </div>
            </div>
        </div>

        <div class="w-1/2 inline-block flex flex-col justify-center">
            <a 
                :href="eventUrl"
                @click="storeClick"
                rel="noreferrer noopener" 
                target="_blank">
                <button class="font-medium text-3.5xl py-6 px-4 rounded-2xl w-full border-none text-white float-right bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1">
                    <span v-if="remaining && remaining.length">{{ event.call_to_action ? event.call_to_action : 'Get Tickets' }}</span>
                    <span v-else>View Event</span>
                </button>
            </a>
        </div>

        <div v-if="canEdit" class="mt-4 absolute bottom-[-7rem] right-0 z-10">
            <a :href="`/hosting/event/${event.slug}/edit`">
                <button class="font-medium py-4 px-4 rounded-2xl w-full bg-white border border-neutral-300 hover:border-[#222222] hover:shadow-focus-black transition-all duration-200">
                    Edit Event
                </button>
            </a>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import ShowMore from '@/GlobalComponents/show-more.vue';
import dayjs from 'dayjs';

const props = defineProps({
    event: Object,
    user: Object
});

const showBar = ref(false);
const ticketsVisible = ref(false);
const remaining = ref([]);
const isModalReady = ref(false);

// Handle scroll to show/hide the purchase bar
const handleScroll = () => {
    if (window.scrollY > 75) {
        showBar.value = true;
    } else {
        showBar.value = false;
    }
};

// Add and remove scroll event listener
onMounted(() => {
    getDates();
    window.addEventListener('scroll', handleScroll);
    // Initial check in case page loads with scroll position
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const canEdit = computed(() => 
    props.user && (
        props.user.isAdmin || 
        props.user.isModerator || 
        props.user.organizer?.id === props.event.organizer_id
    )
);

const eventUrl = computed(() => {
    if (props.event.ticketUrl) return props.event.ticketUrl;
    if (props.event.websiteUrl) return props.event.websiteUrl;
    return props.event.organizer.website;
});

const toggleTickets = () => {
    ticketsVisible.value = !ticketsVisible.value;
    
    // If opening the modal, set modal ready flag after a short delay
    if (ticketsVisible.value) {
        isModalReady.value = false;
        setTimeout(() => {
            isModalReady.value = true;
        }, 100);
    }
};

const handleClickOutside = () => {
    // Only close if the modal is ready to handle outside clicks
    if (isModalReady.value) {
        ticketsVisible.value = false;
    }
};

const storeClick = () => {
    axios.post(`/api/events/${props.event.id}/track-click`, {
        destination_url: eventUrl.value,
        click_type: 'ticket_button'
    }).catch(error => {
        console.error('Error tracking click:', error);
    });
};

const getDates = () => {
    if (props.event.shows) {
        props.event.shows.forEach(event => {
            const eventDate = new Date(event.date);
            // Normalize date to midnight for comparison
            const normalizedEventDate = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());
            const normalizedToday = new Date();
            normalizedToday.setHours(0, 0, 0, 0);
            normalizedToday.setDate(normalizedToday.getDate() - 1); // yesterday
            
            if (normalizedEventDate >= normalizedToday) {
                remaining.value.push(event.date);
            }
        });
    }
};

const formatTicketPrice = (ticket) => {
    if (ticket.type === 'f') return 'Free';
    if (ticket.type === 'p') return 'Pay what you can';
    return ticket.ticket_price == 0.00 ? 'Free' : `${ticket.currency} ${ticket.ticket_price}`;
};
</script>
