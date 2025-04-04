<template>
    <div :class="[
        'inline-block border w-full bg-white',
        singleImage 
            ? 'md:sticky md:top-40 md:rounded-2xl md:shadow-custom-1 md:flex-col py-6 px-8' 
            : 'fixed bottom-0 left-0 z-30 flex py-6 px-8 md:sticky md:top-36 md:pb-12 md:mt-16 md:rounded-2xl md:flex-col md:shadow-custom-1 md:mb-16'
    ]">
        <div 
            v-if="!ticketsVisible"
            class="inline-block w-8/12 md:w-full">
            <h3>{{ event.price_range }}</h3>
            <p 
                class="underline cursor-pointer text-xl text-gray-500"
                @click="ticketsVisible =! ticketsVisible">
                Show ticket details
            </p>
        </div>

        <div 
            v-if="ticketsVisible"
            class="w-full absolute inline-block bg-white bottom-0 left-0 border-t md:relative md:rounded-2xl md:border">
            <div class="p-4 border-b flex items-center justify-between">
                <button 
                    class="border-none"
                    @click="ticketsVisible =! ticketsVisible">
                    <svg class="w-10 h-10">
                        <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                    </svg>
                </button>
                <h4 class="font-semibold">Ticket Details</h4>
            </div>
            <div
                class="py-8 px-4 border-b last:border-none"
                v-for="ticket in event.first_show_tickets" 
                :key="ticket.name">
                <div class="flex justify-between">
                    <h4 class="text-2xl"> {{ ticket.name }} </h4>
                    <p 
                        v-if="ticket.type == 'f'"
                        class="text-2xl">Free</p>
                    <p 
                        v-else-if="ticket.type == 'p'"
                        class="text-2xl">Pay what you can</p>
                    <p 
                        v-else
                        class="text-2xl"> {{ ticket.ticket_price == 0.00 ? 'Free' : `${ticket.currency} ${ticket.ticket_price}` }} </p>
                </div>
                <div>
                    <p class="text-xl text-gray-500"> {{ ticket.description }} </p>
                </div>
            </div>
        </div>
        <div 
            :class="{visible: datesVisible}"
            class="es__tickets--background" />
        <template v-if="!mobile">
            <div 
                v-click-outside="hide"
                class="w-full mt-12 relative">
                <template v-if="event.showtype == 's' || event.showtype == 'l' || event.showtype == 'o'">
                    <button 
                        @click="showDates"
                        class="mb-4 z-50 flex flex-col relative w-full rounded-2xl p-4 border text-left bg-white hover:bg-black hover:text-white">
                        <span class="text-2xl font-medium">{{ showDateRange }}</span>
                        <span 
                            v-if="remaining && remaining.length > 1 ? remaining.length : ''" 
                            class="text-xl">{{ remaining.length }} show dates remaining</span>
                        <span 
                            v-else-if="remaining && remaining.length == 1 ? remaining.length : ''" 
                            class="text-xl">{{ remaining.length }} date remaining</span>
                        <span 
                            v-else 
                            class="text-xl">no dates remaining</span>
                        <svg class="w-12 h-12 right-8 top-8 absolute">
                            <use v-if="!datesVisible" :xlink:href="`/storage/website-files/icons.svg#ri-arrow-down-s-line`" />
                            <use v-else :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                        </svg>
                    </button>
                    <template v-if="datesVisible">
                        <div class="absolute border shadow-custom-1 bg-white px-8 pt-36 pb-8 max-h-[calc(100vh-20rem)] overflow-y-scroll overflow-x-hidden rounded-2xl top-[-2rem] right-[-2rem] graydates shadow-hidden lockedcalendar z-20">
                            <VueDatePicker
                                v-model="selectedDates"
                                :model-value="highlightedDates"
                                :enable-time-picker="false"
                                :disable-month-year-select="false"
                                :prevent-min-max-navigation="false"
                                :enable-button-validator="() => false"
                                :highlight-disabled-days="true"
                                :hide-navigation-buttons="false"
                                :persistent-mobile="true"
                                :updateOnInput="false"
                                :show-month-year-separator="false"
                                :text-input="false"
                                :max-date="maxDate"
                                multi-dates
                                :six-weeks="true"
                                multi-calendars="2"
                                multi-calendars-solo
                                inline
                                auto-apply
                                month-name-format="long"
                                hide-offset-dates
                                :month-change-on-scroll="false"
                                week-start="0"
                                :dark="isDark"
                                ref="datePickerRef"
                                @update:model-value="preventDefault"
                            />
                            <div class="overflow-hidden mt-8">
                                <ShowMore
                                    :text="event.show_times"
                                    :limit="20" />
                            </div>
                        </div>
                    </template>
                </template>

                <template v-if="event.showtype == 'a'">
                    <div class="flex flex-col relative w-full rounded-2xl p-4 border text-left">
                        <h3 class="text-2xl">Available Anytime</h3>
                        <div class="overflow-hidden">
                            <ShowMore 
                                :body-class="`text-xl text-gray-500`"
                                :text="event.show_times"
                                :limit="20" />
                        </div>
                    </div>
                </template>
            </div>
        </template>
        

        <div class="w-5/12 inline-block align-bottom md:mt-12 md:w-full">
            <a 
                :href="eventUrl"
                @click="storeClick"
                rel="noreferrer noopener" 
                target="_blank">
                <button class="font-medium py-6 px-4 rounded-2xl w-full border-none text-white float-right bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 md:px-20 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1">
                    <span v-if="remaining && remaining.length">{{ event.call_to_action ? event.call_to_action : 'Get Tickets' }}</span>
                    <span v-else>View Event</span>
                </button>
            </a>
        </div>

        <div v-if="canEdit" class="mt-4 md:mt-8 absolute bottom-[-7rem] right-0 z-10">
            <a :href="`/hosting/event/${event.slug}/edit`">
                <button class="font-medium py-4 px-4 rounded-2xl w-full bg-white border border-neutral-300 hover:border-[#222222] hover:shadow-focus-black transition-all duration-200">
                    Edit Event
                </button>
            </a>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick, inject } from 'vue';
