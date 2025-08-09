<template>
  <div class="event-calendar-mobile py-8 border-t border-b border-neutral-200">
    <p class="text-5xl font-medium text-black mb-8">
      Show Dates
      <span v-if="remaining.length > 0 && event.showtype !== 'a'" class="text-3xl font-normal text-neutral-600 ml-2">
        ({{ remaining.length }} {{ remaining.length === 1 ? 'date' : 'dates' }} remaining)
      </span>
    </p>
    
    <!-- Show "Available Anytime" when showtype is "a" -->
    <div v-if="event.showtype === 'a'" class="text-2xl text-neutral-700 pb-4">
      Available Anytime
    </div>
    
    <!-- Show calendar for other showtypes -->
    <div v-else class="calendar-container">
      <VueDatePicker
        v-model="selectedDates"
        :preview-date="previewDate"
        :enable-time-picker="false"
        :disable-month-year-select="false"
        :prevent-min-max-navigation="false"
        :enable-button-validator="() => false"
        :highlight-disabled-days="true"
        :hide-navigation-buttons="false"
        :month-change-on-scroll="false"
        :month-change-on-arrows="true"
        :show-month-year-separator="false"
        :max-date="maxDate"
        multi-dates
        :six-weeks="true"
        inline
        auto-apply
        month-name-format="short"
        hide-offset-dates
        week-start="0"
        @update:model-value="preventDefault"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
});

const selectedDates = ref([]);  // Start empty like dates.vue
const highlightedDates = ref([]);
const dates = ref([]);
const remaining = ref([]);
const maxDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() + 1)));
const previewDate = ref(new Date());

const getDates = () => {
  if (props.event.shows) {
    props.event.shows.forEach(event => {
      const eventDate = new Date(event.date);
      // Normalize date to midnight for comparison
      const normalizedEventDate = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());
      const normalizedToday = new Date();
      normalizedToday.setHours(0, 0, 0, 0);
      // Use today as baseline, not yesterday
      
      if (normalizedEventDate >= normalizedToday) {
        remaining.value.push(event.date);
      }
      
      // Add all dates to highlightedDates, regardless of whether they're past or future
      highlightedDates.value.push(normalizedEventDate);
      
      // Keep all dates for reference
      dates.value.push(event.date);
    });
    
    // Set selectedDates AFTER calendar is initialized
    nextTick(() => {
      selectedDates.value = [...highlightedDates.value];
    });
  }
};

const preventDefault = (val) => {
  // Reset to original highlighted dates to prevent selection
  selectedDates.value = null;
  return false;
};

onMounted(getDates);
</script>

<style>
.event-calendar-mobile .dp__calendar {
   width: 100% !important;
   cursor: default !important;
   pointer-events: none !important;
}

/* Header month/year styling */
.event-calendar-mobile .dp__month_year_wrap {
   font-size: 1.7rem !important;
   font-weight: 400 !important;
   display: flex !important;
   justify-content: flex-start !important;
   align-items: center !important;
   padding: 1rem !important;
}

.event-calendar-mobile .dp--past {
    pointer-events: none !important;
    opacity: 0.3 !important;
}

.event-calendar-mobile .dp--past .dp__cell_inner {
    color: #999 !important;
    background: transparent !important;
}

.event-calendar-mobile .dp--past.dp__active_date,
.event-calendar-mobile .dp--past .dp__active {
    background-color: #999 !important;
    color: #fff !important;
}

/* Ensure past dates can't be interacted with */
.event-calendar-mobile .dp--past * {
    pointer-events: none !important;
    cursor: default !important;
}

.event-calendar-mobile .dp__month_year_select {
    pointer-events: none !important;
    display: flex !important;
    justify-content: flex-start !important;
    margin-left: 1rem !important;
}

/* Calendar header (days of week) */
.event-calendar-mobile .dp__calendar_header {
   color: #666 !important;
   font-weight: normal !important;
   font-size: 1.2rem !important;
}

.event-calendar-mobile .dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

/* Calendar item with border solution and square aspect ratio */
.event-calendar-mobile .dp__calendar_item {
   margin: 0 !important;
   padding: 0 !important;
   font-size: 1.4rem !important;
   display: flex !important;
   justify-content: center !important;
   position: relative !important;
   width: calc(100% / 7) !important;
}

/* Remove redundant borders */
.event-calendar-mobile .dp__calendar_item:first-child {
   border-left: none !important;
}

