<template>
    <main class="w-full pb-24">
        <template v-if="event.showtype=== null || event.showtype=== 'a'">
            <h2>Do you have specific dates?</h2>
        </template>
        <template v-else>
            <div v-if="!selectedDatesCount" class="px-8 md:px-0 pb-8">
                <h2>Select Dates</h2>
            </div>
            <div v-else class="flex justify-between items-center px-8 md:px-0 pb-8">
                <h2>{{ selectedDatesCount }} {{ selectedDatesCount === 1 ? 'Night' : 'Nights' }}</h2>
                <div 
                    @mouseover="hoveredLocation = 'clearAllDates'" 
                    @mouseout="hoveredLocation = null" 
                    @click="clearAllDates" 
                    class="cursor-pointer bg-white"
                >
                    <component :is="hoveredLocation === 'clearAllDates' ? RiCloseCircleFill : RiCloseCircleLine" />
                </div>
            </div>
            <p v-if="$v.selectedDates.$error" 
                class="text-red-500 mb-2 p-8">
                Please select at least one date
            </p>
        </template>
        <div v-if="event.showtype=== null || event.showtype=== 'a'">
            <div class="flex flex-col w-full">
                <div class="pt-16 flex flex-col gap-8">
                    <button 
                        @click="setSpecificDates"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center w-full pb-4 hover:border-2 hover hover:border-black h-48 p-8">
                        <div class="w-full text-left">
                            <h4 class="font-bold text-3xl">
                                Show has specific dates
                            </h4>
                            <p class="text-1xl mt-4 text-gray-700 font-light">
                                Either random or days of the week.
                            </p>
                        </div>
                    </button>
                    <button 
                        @click="event.showtype = 'a'"
                        :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.showtype === 'a' }"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center w-full hover:border-2 hover hover:border-black h-48 p-8">
                        <div class="w-full text-left">
                            <h4 class="font-bold text-3xl">
                                Always
                            </h4>
                            <p class="text-1xl mt-4 text-gray-700 font-light">
                                Show is everyday or always available at any time.
                            </p>
                        </div>
                    </button>
                </div>
                <div class="h-[6.3rem]"></div>
            </div>
        </div>
        <div 
        v-else 
        class="relative border border-gray-200 rounded-4xl overflow-hidden">
            <div class="flex-grow relative w-full border bg-white overflow-y-auto overflow-x-hidden h-[45rem]">
                <VueDatePicker
                    v-model="date"
                    multi-dates
                    disable-year-select
                    disable-month-select
                    :multi-calendars="displayedMonths"
                    :enable-time-picker="false"
                    :dark="isDark"
                    :timezone="tz"
                    :preview-date="new Date()"
                    :min-date="new Date()"
                    inline
                    auto-apply
                    @update:model-value="onDateSelect"
                    month-name-format="long"
                    hide-offset-dates
                    :month-change-on-scroll="false"
                    week-start="0"
                />
                
                <!-- Load More Button -->
                <div v-if="displayedMonths === 3" class="w-full flex justify-center my-8">
                    <button 
                        @click="loadMoreMonths"
                        class="text-black underline font-semibold hover:text-gray-600"
                    >
                        Show more dates
                    </button>
                </div>
            </div>
            <div class="w-full h-full flex flex-col justify-between bg-white">
                <div class="h-full flex flex-col justify-between mt-12">
                    <div class="">
                        <div v-if="selectedDatesCount" class="p-8 relative flex flex-col gap-4">

                            <div v-if="promptVisible" class="p-4 rounded-2xl relative bg-black text-white border-black border hover:bg-neutral-700 hover:shadow-[0_0_0_1.5px_black]">
                                <div class="cursor-pointer" @click="handlePromptYes">
                                    <p class="text-white">{{ promptMessage }}</p>
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <textarea 
                                    name="Show times" 
                                    class="text-2xl font-normal border border-gray-300 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full" 
                                    v-model="event.show_times" 
                                    @input="$v.event.show_times.$touch"
                                    placeholder="Please provide a brief description of your show times, e.g., doors open at 7 PM, show starts at 8 PM"
                                    :rows="textareaRows"
                                    style="white-space: pre-wrap;"
                                ></textarea>
                                <p v-if="$v.event.show_times.$dirty && $v.event.show_times.maxLength.$invalid" 
                                   class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                                    Too many characters
                                </p>
                            </div>
                            <div class="flex w-full border p-4 rounded-2xl border-gray-300 hover:bg-gray-100 hover:shadow-[0_0_0_2px_black]">
                                <p class="text-lg">Timezone: </p>
                                <select id="timezone" v-model="selectedTimezone" class="pl-2 ml-2 font-bold w-full cursor-pointer hover:bg-transparent">
                                    <option v-for="timezone in timezones" :key="timezone.name" :value="timezone.name">
                                        {{ timezone.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-2">
                                <div v-if="!hasEmbargoDate" class="flex items-center justify-between p-4 border rounded-2xl hover:border-black">
                                    <div class="flex justify-between items-center w-full">
                                        <span>Publish event the date it's approved</span>
                                        <button 
                                            @click="toggleEmbargoDate" 
                                            class="px-4 py-2 border rounded-lg bg-black text-white"
                                        >
                                            Yes
                                        </button>
                                    </div>
                                </div>
                                
                                <div v-if="hasEmbargoDate" class="flex justify-between items-center">
                                    <div @click="showEmbargoCalendar" class="cursor-pointer">
                                        <p class="text-sm text-gray-600">Goes live:</p>
                                        <p class="underline">{{ formattedEmbargoDate }}</p>
                                    </div>
                                    <div 
                                        @mouseover="hoveredLocation = 'clearEmbargoDate'" 
                                        @mouseout="hoveredLocation = null" 
                                        @click="clearEmbargoToggle" 
                                        class="cursor-pointer bg-white"
                                    >
                                        <component :is="hoveredLocation === 'clearEmbargoDate' ? RiCloseCircleFill : RiCloseCircleLine" />
                                    </div>
                                </div>
                            </div>
                            <!-- Embargo Calendar Modal -->
                            <div v-if="showEmbargoModal" class="c-embargo fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                                <div class="bg-white p-8 rounded-2xl w-[600px]">
                                    <h3 class="text-2xl mb-4">Choose when your event goes live</h3>
                                    <VueDatePicker
                                        v-model="tempEmbargoDate"
                                        :enable-time-picker="false"
                                        :dark="isDark"
                                        :timezone="selectedTimezone"
                                        :preview-date="new Date()"
                                        :min-date="new Date()"
                                        inline
                                        auto-apply
                                        month-name-format="long"
                                        hide-offset-dates
                                        :month-change-on-scroll="false"
                                        week-start="0"
                                        @update:model-value="selectEmbargoDate"
                                        class="embargo-calendar"
                                        style="width: 100%"                                    />
                                    <div class="mt-4 flex justify-end gap-4">
                                        <button 
                                            @click="showEmbargoModal = false"
                                            class="px-6 py-2 border rounded-lg hover:bg-gray-100"
                                        >
                                            Cancel
                                        </button>
                                        <button 
                                            @click="confirmEmbargoDate"
                                            class="px-6 py-2 bg-black text-white rounded-lg hover:bg-gray-800"
                                        >
                                            Confirm
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="w-full flex justify-between p-8">
                        <button @click="event.showtype = null" class="mt-8 text-xl rounded-2xl underline">Switch show type</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Clear Dates Confirmation Modal -->
        <div v-if="showClearConfirmation" 
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
             @click="showClearConfirmation = false">
            <div class="bg-white p-8 rounded-2xl w-[400px] mx-4" 
                 @click.stop>
                <h3 class="text-2xl mb-4">Clear all dates?</h3>
                <p class="text-gray-600 mb-6">This will remove all selected dates and show times. This action cannot be undone.</p>
                <div class="flex justify-end gap-4">
                    <button 
                        @click="showClearConfirmation = false"
                        class="px-6 py-2 border rounded-lg hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="confirmClearAllDates"
                        class="px-6 py-2 bg-black text-white rounded-lg hover:bg-gray-800"
                    >
                        Clear All
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
// 1. Core Imports
import { ref, computed, inject, onMounted, watch, onUnmounted } from 'vue';
import VueCal from 'vue-cal';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import 'vue-cal/dist/vuecal.css';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { maxLength, required } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';

// 2. Injected Dependencies & Core State
const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const date = ref([]);
const windowWidth = ref(0);
const displayedMonths = ref(3);

// 3. Calendar State
const events = ref([]);
const selectedDates = ref([]);
const tempSelectedDates = ref([]);
const tempShowTimes = ref('');
const previousShowType = ref(null);

// 4. Prompt State
const promptVisible = ref(false);
const promptMessage = ref('');
const promptAction = ref(null);
const selectedDate = ref(null);
const hoveredLocation = ref(null);

// 5. Timezone & Embargo State
const timezones = ref([]);
const selectedTimezone = ref(Intl.DateTimeFormat().resolvedOptions().timeZone);
const userGMTOffset = ref('');
const showEmbargoModal = ref(false);
const tempEmbargoDate = ref(null);
const showClearConfirmation = ref(false);

// 6. Validation Rules
const rules = {
    event: {
        show_times: { maxLength: maxLength(500) }
    },
    selectedDates: {
        required: (value) => {
            return event.showtype !== 's' || (event.showtype === 's' && value.length > 0);
        }
    }
};

const $v = useVuelidate(rules, { 
    event,
    selectedDates 
});

// 7. Computed Properties & Basic Helpers
const selectedDatesCount = computed(() => selectedDates.value.length);
const hasEmbargoDate = computed(() => !!event.embargo_date);
const formattedEmbargoDate = computed(() => {
    if (!event.embargo_date) return '';
    return new Date(event.embargo_date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
});

const textareaRows = computed(() => {
    return windowWidth.value >= 768 ? 6 : 4;
});

const handleResize = () => {
    windowWidth.value = window?.innerWidth ?? 0;
};

const checkFutureDates = (dateStr) => {
    const date = new Date(dateStr);
    const weekday = date.getDay();
    
    return selectedDates.value.some(d => {
        const existingDate = new Date(d);
        return existingDate > date && existingDate.getDay() === weekday;
    });
};

// 8. Date Selection & Event Handling
const onDateSelect = (dates) => {
    date.value = dates;
    
    const newSelectedDates = dates.map(d => {
        const date = new Date(d);
        date.setHours(12, 0, 0, 0);
        return date.toISOString().split('T')[0];
    });

    const addedDate = newSelectedDates.find(d => !selectedDates.value.includes(d));
    const removedDate = selectedDates.value.find(d => !newSelectedDates.includes(d));

    selectedDates.value = newSelectedDates;
    promptVisible.value = false;

    if (dates.length < date.value.length) {
        return;
    }

    if (addedDate || removedDate) {
        const dateToCheck = addedDate || removedDate;
        const dateObj = new Date(dateToCheck);
        dateObj.setDate(dateObj.getDate() + 1);
        const weekday = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
        const futureDatesExist = checkFutureDates(dateToCheck);

        if (addedDate) {
            showPrompt('selectWeekly', `Repeat future ${weekday}s`, dateToCheck);
        } else if (removedDate && futureDatesExist) {
            showPrompt('removeFuture', `Remove future ${weekday}s?`, dateToCheck);
        }
    }
};

const handleDateSelection = (formattedDate, weekday) => {
    selectedDates.value.push(formattedDate);
    events.value.push({ start: formattedDate, end: formattedDate, title: 'Selected' });

    const futureDatesExist = checkFutureDates(formattedDate);
    if (!futureDatesExist) {
        showPrompt('selectWeekly', `Repeat future ${weekday}s`, formattedDate);
    }
};

const handleDateDeselection = (formattedDate, weekday) => {
    selectedDates.value = selectedDates.value.filter(d => d !== formattedDate);
    events.value = events.value.filter(event => event.start !== formattedDate);
    
    const futureDatesExist = checkFutureDates(formattedDate);
    if (futureDatesExist) {
        showPrompt('removeFuture', `Remove future ${weekday}s?`, formattedDate);
    }
};

// 9. Weekly Events Management
const createWeeklyEvents = async (startDateStr) => {
    const { default: moment } = await import('moment-timezone');
    const timezone = selectedTimezone.value;
    const startDate = moment.tz(startDateStr + 'T12:00:00', timezone);
    const targetDay = startDate.day();
    
    const newDates = [...date.value];
    
    for (let i = 1; i < 26; i++) {
        const nextDate = startDate.clone().add(i, 'weeks');
        if (nextDate.isAfter(moment().add(180, 'days'))) break;
        
        const dateObj = nextDate.hours(12).minutes(0).seconds(0).toDate();
        if (!date.value.some(d => d.getTime() === dateObj.getTime())) {
            newDates.push(dateObj);
        }
    }
    
    date.value = newDates;
    selectedDates.value = newDates.map(d => {
        const date = new Date(d);
        date.setHours(12, 0, 0, 0);
        return date.toISOString().split('T')[0];
    });
};

const removeWeeklyEvents = async (startDateStr) => {
    const { default: moment } = await import('moment-timezone');
    const timezone = selectedTimezone.value;
    const startDate = moment.tz(startDateStr + 'T12:00:00', timezone);
    const startDay = startDate.day();

    const newDates = date.value.filter(d => {
        const date = moment(d).tz(timezone).hours(12);
        return !(date.isAfter(startDate) && date.day() === startDay);
    });

    date.value = newDates;
    selectedDates.value = newDates.map(d => {
        const date = new Date(d);
        date.setHours(12, 0, 0, 0);
        return date.toISOString().split('T')[0];
    });
};

// 10. Prompt Handling
const showPrompt = (action, message, date) => {
    promptVisible.value = true;
    promptMessage.value = message;
    promptAction.value = action;
    selectedDate.value = date;
};

const handlePromptYes = async () => {
    if (promptAction.value === 'selectWeekly') {
        await createWeeklyEvents(selectedDate.value);
    } else if (promptAction.value === 'removeFuture') {
        await removeWeeklyEvents(selectedDate.value);
    }
    promptVisible.value = false;
    promptAction.value = null;
    selectedDate.value = null;
};

// 11. Date Management Methods
const setSpecificDates = () => {
    event.showtype = 's';
};

const clearAllDates = () => {
    showClearConfirmation.value = true;
};

const confirmClearAllDates = () => {
    date.value = [];
    selectedDates.value = [];
    events.value = [];
    event.show_times = '';
    showClearConfirmation.value = false;
};

// 12. Embargo Date Methods
const toggleEmbargoDate = () => {
    if (hasEmbargoDate.value) {
        event.embargo_date = null;
    } else {
        showEmbargoCalendar();
    }
};

const showEmbargoCalendar = () => {
    if (event.embargo_date) {
        tempEmbargoDate.value = new Date(event.embargo_date);
    }
    showEmbargoModal.value = true;
};

const selectEmbargoDate = (selectedDate) => {
    tempEmbargoDate.value = selectedDate;
};

const confirmEmbargoDate = () => {
    if (tempEmbargoDate.value) {
        const date = new Date(tempEmbargoDate.value);
        date.setHours(11, 0, 0, 0);
        event.embargo_date = date.toISOString().slice(0, 19).replace('T', ' ');
        showEmbargoModal.value = false;
        tempEmbargoDate.value = null;
    }
};

const clearEmbargoToggle = () => {
    event.embargo_date = null;
};

// 13. API Methods
const initializeTimezones = async () => {
    const { default: moment } = await import('moment-timezone');
    timezones.value = moment.tz.names().map(name => ({ name }));
};

// 14. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { dates: ['Please select at least one date'] };
        }
        
        return isValid;
    },
    submitData: () => {
        const formattedDates = selectedDates.value.map(date => 
            new Date(date).toISOString().slice(0, 19).replace('T', ' ')
        );
        
        const data = {
            showtype: event.showtype,
            dateArray: event.showtype === 'a' ? [] : formattedDates,
            timezone: selectedTimezone.value,
            show_times: event.show_times,
            embargo_date: event.embargo_date
        };
        return data;
    }
});