import axios from 'axios';
import ShowMore from '@/GlobalComponents/show-more.vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import dayjs from 'dayjs';

const props = defineProps({
    event: Object,
    singleImage: Boolean,
    user: Object
});

// Safely check for mobile status
const mobile = computed(() => {
    return window?.Laravel?.isMobile ?? false;
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

const showDateRange = computed(() => {
    if (props.event.shows.length > 1) {
        return `${cleanDate(props.event.shows[props.event.shows.length - 1].date)} - ${cleanDate(props.event.shows[0].date)}`;
    }
    return cleanDate(props.event.shows[0].date);
});

const hover = ref(null);
const visible = ref(false);
const dates = ref([]);
const selectedDates = ref(null); // This is just for the v-model of VueDatePicker
const highlightedDates = ref([]);
const week = ref(props.event ? props.event.show_on_going : '');
const remaining = ref([]);
const ticketsVisible = ref(false);
const datesVisible = ref(false);
const datePickerRef = ref(null);
const isDark = ref(false);
const maxDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() + 1)));

const showDates = () => {
    ticketsVisible.value = false;
    datesVisible.value = !datesVisible.value;
};

const storeClick = () => {
    axios.post('/track/event/click', { event: props.event.id });
};

const hide = () => {
    datesVisible.value = false;
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
                
                // Format date for highlighting in calendar
                highlightedDates.value.push(normalizedEventDate);
            }
            
            // Keep all dates for reference
            dates.value.push(event.date);
        });
    }
};

const cleanDate = (data) => dayjs(data).format("MMM D, YYYY");

const preventDefault = (val) => {
    // Reset to original highlighted dates to prevent selection
    selectedDates.value = null;
    return false;
};

