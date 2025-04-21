<template>
    <main class="w-full pb-24" :class="{'narrow-layout': isNarrowLayout}">
        <div class="w-full md:w-1/2 mx-auto" v-if="event.showtype=== null || event.showtype=== 'a'">
            <h2>Do you have specific dates?</h2>
        </div>
        <div v-else>
            <div v-if="!selectedDatesCount" class="px-8 md:px-0 pb-8">
                <h2 class="text-black">Select Dates</h2>
            </div>
            <div v-else class="flex justify-between items-end px-8 md:px-0 pb-8 w-full !pr-4" :class="{'md:w-2/3': !isNarrowLayout}">
                <h2 class="text-black">{{ selectedDatesCount }} {{ selectedDatesCount === 1 ? 'Night' : 'Nights' }}</h2>
                <div 
                    @mouseover="hoveredLocation = 'clearAllDates'" 
                    @mouseout="hoveredLocation = null" 
                    @click="clearAllDates" 
                    class="cursor-pointer"
                >
                    <span class="underline">clear selected dates</span>
                </div>
            </div>
            <p v-if="$v.selectedDates.$error" 
                class="text-red-500 mb-2 p-8">
                Please select at least one date
            </p>
        </div>
        <div class="w-full md:w-1/2 mx-auto" v-if="event.showtype=== null || event.showtype=== 'a'">
            <div class="flex flex-col w-full">
                <div class="pt-16 flex flex-col gap-8">
                    <button 
                        @click="setSpecificDates"
                        :class="[
                            'border rounded-2xl flex justify-between items-center w-full h-48 p-8 transition-all duration-200',
                            {
                                'border-[#222222] shadow-focus-black': event.showtype === 's',
                                'border-neutral-300 hover:border-[#222222] hover:shadow-focus-black hover:bg-neutral-50': event.showtype !== 's'
                            }
                        ]"
                    >
                        <div class="w-full text-left">
                            <p class="font-bold text-3xl">
                                Show has specific dates
                            </p>
                            <p class="text-1xl mt-4 text-neutral-700 font-light">
                                Select all show dates.
                            </p>
                        </div>
                    </button>
                    <button 
                        @click="event.showtype = 'a'"
                        :class="[
                            'border rounded-2xl flex justify-between items-center w-full h-48 p-8 transition-all duration-200',
                            {
                                'border-[#222222] shadow-focus-black': event.showtype === 'a',
                                'border-neutral-300 hover:border-[#222222] hover:shadow-focus-black hover:bg-neutral-50': event.showtype !== 'a'
                            }
                        ]"
                    >
                        <div class="w-full text-left">
                            <p class="font-bold text-3xl">
                                Always
                            </p>
                            <p class="text-1xl mt-4 text-neutral-700 font-light">
                                Show is available at any time.
                            </p>
                        </div>
                    </button>
                </div>
                <div class="h-[6.3rem]"></div>
            </div>
        </div>
        <div 
        v-else 
        class="relative rounded-4xl">
            <!-- Create a flex container for desktop layout -->
            <div class="w-full flex flex-col gap-6" :class="{'md:flex-row': !isNarrowLayout}">
                <!-- Calendar container - take most of the width on desktop -->
                <div class="flex-grow border-[#222222] shadow-focus-black overflow-hidden rounded-2xl relative w-full bg-white overflow-y-auto overflow-x-hidden h-[45rem] md:min-w-[540px]" :class="{'md:w-2/3': !isNarrowLayout}">
                    <div class="w-full h-full overflow-x-hidden">
                        <!-- Admin controls -->
                        <div v-if="isAdmin" class="flex items-center justify-center gap-4 pt-4">
                            <button 
                                @click="showPreviousMonths"
                                class="text-black underline font-semibold hover:text-gray-600"
                            >
                                Show previous months
                            </button>
                        </div>
                        
                        <VueDatePicker
                            ref="calendarRef"
                            v-model="date"
                            multi-dates
                            disable-year-select
                            disable-month-select
                            :multi-calendars="displayedMonths"
                            multi-calendars-solo
                            :enable-time-picker="false"
                            :dark="isDark"
                            :timezone="tz"
                            :preview-date="previewDate"
                            :min-date="minDate"
                            inline
                            auto-apply
                            @update:model-value="onDateSelect"
                            month-name-format="long"
                            hide-offset-dates
                            :month-change-on-scroll="false"
                            week-start="0"
                        />
                        
                        <!-- Load More Button - modified to always show for admins or show when displayedMonths < 6 -->
                        <div v-if="displayedMonths < 6 || isAdmin" class="w-full flex justify-center my-8">
                            <button 
                                @click="loadMoreMonths"
                                class="text-black underline font-semibold hover:text-gray-600"
                            >
                                Show more dates
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sidebar container - move to right on desktop -->
                <div class="w-full flex flex-col justify-between bg-white mt-6" :class="{'md:w-1/3': !isNarrowLayout, 'md:mt-0': !isNarrowLayout}">
                    <div class="h-full flex flex-col justify-between">
                        <div class="">
                            <div class="lg:px-8 relative flex flex-col gap-4">

                                <div v-if="promptVisible" 
                                     class="p-4 rounded-2xl relative bg-black text-white border-black border hover:bg-neutral-800 transition-colors">
                                    <div class="cursor-pointer" @click="handlePromptYes">
                                        <p class="text-white">{{ promptMessage }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col">
                                    <textarea 
                                        name="Show times" 
                                        class="text-2xl font-normal border rounded-2xl p-4 w-full" 
                                        :class="{ 
                                            'border-red-500 focus:shadow-focus-error': $v.event.show_times.$error,
                                            'border-neutral-300 focus:border-[#222222] focus:shadow-focus-black': !$v.event.show_times.$error
                                        }"
                                        v-model="event.show_times" 
                                        @input="$v.event.show_times.$touch"
                                        placeholder="Please provide a brief description of your show times, e.g., doors open at 7 PM, show starts at 8 PM"
                                        :rows="textareaRows"
                                        style="white-space: pre-wrap;"
                                    ></textarea>
                                    <p v-if="$v.event.show_times.$dirty && $v.event.show_times.maxLength.$invalid" 
                                       class="text-red-500 text-1xl mt-2 px-4">
                                        Too many characters
                                    </p>
                                </div>
                                <div class="flex w-full border p-4 rounded-2xl border-neutral-300 hover:border-[#222222] hover:bg-neutral-50 hover:shadow-focus-black transition-all duration-200">
                                    <p class="text-lg">Timezone: </p>
                                    <select id="timezone" 
                                            v-model="selectedTimezone" 
                                            class="pl-2 ml-2 font-bold w-full cursor-pointer bg-transparent">
                                        <option v-for="timezone in timezones" 
                                                :key="timezone.name" 
                                                :value="timezone.name">
                                            {{ timezone.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <div class="flex flex-col p-4 border border-neutral-300 rounded-2xl hover:border-[#222222]">
                                        <div class="mb-2">
                                            <p class="text-xl font-medium">Does the event have a specific embargo date?</p>
                                            <p class="text-lg text-gray-600">(i.e. The date you would like it to first appear on EI)</p>
                                        </div>
                                        <div class="flex justify-between items-center w-full mt-2">
                                            <ToggleSwitch 
                                                v-model="embargoToggle" 
                                                leftLabel="No" 
                                                rightLabel="Yes" 
                                                @update:modelValue="handleEmbargoToggleChange"
                                            />
                                            <div v-if="hasEmbargoDate" class="flex items-center cursor-pointer" @click="showEmbargoCalendar">
                                                <p class="underline mr-2 text-1xl">{{ formattedEmbargoDate }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Embargo Calendar Modal -->
                                <div v-if="showEmbargoModal" class="c-embargo fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                                    <div class="bg-white p-8 rounded-2xl w-[600px]">
                                        <h3 class="text-2xl mb-4">Select when your event should appear on EI</h3>
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
                                                @click="cancelEmbargoDate"
                                                class="px-6 py-2 border rounded-lg hover:bg-gray-100"
                                            >
                                                Cancel
                                            </button>
                                            <button 
                                                @click="confirmEmbargoDate"
                                                class="px-6 py-2 bg-black text-white rounded-lg hover:bg-gray-800"
                                            >
                                                Set Embargo Date
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="w-full flex justify-between px-8">
                            <button @click="event.showtype = null" 
                                    class="mt-8 text-xl rounded-2xl hover:text-neutral-600 transition-colors underline">
                                change show to always available
                            </button>
                        </div>
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
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';

// 2. Injected Dependencies & Core State
const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const user = inject('user');
const date = ref([]);
const windowWidth = ref(0);
const displayedMonths = ref(3);
const isDesktop = computed(() => windowWidth.value >= 768);
const calendarLayout = computed(() => isDesktop.value ? 'horizontal' : 'vertical');
const initialMonthsToShow = computed(() => isDesktop.value ? 2 : 3);
const isAdmin = computed(() => user && (user.isAdmin || false));
const previewDate = ref(new Date());

// Reference for calendar navigation
const calendarRef = ref(null);

// 3. Calendar State
const events = ref([]);
const selectedDates = ref([]);

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
const embargoToggle = ref(false);

// Additional refs for previous months functionality
const minDate = computed(() => {
    // Admins can select past dates (up to 1 year ago)
    if (isAdmin.value) {
        const pastDate = new Date();
        pastDate.setFullYear(pastDate.getFullYear() - 1);
        return pastDate;
    }
    // Regular users can only select current dates and future
    return new Date();
});

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

const isNarrowLayout = computed(() => {
    return event.status === 'e' || event.status === 'p' || !isDesktop.value;
});

const handleResize = () => {
    windowWidth.value = window?.innerWidth ?? 0;
    
    // Update displayedMonths based on screen size
    // Only update if we're not already showing all 6 months
    if (displayedMonths.value !== 6) {
        displayedMonths.value = windowWidth.value >= 768 ? 2 : 3;
    }
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
    const currentDate = moment().tz(timezone);
    const sixMonthsFromNow = moment().tz(timezone).add(180, 'days');
    
    const newDates = [...date.value];
    
    // Start from the selected date
    let nextDate = startDate.clone();
    
    // Keep adding weekly occurrences until we reach 6 months from today
    while (nextDate.isBefore(sixMonthsFromNow)) {
        const dateObj = nextDate.clone().hours(12).minutes(0).seconds(0).toDate();
        
        // Only add the date if it's not already selected
        if (!date.value.some(d => moment(d).isSame(dateObj, 'day'))) {
            newDates.push(dateObj);
        }
        
        // Move to next week
        nextDate = nextDate.clone().add(1, 'weeks');
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
    const sixMonthsFromNow = moment().tz(timezone).add(180, 'days');

    // Keep dates that are either:
    // 1. Not on the same day of week as the selected date, or
    // 2. Not after the selected date, or
    // 3. Beyond 6 months from today
    const newDates = date.value.filter(d => {
        const dateObj = moment(d).tz(timezone).hours(12);
        return dateObj.day() !== startDay || 
               !dateObj.isAfter(startDate) || 
               dateObj.isAfter(sixMonthsFromNow);
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
const handleEmbargoToggleChange = (value) => {
    if (value) {
        // Toggle set to "Yes" - show calendar
        showEmbargoCalendar();
    } else {
        // Toggle set to "No" - clear embargo date
        event.embargo_date = null;
    }
};

const toggleEmbargoDate = () => {
    embargoToggle.value = !embargoToggle.value;
    handleEmbargoToggleChange(embargoToggle.value);
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
        date.setUTCHours(12, 0, 0, 0);
        event.embargo_date = date.toISOString().slice(0, 19).replace('T', ' ');
        embargoToggle.value = true; // Ensure toggle is set to Yes
        console.log('Set embargo_date to:', event.embargo_date);
        showEmbargoModal.value = false;
        tempEmbargoDate.value = null;
    }
};

const cancelEmbargoDate = () => {
    // If user was trying to set an embargo date for the first time
    // and then cancels, set the toggle back to No
    if (!hasEmbargoDate.value) {
        embargoToggle.value = false;
    }
    showEmbargoModal.value = false;
};

const clearEmbargoToggle = () => {
    event.embargo_date = null;
    embargoToggle.value = false; // Set toggle to No
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
        
        // Always use 's' or 'a' for showtype, never 'l' or 'o'
        // If it was originally 'l' or 'o', we've already converted it to 's' for editing
        
        const data = {
            showtype: event.showtype === 'a' ? 'a' : 's',
            dateArray: event.showtype === 'a' ? [] : formattedDates,
            timezone: selectedTimezone.value,
            show_times: event.show_times,
            embargo_date: event.embargo_date
        };
        console.log('Submitting data:', data);
        return data;
    }
});

// 15. Lifecycle Hooks
onMounted(() => {
    initializeTimezones();
    
    // Clear any existing dates first
    date.value = [];
    selectedDates.value = [];
    events.value = [];
    
    // Initialize embargo toggle based on existing embargo date
    embargoToggle.value = !!event.embargo_date;
    
    // Only set dates if we have shows and we're in specific dates mode (s), live mode (l), or ongoing mode (o)
    if (event.shows?.length > 0 && (event.showtype === 's' || event.showtype === 'l' || event.showtype === 'o')) {
        // If showtype is "l" or "o", set it to "s" for editing purposes
        if (event.showtype === 'l' || event.showtype === 'o') {
            // Convert legacy 'l' or 'o' type to 's'
            event.showtype = 's';
        }
        
        const showDates = event.shows.map(show => {
            const date = new Date(show.date);
            date.setHours(12, 0, 0, 0);
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
    displayedMonths.value = initialMonthsToShow.value;
    window?.addEventListener('resize', handleResize);
    
    // Apply admin-enabled class to past dates for admins
    if (isAdmin.value) {
        setTimeout(() => {
            // Add admin-enabled class to past dates
            const pastDateElements = document.querySelectorAll('.dp--past');
            if (pastDateElements) {
                pastDateElements.forEach(element => {
                    element.classList.add('admin-enabled');
                });
            }
            
            // Add admin-enabled-calendar class to the calendar container
            const calendarContainer = document.querySelector('.dp__instance_calendar');
            if (calendarContainer) {
                calendarContainer.classList.add('admin-enabled-calendar');
            }
            
            // Add data-year attributes to the month/year elements
            updateMonthYearElements();
        }, 300);
    }
});

// Watch for changes to hasEmbargoDate to keep toggle in sync
watch(hasEmbargoDate, (newValue) => {
    embargoToggle.value = newValue;
});

onUnmounted(() => {
    window?.removeEventListener('resize', handleResize);
});

// 16. Utility Methods
const loadMoreMonths = () => {
    // First, capture the current active month before we change anything
    const calendar = document.querySelector('.dp__instance_calendar');
    const currentScrollPosition = calendar ? calendar.scrollTop : 0;
    
    // Remember which months we already have displayed before adding more
    const oldMonthCount = displayedMonths.value;
    
    // For non-admins, cap at 6 months
    // For admins, if already at 6, add 3 more months
    if (!isAdmin.value) {
        displayedMonths.value = 6;
    } else {
        displayedMonths.value = displayedMonths.value >= 6 ? displayedMonths.value + 3 : 6;
    }
    
    // After adding more months, try to maintain the same scroll position
    // This is a much simpler approach that should work reliably
    setTimeout(() => {
        // First update the year labels
        updateMonthYearElements();
        
        // Then restore the scroll position
        const updatedCalendar = document.querySelector('.dp__instance_calendar');
        if (updatedCalendar) {
            // Simply restore the previous scroll position
            updatedCalendar.scrollTop = currentScrollPosition;
            
            // Add a console log to debug
            console.log('Restoring scroll position:', currentScrollPosition);
        }
    }, 100);
};

const getMonthDate = (offset) => {
    const date = new Date();
    date.setMonth(date.getMonth() + offset);
    return date;
};

// Add these with your other refs (in section "2. Injected Dependencies & Core State")
const isDark = ref(false);  // for dark mode state
const tz = computed(() => selectedTimezone.value);  // use the selected timezone

const showPreviousMonths = () => {
    // Get a reference to the calendar component
    console.log('Calendar ref:', calendarRef.value);
    
    // Get current date
    const currentDate = new Date(previewDate.value);
    console.log('Current date:', currentDate);
    
    // Calculate the new date (2 months back instead of 3)
    const newMonth = currentDate.getMonth() - 2;
    const newYear = currentDate.getFullYear() + Math.floor(newMonth / 12);
    const adjustedMonth = ((newMonth % 12) + 12) % 12; // Handle negative months
    
    console.log('New month/year:', adjustedMonth, newYear);
    
    // Update previewDate (this alone doesn't seem to work)
    currentDate.setMonth(currentDate.getMonth() - 2);
    previewDate.value = currentDate;
    
    // Use the DatePicker's API method to change month programmatically
    if (calendarRef.value) {
        // Set month and year using component's method
        calendarRef.value.setMonthYear({ 
            month: adjustedMonth, 
            year: newYear 
        });
        console.log('Updated calendar using setMonthYear');
        
        // Update the month/year elements with year data
        updateMonthYearElements();
    }
};

// Add this new function to update month/year elements with the year data attribute
const updateMonthYearElements = () => {
    if (isAdmin.value) {
        setTimeout(() => {
            const monthYearElements = document.querySelectorAll('.dp__month_year_select');
            monthYearElements.forEach(element => {
                const monthText = element.textContent || '';
                // Extract month and find the corresponding year
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                const monthIndex = monthNames.findIndex(m => monthText.includes(m));
                
                if (monthIndex !== -1) {
                    // Calculate the year based on the month index and current date
                    const baseDate = new Date(previewDate.value);
                    const currentMonthIndex = baseDate.getMonth();
                    const currentYear = baseDate.getFullYear();
                    
                    // Calculate how many months ahead/behind this month is from the current month
                    let monthOffset = 0;
                    let yearToShow = currentYear;
                    
                    // Adjust for multiple calendars displayed
                    const elementIndex = Array.from(monthYearElements).indexOf(element);
                    if (elementIndex >= 0) {
                        monthOffset = elementIndex;
                    }
                    
                    // If this is a past month in the calendar display
                    const targetMonth = (currentMonthIndex + monthOffset) % 12;
                    const yearOffset = Math.floor((currentMonthIndex + monthOffset) / 12);
                    yearToShow = currentYear + yearOffset;
                    
                    // Set the year as data attribute (last two digits)
                    const yearSuffix = String(yearToShow).slice(-2);
                    element.setAttribute('data-year', `'${yearSuffix}`);
                }
            });
        }, 350);
    }
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
   justify-content: flex-start !important;
   align-items: center;
   padding:1rem;
}

/* Admin styling for month/year display - add year digits */
.admin-enabled-calendar .dp__month_year_wrap .dp__month_year_select {
   position: relative;
}

/* Add the last two digits of the year after month */
.admin-enabled-calendar .dp__month_year_wrap .dp__month_year_select::after {
   content: attr(data-year);
   font-size: 1.7rem;
   font-weight: 400;
   margin-left: 0.5rem;
   opacity: 1;
}

.dp--past {
    pointer-events: none !important;
    opacity: 0.3 !important;
}

/* Admin override for past dates */
.dp--past.admin-enabled {
    pointer-events: auto !important;
    opacity: 0.6 !important;
    cursor: pointer !important;
}

.dp--past .dp__cell_inner {
    color: #999 !important;
    background: transparent !important;
}

/* Admin override for selected past dates */
.dp--past.admin-enabled .dp__cell_inner {
    color: #555 !important;
}

.dp--past.dp__active_date,
.dp--past .dp__active {
    background-color: #999 !important;
    color: #fff !important;
}

/* Admin override for selected past dates */
.dp--past.admin-enabled.dp__active_date,
.dp--past.admin-enabled .dp__active {
    background-color: #666 !important;
    color: #fff !important;
}

/* Ensure past dates can't be interacted with */
.dp--past * {
    pointer-events: none !important;
    cursor: default !important;
}

/* Admin override for past dates */
.dp--past.admin-enabled * {
    pointer-events: auto !important;
    cursor: pointer !important;
}

.dp__month_year_select {
    pointer-events: none !important;
    display: flex;
    justify-content: flex-start !important;
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

/* Add horizontal layout styles */
@media (min-width: 768px) {
    /* Calendar container styles */
    .dp__instance_calendar {
        width: 100% !important;
    }
    
    /* Override the default display for the calendars container to create grid */
    .dp__menu_inner {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 20px !important;
        width: 100% !important;
        padding: 1rem !important;
    }
    
    /* Fix the calendar width in the grid */
    .dp__calendar {
        width: 100% !important;
        min-width: 0 !important;
    }
    
    /* Make calendar items slightly smaller on desktop grid */
    .dp__calendar_item {
        font-size: 1.2rem;
    }
    
    /* Ensure the "Show more dates" button is full width */
    .dp__menu_inner + div {
        grid-column: span 2;
    }
    
    /* Adjust height for the sidebar to match calendar */
    .md\:w-1\/3 {
        max-height: 45rem;
        overflow-y: auto;
    }
}

/* Conditional styles for narrow layout (mobile or status e/p) */
@media (min-width: 768px) {
    /* When in narrow layout, revert to default column display */
    .narrow-layout .dp__menu_inner {
        display: flex !important;
        flex-direction: column !important;
        gap: 2rem !important;
    }
}

/* Ensure this doesn't interfere with our grid layout */
.dp__menu_inner.dp__flex_display {
    gap: 2rem;
}
@media (max-width: 767px) {
    /* For mobile, keep original column layout */
    .dp__menu_inner {
        display: flex !important;
        flex-direction: column !important;
    }
}

/* Optional: Apply smooth transition between layouts */
.dp__calendar, .dp__menu_inner {
    transition: all 0.3s ease-in-out;
}
</style>