// 15. Watchers
watch(() => event.showtype, (newType, oldType) => {
    if (oldType === 's' && newType === 'a') {
        tempSelectedDates.value = [...selectedDates.value];
        tempShowTimes.value = event.show_times;
        previousShowType.value = 's';
        selectedDates.value = [];
        events.value = [];
        event.show_times = '';
    } else if (oldType === 'a' && newType === 's') {
        selectedDates.value = [];
        events.value = [];
    } else if (newType === null && previousShowType.value === 's') {
        selectedDates.value = [...tempSelectedDates.value];
        event.show_times = tempShowTimes.value;
        events.value = tempSelectedDates.value.map(date => ({
            start: date,
            end: date,
            title: 'Selected'
        }));
    }
}, { deep: true });

// 16. Lifecycle Hooks
onMounted(() => {
    initializeTimezones();
    if (event.shows?.length > 0) {
        const showDates = event.shows.map(show => {
            const date = new Date(show.date);
            date.setHours(11, 0, 0, 0);
            return date;
        });
        
        date.value = showDates;
        selectedDates.value = showDates.map(d => d.toISOString().split('T')[0]);
        events.value = selectedDates.value.map(date => ({
            start: date,
            end: date,
            title: 'Selected'
        }));
    }
    
    windowWidth.value = window?.innerWidth ?? 0;
    window?.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window?.removeEventListener('resize', handleResize);
});