watch(datesVisible, (newVal) => {
    if (newVal) {
        nextTick(() => {
            // Focus on current date when opened
            if (datePickerRef.value) {
                const now = new Date();
                datePickerRef.value.selectYear(now.getFullYear());
                datePickerRef.value.selectMonth(now.getMonth());
            }
        });
    }
});

onMounted(getDates);
</script>

<style>
/* Calendar styling from dates.vue */
.dp__calendar {
   width: 100% !important;
}

/* Header month/year styling */
.dp__month_year_wrap {
   font-size: 1.7rem;
   font-weight: 400;
   display: flex;
   justify-content: flex-start;
   align-items: center;
   padding:1rem;
}

.dp--past {
    pointer-events: none !important;
    opacity: 0.3 !important;
}

.dp--past .dp__cell_inner {
    color: #999 !important;
    background: transparent !important;
}

.dp--past.dp__active_date,
.dp--past .dp__active {
    background-color: #999 !important;
    color: #fff !important;
}

/* Ensure past dates can't be interacted with */
.dp--past * {
    pointer-events: none !important;
    cursor: default !important;
}

.dp__month_year_select {
    pointer-events: none !important;
    display: flex;
    justify-content: flex-start;
    margin-left: 1rem;
}

/* Calendar header (days of week) */
.dp__calendar_header {
   color: #666;
   font-weight: normal;
   font-size: 1.2rem;
   border-bottom: 1px solid #e5e5e5
}

.dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

/* Calendar item with border solution and square aspect ratio */
.dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem;
   border-right: 1px solid #e5e5e5;
   border-bottom: 1px solid #e5e5e5;
   display: flex;
   justify-content: center;
   position: relative;
   width: calc(100% / 7) !important;
}

/* Remove redundant borders */
.dp__calendar_item:first-child {
   border-left: none;
}

.dp__calendar_row:first-child .dp__calendar_item {
   border-top: none;
}

/* Create square aspect ratio */
.dp__calendar_item::before {
   content: '';
   display: block;
   padding-top: 100%; /* Creates 1:1 aspect ratio */
}

/* Position the content absolutely within the square */
.dp__calendar_item > * {
   position: absolute;
   top: 0;
   left: 0;
   right: 0;
   bottom: 0;
   display: flex;
   align-items: center;
   justify-content: center;
}

/* Adjust cell inner to fit square */
.dp__cell_inner {
   position: absolute;
   height: 100%;
   width: 100%;
   margin: auto !important;
   padding: 0 !important;
   display: flex;
   align-items: center;
   justify-content: center;
   font-weight: normal;
   color: #333;
}

.dp__cell_disabled {
   opacity: 0.3;
   cursor: auto !important;
}

/* Selected state */
.dp__active {
   background-color: black !important;
   color: white !important;
}

.dp__active_date {
   background-color: black !important;
   color: white !important;
}

/* Range styling */
.dp__range_start,
.dp__range_end {
   background-color: black !important;
   color: white !important;
}

.dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}

.dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}

.dp__range_between {
   border-radius: 0 !important;
}

/* Navigation arrows */
.dp__arrow_bottom,
.dp__arrow_top {
   display: none;
}

/* Today's date */
.dp__today {
   border: none !important;
}

/* Calendar container */
.dp__main {
   border: none;
   box-shadow: none;
}

/* Remove borders */
.dp__calendar_header_separator {
   display: none;
}

.dp__theme_light {
   border: none !important;
}

.dp--header-wrap {
   margin-bottom: 1rem;
}

.dp__flex_display {
   display: block !important;
}

.dp__menu_inner.dp__flex_display {
   gap: 4rem;
}
.dp__menu_inner {
    padding: 0 !important;
}

/* Calendar layout */
.dp__menu_inner.dp__flex_display {
    flex-direction: column !important;
    gap: 2rem !important;
}

.dp__calendar_next {
    margin:0 !important;
}

