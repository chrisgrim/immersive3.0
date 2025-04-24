<template>
    <div class="md:sticky md:top-40">
        <div class="inline-block border w-full bg-white md:rounded-2xl md:shadow-custom-1 md:flex-col py-6 px-8">
            <div 
                v-if="!ticketsVisible"
                class="inline-block w-full">
                <button 
                    @click="ticketsVisible =! ticketsVisible"
                    class="text-3.5xl font-medium underline">{{ event.price_range }}</button>
            </div>

            <div 
                v-if="ticketsVisible"
                class="w-full relative inline-block bg-white rounded-2xl border">
                <div class="p-4 border-b flex items-center justify-between">
                    <button 
                        class="border-none"
                        @click="ticketsVisible =! ticketsVisible">
                        <svg class="w-10 h-10">
                            <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                        </svg>
                    </button>
                    <h4 style="font-family: 'Montserrat', sans-serif;" class="font-semibold flex items-center">Ticket Details</h4>
                </div>
                <div
                    class="py-8 px-4 border-b last:border-none"
                    v-for="ticket in event.first_show_tickets" 
                    :key="ticket.name">
                    <div class="flex justify-between">
                        <p class="text-2xl"> {{ ticket.name }} </p>
                        <p class="text-2xl">{{ formatTicketPrice(ticket) }}</p>
                    </div>
                    <div>
                        <p class="text-xl text-gray-500"> {{ ticket.description }} </p>
                    </div>
                </div>
            </div>
            <div 
                :class="{visible: datesVisible}"
                class="es__tickets--background" />
            
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
                        <div class="absolute border shadow-custom-1 bg-white px-8 pt-36 pb-8 max-h-[calc(100vh-20rem)] overflow-y-scroll overflow-x-hidden rounded-2xl top-[-2rem] right-[-2rem] graydates shadow-hidden lockedcalendar show-purchase-calendar z-20">
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
                                :open-date="openDateValue"
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
            

            <div class="w-full mt-12">
                <a 
                    :href="eventUrl"
                    @click="storeClick"
                    rel="noreferrer noopener" 
                    target="_blank">
                    <button class="font-medium py-6 px-20 rounded-2xl w-full border-none text-white bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1">
                        <span v-if="remaining && remaining.length">{{ event.call_to_action ? event.call_to_action : 'Get Tickets' }}</span>
                        <span v-else>View Event</span>
                    </button>
                </a>
            </div>

            <div v-if="canEdit" class="mt-8 z-10">
                <a :href="`/hosting/event/${event.slug}/edit`">
                    <button class="font-medium py-4 px-4 rounded-2xl w-full bg-white border border-neutral-300 hover:border-[#222222] hover:shadow-focus-black transition-all duration-200">
                        Edit Event
                    </button>
                </a>
            </div>
        </div>
        <!-- <vue-similar-events :event="event"></vue-similar-events> -->
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
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

const formatTicketPrice = (ticket) => {
    // Check if ticket name is PWYC (case insensitive)
    if (ticket.name && ticket.name.toUpperCase().trim() === 'PWYC') {
        return 'Pay what you can';
    }
    if (ticket.type === 'f') return 'Free';
    if (ticket.type === 'p') return 'Pay what you can';
    return ticket.ticket_price == 0.00 ? 'Free' : `${ticket.currency} ${ticket.ticket_price}`;
};

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
const openDateValue = ref(new Date());

