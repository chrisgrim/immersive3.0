<template>
  <div class="event-calendar-mobile py-8 border-t border-b border-neutral-200">
    <h3 class="text-3xl md:text-2xl font-medium text-black mb-8">Show Dates</h3>
    
    <!-- Show "Available Anytime" when showtype is "a" -->
    <div v-if="event.showtype === 'a'" class="text-2xl text-neutral-700 pb-4">
      Available Anytime
    </div>
    
    <!-- Show calendar for other showtypes -->
    <div v-else class="calendar-container">
      <VueDatePicker
        v-model="selectedDate"
        :enable-time-picker="false"
        :highlighted="highlightedDates"
        :min-date="minDate"
        :max-date="maxDate"
        inline
        auto-apply
        multi-calendars-solo>     
      </VueDatePicker>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
});

// Get all event dates from shows
const eventDates = computed(() => {
  if (!props.event.shows || !props.event.shows.length) {
    return [];
  }
  
  return props.event.shows.map(show => {
    const showDate = new Date(show.date);
    // Reset time to compare only dates
    return new Date(showDate.getFullYear(), showDate.getMonth(), showDate.getDate()).getTime();
  }).sort((a, b) => a - b); // Sort dates chronologically
});

// Calculate range for display
const minDate = computed(() => {
  if (eventDates.value.length) {
    // Get first date of the month of the first show
    const firstDate = new Date(eventDates.value[0]);
    return new Date(firstDate.getFullYear(), firstDate.getMonth(), 1);
  }
  return new Date(); // Fallback to today
});

const maxDate = computed(() => {
  if (eventDates.value.length) {
    // Get last date of the month of the last show
    const lastDate = new Date(eventDates.value[eventDates.value.length - 1]);
    return new Date(lastDate.getFullYear(), lastDate.getMonth() + 1, 0);
  }
  return new Date(); // Fallback to today
});

// Use the current month if no event dates are available
const currentDate = new Date();
const selectedDate = ref(eventDates.value.length > 0 ? eventDates.value[0] : currentDate.getTime());

// Helper function to check if a day is an event day
const isEventDay = (timestamp) => {
  if (!timestamp || !eventDates.value.length) return false;
  
  const date = new Date(timestamp);
  const dateWithoutTime = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  
  return eventDates.value.includes(dateWithoutTime);
};

// Set highlighted dates for template
const highlightedDates = computed(() => {
  return eventDates.value.map(timestamp => {
    return {
      timestamp: timestamp,
      type: 'event'
    };
  });
});
</script>

<style>

.dp--arrow-btn-nav {
    display: none !important;
}

/* Remove the tabs at the top */
.dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Calendar styling */
.dp__calendar {
   width: 100% !important;
}

/* Grid layout for calendar to ensure square cells */
.dp__month {
   width: 100% !important;
}

.dp__calendar_row {
   display: grid !important;
   grid-template-columns: repeat(7, 1fr) !important;
   width: 100% !important;
   margin: 0 !important;
   gap: 0 !important;
}

.dp__calendar_header {
   display: none !important;
}

/* Header month/year styling */
.dp__month_year_wrap {
   font-size: 1.7rem !important;
   font-weight: 400 !important;
}

.dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
   aspect-ratio: 1/1 !important;
   display: flex !important;
   align-items: center !important;
   justify-content: center !important;
}

/* Calendar cells */
.dp__cell_inner {
   height: 100% !important;
   width: 100% !important;
   margin: 0 !important;
   padding: 0 !important;
   display: flex !important;
   align-items: center !important;
   justify-content: center !important;
   font-weight: normal !important;
   color: #333 !important;
   aspect-ratio: 1/1 !important;
}
.dp__active_date {
    background: black !important;
    color: white !important;
}
.dp__calendar_item {
    pointer-events: none !important;
    cursor: default !important;
}

.dp__cell_disabled {
   opacity: 0.3 !important;
   cursor: auto !important;
}

/* Hover state */
.dp__cell_inner:not(.dp--past):hover {
   border: 2px solid black !important;
   background: transparent !important;
   color: black !important;
}

/* Selected state */
.dp__active {
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
   display: none !important;
}

/* Today's date */
.dp__today {
   border: none !important;
}

/* Calendar container */
.dp__main {
   border: none !important;
   box-shadow: none !important;
}

/* Remove borders */
.dp__calendar_header_separator {
    display: none !important;
}

.dp__theme_light {
   border: none !important;
}

.dp--header-wrap {
   margin-bottom: 1rem !important;
   pointer-events: none !important;
    cursor: default !important;
}

/* Fix dual calendar layout */
.dp__menu_inner.dp__flex_display {
    flex-direction: column !important;
    gap: 1rem !important;
    max-width: 100% !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.dp__calendar_next {
    margin-top: 1rem !important;
    margin-inline-start: 0 !important;
    margin-bottom: 0 !important;
}

/* Custom container class to enforce the square aspect */
.calendar-container {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 1rem !important;
}

/* Event calendar container */
.event-calendar-mobile {
    width: 100% !important;
}

/* Optional: Add smooth transition */
.dp__calendar {
    transition: all 0.3s ease !important;
}

/* Fix the bottom padding */
.dp__instance_calendar {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

.dp__menu {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

/* Remove extra padding in menu content */
.dp__menu_content {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

/* Fix header item alignment */
.dp__calendar_header_item {
    width: 100% !important;
    text-align: center !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

</style>