/* Override calendar width and height for show-purchase */
.lockedcalendar .dp__instance_calendar {
    width: 100% !important;
}

/* Ensure the date picker in show-purchase is read-only */
.lockedcalendar .dp__pointer {
    pointer-events: none !important;
    cursor: default !important;
}

/* Remove tabs from top */
.dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Remove arrow buttons for navigation - CHANGED TO DISPLAY THEM */
.lockedcalendar .dp--arrow-btn-nav {
    display: flex !important;
}

/* Style navigation arrows */
.lockedcalendar .dp__arrow_btn {
    cursor: pointer !important;
    pointer-events: auto !important;
}

/* Force dates to be non-clickable - update to target both elements */
.lockedcalendar .dp__calendar_item,
.lockedcalendar .dp__cell_inner {
    pointer-events: none !important;
}

/* But allow navigation buttons to be clickable - more specific selector to override */
.lockedcalendar .dp__month_arrow_btn,
.lockedcalendar .dp__arrow_btn,
.lockedcalendar .dp__arrow_btn_container,
.lockedcalendar .dp__inner_nav {
    pointer-events: auto !important;
}

/* Make sure highlighted dates stay highlighted */
.lockedcalendar .dp__active_date,
.lockedcalendar .dp__date_cell_highlight div,
.lockedcalendar .dp__range_start,
.lockedcalendar .dp__range_end {
    background-color: black !important;
    color: white !important;
}

/* Prevent any date selection */
.lockedcalendar .dp__cell_offset,
.lockedcalendar .dp__cell_disabled {
    pointer-events: none !important;
}

/* Desktop grid layout for calendars */
@media (min-width: 768px) {
    .lockedcalendar .dp__menu_inner {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 20px !important;
        width: 100% !important;
        padding: 1rem !important;
    }
    
    .lockedcalendar .dp__calendar {
        width: 100% !important;
        min-width: 0 !important;
    }
}

/* Highlight dates selected for the show */
.lockedcalendar .dp__date_cell.dp__date_cell_highlight div {
    background-color: black !important;
    color: white !important;
}

/* Disable button appearance for all dates */
.lockedcalendar .dp__cell_inner {
    cursor: default !important;
}

/* Style navigation arrows - make them more visible */
.lockedcalendar .dp__month_year_row {
    position: relative;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}

.lockedcalendar .dp__arrow_btn {
    display: flex !important;
    cursor: pointer !important;
    pointer-events: auto !important;
    position: absolute !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    background-color: #f5f5f5 !important;
    border-radius: 50% !important;
    width: 2rem !important;
    height: 2rem !important;
    justify-content: center !important;
    align-items: center !important;
    z-index: 10 !important;
}

.lockedcalendar .dp__arrow_btn:hover {
    background-color: #e5e5e5 !important;
}

.lockedcalendar .dp__arrow_btn:first-child {
    left: 0.5rem !important;
}

.lockedcalendar .dp__arrow_btn:last-child {
    right: 0.5rem !important;
}

.lockedcalendar .dp__month_year_select {
    margin: 0 3rem !important; /* Make room for arrows */
}

/* Override any rules that might hide arrows */
.lockedcalendar .dp__inner_nav {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.lockedcalendar .dp--arrow-btn-nav,
.lockedcalendar .dp__arrow_btn_container {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Fix greyed out navigation arrows in readonly mode */
.lockedcalendar .dp__menu_readonly .dp__inner_nav,
.lockedcalendar .dp__menu_readonly .dp__arrow_btn {
    opacity: 1 !important;
    pointer-events: auto !important;
}

.lockedcalendar .dp__menu_readonly .dp__arrow_btn svg {
    fill: #333 !important; /* Normal color instead of disabled gray */
}

.lockedcalendar .dp__menu_readonly .dp__arrow_btn:hover {
    background-color: #e5e5e5 !important;
}

.lockedcalendar .dp__arrow_btn svg {
    width: 1rem !important;
    height: 1rem !important;
}
</style>