.event-calendar-mobile .dp__calendar_row:first-child .dp__calendar_item {
   border-top: none !important;
}

/* Create square aspect ratio */
.event-calendar-mobile .dp__calendar_item::before {
   content: '' !important;
   display: block !important;
   padding-top: 100% !important; /* Creates 1:1 aspect ratio */
}

/* Position the content absolutely within the square */
.event-calendar-mobile .dp__calendar_item > * {
   position: absolute !important;
   top: 0 !important;
   left: 0 !important;
   right: 0 !important;
   bottom: 0 !important;
   display: flex !important;
   align-items: center !important;
   justify-content: center !important;
}

/* Adjust cell inner to fit square */
.event-calendar-mobile .dp__cell_inner {
   position: absolute !important;
   height: 100% !important;
   width: 100% !important;
   margin: auto !important;
   padding: 0 !important;
   display: flex !important;
   align-items: center !important;
   justify-content: center !important;
   font-weight: normal !important;
   color: #333 !important;
}

.event-calendar-mobile .dp__cell_disabled {
   opacity: 0.3 !important;
   cursor: auto !important;
}

/* Selected state */
.event-calendar-mobile .dp__active {
   background-color: black !important;
   color: white !important;
}

.event-calendar-mobile .dp__active_date {
   background-color: black !important;
   color: white !important;
}

/* Range styling */
.event-calendar-mobile .dp__range_start,
.event-calendar-mobile .dp__range_end {
   background-color: black !important;
   color: white !important;
}

.event-calendar-mobile .dp__range_start {
   border-top-right-radius: 0 !important;
   border-bottom-right-radius: 0 !important;
}

.event-calendar-mobile .dp__range_end {
   border-top-left-radius: 0 !important;
   border-bottom-left-radius: 0 !important;
}

.event-calendar-mobile .dp__range_between {
   border-radius: 0 !important;
}

/* Navigation arrows */
.event-calendar-mobile .dp__arrow_bottom,
.event-calendar-mobile .dp__arrow_top {
   display: none !important;
}

/* Today's date */
.event-calendar-mobile .dp__today {
   border: none !important;
}

/* Calendar container */
.event-calendar-mobile .dp__main {
   border: none !important;
   box-shadow: none !important;
}

/* Remove borders */
.event-calendar-mobile .dp__calendar_header_separator {
   display: none !important;
}

.event-calendar-mobile .dp__theme_light {
   border: none !important;
}

.event-calendar-mobile .dp--header-wrap {
   margin-bottom: 1rem !important;
}

.event-calendar-mobile .dp__flex_display {
   display: block !important;
}

.event-calendar-mobile .dp__menu_inner.dp__flex_display {
   gap: 4rem !important;
}

.event-calendar-mobile .dp__menu_inner {
    padding: 0 !important;
}

/* Calendar layout */
.event-calendar-mobile .dp__menu_inner.dp__flex_display {
    flex-direction: column !important;
    gap: 2rem !important;
}

.event-calendar-mobile .dp__calendar_next {
    margin:0 !important;
}

/* Remove tabs from top */
.event-calendar-mobile .dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Custom container class */
.calendar-container {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 1rem !important;
}

/* Events container */
.event-calendar-mobile {
    width: 100% !important;
}

/* Make sure calendar is visible */
.event-calendar-mobile .dp__instance_calendar {
    width: 100% !important;
}

/* Arrow styling */
.dp--arrow-btn-nav .dp__inner_nav {
    display: flex !important;
    width: 5rem !important;
    height: 5rem !important;
}
.dp__inner_nav svg {
    height: 2rem !important;
    width: 2rem !important;
    stroke: #000 !important;
}

.event-calendar-mobile .dp__arrow_btn {
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

.event-calendar-mobile .dp__arrow_btn:hover {
    background-color: #e5e5e5 !important;
}

.event-calendar-mobile .dp__arrow_btn:first-child {
    left: 0.5rem !important;
}

.event-calendar-mobile .dp__arrow_btn:last-child {
    right: 0.5rem !important;
}

.event-calendar-mobile .dp__month_year_select {
    margin: 0 3rem !important; /* Make room for arrows */
}

.event-calendar-mobile .dp__inner_nav {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.event-calendar-mobile .dp--arrow-btn-nav,
.event-calendar-mobile .dp__arrow_btn_container {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.event-calendar-mobile .dp__arrow_btn svg {
    width: 1rem !important;
    height: 1rem !important;
}
</style>