const showDates = () => {
    ticketsVisible.value = false;
    datesVisible.value = !datesVisible.value;
    
    // Update the open date when showing the calendar
    if (datesVisible.value) {
        openDateValue.value = new Date();
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

onMounted(getDates);
</script>

<style>
/* Calendar styling for show-purchase component only */
.show-purchase-calendar .dp__calendar,
.lockedcalendar .dp__calendar {
   width: 100% !important;
}

/* Header month/year styling */
.show-purchase-calendar .dp__month_year_wrap,
.lockedcalendar .dp__month_year_wrap {
   font-size: 1.7rem;
   font-weight: 400;
   display: flex;
   justify-content: flex-start;
   align-items: center;
   padding:1rem;
}

.show-purchase-calendar .dp--past,
.lockedcalendar .dp--past {
    pointer-events: none !important;
    opacity: 0.3 !important;
}

.show-purchase-calendar .dp--past .dp__cell_inner,
.lockedcalendar .dp--past .dp__cell_inner {
    color: #999 !important;
    background: transparent !important;
}

.show-purchase-calendar .dp--past.dp__active_date,
.show-purchase-calendar .dp--past .dp__active,
.lockedcalendar .dp--past.dp__active_date,
.lockedcalendar .dp--past .dp__active {
    background-color: #999 !important;
    color: #fff !important;
}

/* Ensure past dates can't be interacted with */
.show-purchase-calendar .dp--past *,
.lockedcalendar .dp--past * {
    pointer-events: none !important;
    cursor: default !important;
}

.show-purchase-calendar .dp__month_year_select,
.lockedcalendar .dp__month_year_select {
    pointer-events: none !important;
    display: flex;
    justify-content: flex-start;
    margin-left: 1rem;
}

/* Calendar header (days of week) */
.show-purchase-calendar .dp__calendar_header,
.lockedcalendar .dp__calendar_header {
   color: #666;
   font-weight: normal;
   font-size: 1.2rem;
   border-bottom: 1px solid #e5e5e5
}

.show-purchase-calendar .dp__calendar_row,
.lockedcalendar .dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

/* Calendar item with border solution and square aspect ratio */
.show-purchase-calendar .dp__calendar_item,
.lockedcalendar .dp__calendar_item {
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
.show-purchase-calendar .dp__calendar_item:first-child,
.lockedcalendar .dp__calendar_item:first-child {
   border-left: none;
}

.show-purchase-calendar .dp__calendar_row:first-child .dp__calendar_item,
.lockedcalendar .dp__calendar_row:first-child .dp__calendar_item {
   border-top: none;
}

/* Create square aspect ratio */
.show-purchase-calendar .dp__calendar_item::before,
.lockedcalendar .dp__calendar_item::before {
   content: '';
   display: block;
   padding-top: 100%; /* Creates 1:1 aspect ratio */
}

/* Position the content absolutely within the square */
.show-purchase-calendar .dp__calendar_item > *,
.lockedcalendar .dp__calendar_item > * {
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
.show-purchase-calendar .dp__cell_inner,
.lockedcalendar .dp__cell_inner {
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

.show-purchase-calendar .dp__cell_disabled,
.lockedcalendar .dp__cell_disabled {
   opacity: 0.3;
   cursor: auto !important;
}

/* Selected state */
.show-purchase-calendar .dp__active,
.lockedcalendar .dp__active {
   background-color: black !important;
   color: white !important;
}

.show-purchase-calendar .dp__active_date,
.lockedcalendar .dp__active_date {
   background-color: black !important;
   color: white !important;
}

/* Range styling */
.show-purchase-calendar .dp__range_start,
.show-purchase-calendar .dp__range_end,
.lockedcalendar .dp__range_start,
.lockedcalendar .dp__range_end {
   background-color: black !important;
   color: white !important;
}

.show-purchase-calendar .dp__range_start,
.lockedcalendar .dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}

.show-purchase-calendar .dp__range_end,
.lockedcalendar .dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}

.show-purchase-calendar .dp__range_between,
.lockedcalendar .dp__range_between {
   border-radius: 0 !important;
}

/* Navigation arrows */
.show-purchase-calendar .dp__arrow_bottom,
.show-purchase-calendar .dp__arrow_top,
.lockedcalendar .dp__arrow_bottom,
.lockedcalendar .dp__arrow_top {
   display: none;
}

/* Today's date */
.show-purchase-calendar .dp__today,
.lockedcalendar .dp__today {
   border: none !important;
}

/* Calendar container */
.show-purchase-calendar .dp__main,
.lockedcalendar .dp__main {
   border: none;
   box-shadow: none;
}

/* Remove borders */
.show-purchase-calendar .dp__calendar_header_separator,
.lockedcalendar .dp__calendar_header_separator {
   display: none;
}

.show-purchase-calendar .dp__theme_light,
.lockedcalendar .dp__theme_light {
   border: none !important;
}

.show-purchase-calendar .dp--header-wrap,
.lockedcalendar .dp--header-wrap {
   margin-bottom: 1rem;
}

.show-purchase-calendar .dp__flex_display,
.lockedcalendar .dp__flex_display {
   display: block !important;
}

.show-purchase-calendar .dp__menu_inner.dp__flex_display,
.lockedcalendar .dp__menu_inner.dp__flex_display {
   gap: 4rem;
}

.show-purchase-calendar .dp__menu_inner,
.lockedcalendar .dp__menu_inner {
    padding: 0 !important;
}

/* Calendar layout */
.show-purchase-calendar .dp__menu_inner.dp__flex_display,
.lockedcalendar .dp__menu_inner.dp__flex_display {
    flex-direction: column !important;
    gap: 2rem !important;
}

.show-purchase-calendar .dp__calendar_next,
.lockedcalendar .dp__calendar_next {
    margin:0 !important;
}

/* Remove tabs from top */
.show-purchase-calendar .dp__menu_inner .dp__menu_items,
.lockedcalendar .dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Keep lockedcalendar specific styles as they were before */
.lockedcalendar .dp__instance_calendar {
    width: 100% !important;
}

.lockedcalendar .dp__pointer {
    pointer-events: none !important;
    cursor: default !important;
}

.lockedcalendar .dp--arrow-btn-nav {
    display: flex !important;
}

.lockedcalendar .dp__arrow_btn {
    cursor: pointer !important;
    pointer-events: auto !important;
}

.lockedcalendar .dp__calendar_item,
.lockedcalendar .dp__cell_inner {
    pointer-events: none !important;
}

.lockedcalendar .dp__month_arrow_btn,
.lockedcalendar .dp__arrow_btn,
.lockedcalendar .dp__arrow_btn_container,
.lockedcalendar .dp__inner_nav {
    pointer-events: auto !important;
}

.lockedcalendar .dp__active_date,
.lockedcalendar .dp__date_cell_highlight div,
.lockedcalendar .dp__range_start,
.lockedcalendar .dp__range_end {
    background-color: black !important;
    color: white !important;
}

.lockedcalendar .dp__cell_offset,
.lockedcalendar .dp__cell_disabled {
    pointer-events: none !important;
}

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

.lockedcalendar .dp__date_cell.dp__date_cell_highlight div {
    background-color: black !important;
    color: white !important;
}

.lockedcalendar .dp__cell_inner {
    cursor: default !important;
}

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