// 17. Utility Methods
const loadMoreMonths = () => {
    displayedMonths.value = 6;
    setTimeout(() => {
        const calendar = document.querySelector('.custom-calendar');
        if (calendar) {
            const lastMonth = calendar.querySelector('.vuecal__month:last-child');
            if (lastMonth) {
                lastMonth.scrollIntoView({ behavior: 'smooth' });
            }
        }
    }, 100);
};

const getMonthDate = (offset) => {
    const date = new Date();
    date.setMonth(date.getMonth() + offset);
    return date;
};
</script>

<style>
.dp--arrow-btn-nav {
    display: none;
}

/* Remove the tabs at the top */
.dp__menu_inner .dp__menu_items {
   display: none !important;
}

/* Calendar styling */
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

/* Hover state */
.dp__cell_inner:not(.dp--past):hover {
   border: 2px solid black !important;
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

/* Optional: Add smooth transition */
.dp__calendar {
    transition: all 0.3s ease;
}

/* Override arrow styles for embargo calendar */
.embargo-calendar .dp--arrow-btn-nav {
    display: flex !important;
}

/* Style the arrows for embargo calendar */
.embargo-calendar .dp__arrow_btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 50%;
}

.embargo-calendar .dp__arrow_btn:hover {
    background-color: #f3f3f3;
}

/* Keep the original arrow hiding only for the main calendar */
.dp__calendar:not(.embargo-calendar) .dp--arrow-btn-nav {
    display: none;
}

</style>
