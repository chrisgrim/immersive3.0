<template>
    <div class="w-full">
        <!-- Create a flex container for desktop layout -->
        <div class="w-full flex flex-col gap-6 md:mt-[-1rem] md:mt-0" :class="{'md:flex-row': shouldUseRowLayout, 'mt-[-4.5rem]': !isEditMode}">
            <!-- Icon button column (creation mode only) -->
            <div v-if="showIconButton" class="hidden md:block w-48 px-8">
                <div class="h-24 md:h-40 w-full items-center justify-center flex">
                    <button 
                        @click="$emit('back-to-selection')"
                        class="transition-colors"
                        title="Switch Date Type"
                    >
                        <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 hover:bg-gray-200">
                            <svg 
                                class="w-10 h-10" 
                                viewBox="0 0 24 24" 
                                fill="none" 
                                stroke="gray" 
                                stroke-width="2" 
                                stroke-linecap="round" 
                                stroke-linejoin="round"
                            >
                                <path d="M19 12H5"/>
                                <path d="M12 19l-7-7 7-7"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
            
            <!-- Calendar container - take most of the width on desktop -->
            <div class="flex-grow mt-4 overflow-hidden relative w-full bg-white overflow-y-auto overflow-x-hidden md:min-w-[540px]" :class="[
                {'md:w-2/3': shouldUseRowLayout},
                layoutMode === 'edit-desktop-sidebar' ? 'md:h-[calc(100vh-39rem)]' : 'md:h-[calc(100vh-24rem)]'
            ]">
                <!-- Text button (mobile or edit mode) -->
                <div v-if="showTextButton" class="mb-6 md:mb-8">
                    <button 
                        @click="$emit('back-to-selection')"
                        class="text-xl text-left text-gray-500 hover:text-black underline whitespace-nowrap w-full"
                    >
                        Change Date Type
                    </button>
                </div>
                
                <!-- Calendar column toggle buttons (only show in creation mode or edit with sidebar collapsed) -->
                <div v-if="layoutMode !== 'edit-desktop-sidebar'" class="absolute top-0 right-2 z-[1001] flex gap-2 hidden md:flex">
                    <!-- Show 2x button when currently in 1-column mode -->
                    <button 
                        v-if="calendarColumns === 1"
                        @click="setCalendarColumns(2)"
                        class="bg-white border border-gray-300 rounded-full bg-[#f5f5f5] px-6 py-2 text-1xl font-medium hover:bg-gray-50 shadow-sm"
                        title="Switch to 2 columns"
                    >
                        2x
                    </button>
                    
                    <!-- Show 1x button when currently in 2-column mode -->
                    <button 
                        v-if="calendarColumns === 2"
                        @click="setCalendarColumns(1)"
                        class="bg-white border border-gray-300 rounded-full bg-[#f5f5f5] px-6 py-2 text-1xl font-medium hover:bg-gray-50 shadow-sm"
                        title="Switch to 1 column"
                    >
                        1x
                    </button>
                </div>
                <div class="w-full h-full overflow-x-hidden md:pb-20">
                    <!-- Admin controls -->
                    <div v-if="isAdmin" class="flex items-center justify-center gap-4 py-8">
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
                        :preview-date="previewDate"
                        :min-date="minDate"
                        inline
                        auto-apply
                        @update:model-value="onDateSelect"
                        month-name-format="long"
                        hide-offset-dates
                        :month-change-on-scroll="false"
                        :config="{ noSwipe: true }"
                        week-start="0"
                        :class="{ 'row-layout': shouldUseRowLayout }"
                    />
                    
                    <!-- Load More Button - hide when regular users reach 6 months or admins reach 12 months -->
                    <div v-if="(displayedMonths < 6) || (isAdmin && displayedMonths < 12)" class="w-full flex justify-center my-8 md:mb-52">
                        <button 
                            @click="loadMoreMonths"
                            class="text-black underline font-semibold hover:text-gray-600"
                        >
                            Show more months
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar container - move to right on desktop -->
            <div class="w-full flex flex-col bg-white mt-6 overflow-hidden overflow-y-auto" :class="[
                {'md:w-1/4': shouldUseRowLayout},
                {'md:mt-0': shouldUseRowLayout || layoutMode === 'edit-desktop-sidebar'},
                {'md:min-h-[calc(100vh-27rem)]': shouldUseRowLayout}
            ]">
                <div class="h-full flex flex-col justify-between">
                    
                    <div class="md:mt-8">
                        <div class="lg:px-8 relative flex flex-col gap-8 md:gap-4">

                            <!-- Select Dates header when no dates selected -->
                            <div v-if="!selectedDatesCount" class="px-8 md:px-0 pb-[1.9rem]">
                                <h2 class="text-black">Select Dates</h2>
                            </div>

                            <!-- Header with count and clear button -->
                            <div v-else class="flex justify-end items-center px-8 md:px-0 w-full gap-3">
                                <div class="bg-black text-white rounded-full px-6 py-3">
                                    <span class="text-4xl font-medium">{{ selectedDatesCount }} {{ selectedDatesCount === 1 ? 'night' : 'nights' }}</span>
                                </div>
                                <button 
                                    @click="clearAllDates" 
                                    class="bg-black text-white rounded-full w-20 h-20 flex items-center justify-center hover:bg-gray-800 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>

                            <!-- Validation error -->
                            <p v-if="$v.selectedDates.$error" class="text-red-500 mb-2 p-8">
                                Please select at least one date
                            </p>
                            <!-- Select Dates header when no dates selected -->

                            <div v-if="promptVisible" 
                                 class="p-4 rounded-2xl relative bg-black text-white border-black border hover:bg-neutral-800 transition-colors">
                                <div class="cursor-pointer" @click="handlePromptYes">
                                    <p class="text-white">{{ promptMessage }}</p>
                                </div>
                            </div>

                            
                            <div v-if="!promptVisible" class="h-[4.5rem] hidden md:block">
                            </div>
                             <div class="flex flex-col">
                                <textarea 
                                    name="Show times" 
                                    class="text-2.5xl md:text-2xl font-normal border rounded-2xl p-4 w-full" 
                                    :class="{ 
                                        'border-red-500 focus:shadow-focus-error': showTimesError,
                                        'border-neutral-300 focus:border-[#222222] focus:shadow-focus-black': !showTimesError
                                    }"
                                    v-model="event.show_times" 
                                    placeholder="Please provide a brief description of your show times, e.g., doors open at 7 PM, show starts at 8 PM"
                                    :rows="textareaRows"
                                    style="white-space: pre-wrap;"
                                ></textarea>
                                <p v-if="showTimesError" 
                                   class="text-red-500 text-1xl mt-2 px-4">
                                    Too many characters
                                </p>
                            </div>
                            <div class="flex w-full border p-4 rounded-2xl border-neutral-300 hover:border-[#222222] hover:bg-neutral-50 hover:shadow-focus-black transition-all duration-200">
                                <p class="text-lg">Timezone: </p>
                                <select id="timezone" 
                                        v-model="localTimezone" 
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
                            <div v-if="showEmbargoModal" class="c-embargo fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[1003] p-4 overflow-y-auto">
                                <div class="bg-white rounded-2xl w-[600px] mx-4 max-h-[90vh] my-auto flex flex-col">
                                    <div class="flex items-center justify-between p-8 pb-6 border-b border-gray-200">
                                        <h3 class="text-2.5xl font-bold">Select Embargo Date</h3>
                                        <button 
                                            @click="cancelEmbargoDate"
                                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
                                        >
                                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <div class="flex-1 overflow-y-auto p-8 pt-6 pb-0">
                                        <div class="max-h-[400px] overflow-y-auto">
                                            <VueDatePicker
                                                v-model="tempEmbargoDate"
                                                :enable-time-picker="false"
                                                :dark="isDark"
                                                :timezone="localTimezone"
                                                :start-date="new Date()"
                                                focus-start-date
                                                :multi-calendars="6"
                                                multi-calendars-solo
                                                inline
                                                auto-apply
                                                month-name-format="long"
                                                hide-offset-dates
                                                :month-change-on-scroll="false"
                                                week-start="0"
                                                @update:model-value="selectEmbargoDate"
                                                class="embargo-calendar modal-calendar"
                                            />
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-end gap-4 p-8 pt-6 border-t border-gray-200 bg-white rounded-b-2xl">
                                        <button 
                                            @click="cancelEmbargoDate"
                                            class="px-6 py-2 border rounded-lg hover:bg-gray-100"
                                        >
                                            Cancel
                                        </button>
                                        <button 
                                            @click="confirmEmbargoDate"
                                            :class="[
                                                'px-6 py-2 rounded-lg transition-colors',
                                                tempEmbargoDate 
                                                    ? 'bg-black text-white hover:bg-gray-800' 
                                                    : 'border border-gray-300 text-gray-500 cursor-not-allowed'
                                            ]"
                                            :disabled="!tempEmbargoDate"
                                        >
                                            Set Embargo Date
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, inject, watch, onMounted, onUnmounted } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';
import moment from 'moment-timezone';
import { 
    normalizeDateToTimezone,
    createDateAtNoon,
    formatDateForAPI,
    parseDateString
} from '@/composables/dateUtils';

// Props and emits
const props = defineProps({
    selectedTimezone: {
        type: String,
        default: 'America/New_York'
    },
    parentContainerWidth: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['dates-updated', 'back-to-selection', 'timezone-changed']);

// Injected dependencies
const event = inject('event');
const errors = inject('errors');
const user = inject('user');
const isSidebarCollapsed = inject('isSidebarCollapsed', ref(true));

// Calendar state
const date = ref([]);
const selectedDates = ref([]);
const calendarColumns = ref(1); // Will be set from cookie in onMounted
const windowWidth = ref(0);
const displayedMonths = ref(3);
const previewDate = ref(new Date());
const calendarRef = ref(null);
const timezones = ref([]);
const localTimezone = ref(props.selectedTimezone);

// Prompt state
const promptVisible = ref(false);
const promptMessage = ref('');
const promptAction = ref(null);
const selectedDate = ref(null);

// Embargo state
const showEmbargoModal = ref(false);
const tempEmbargoDate = ref(null);
const embargoToggle = ref(false);

// UI state for header
const hoveredLocation = ref(null);

// UI state
const isDark = ref(false);

// Computed properties
const isDesktop = computed(() => windowWidth.value >= 768);
const initialMonthsToShow = computed(() => isDesktop.value ? 6 : 3);
const isAdmin = computed(() => user && (user.isAdmin || false));
const selectedDatesCount = computed(() => selectedDates.value.length);
const selectedTimezone = computed(() => props.selectedTimezone);

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

const textareaRows = computed(() => {
    return windowWidth.value >= 768 ? 6 : 4;
});

const isEditMode = computed(() => {
    return event.status === 'e' || event.status === 'p';
});

// Layout mode: explicit state for clarity
const layoutMode = computed(() => {
    if (!isDesktop.value) return 'mobile';
    if (!isEditMode.value) return 'creation-desktop';
    if (!isSidebarCollapsed.value) return 'edit-desktop-sidebar';
    return 'edit-desktop-collapsed';
});

// Clear boolean flags for UI elements
const showTextButton = computed(() => {
    return layoutMode.value === 'mobile' || layoutMode.value.startsWith('edit-desktop');
});

const showIconButton = computed(() => {
    return layoutMode.value === 'creation-desktop';
});

const shouldUseRowLayout = computed(() => {
    // Use row layout in creation desktop mode
    if (layoutMode.value === 'creation-desktop') return true;
    
    // In edit mode with sidebar collapsed, check parent container width
    if (layoutMode.value === 'edit-desktop-collapsed') {
        return props.parentContainerWidth && props.parentContainerWidth > 1230;
    }
    
    // Never use row layout when sidebar is visible in edit mode
    return false;
});

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

const showTimesError = computed(() => {
    return event.show_times && event.show_times.length > 500;
});

// Validation rules
const rules = {
    selectedDates: {
        required: (value) => value.length > 0
    }
};

const $v = useVuelidate(rules, { selectedDates });

// Methods
const handleResize = () => {
    windowWidth.value = window?.innerWidth ?? 0;
    
    // Update displayedMonths based on screen size
    // Only update if we're not already showing more than default months
    if (displayedMonths.value <= (windowWidth.value >= 768 ? 6 : 3)) {
        displayedMonths.value = windowWidth.value >= 768 ? 6 : 3;
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

const onDateSelect = (dates) => {
    // Normalize all dates to YYYY-MM-DD format in the selected timezone
    const newSelectedDates = dates.map(d => normalizeDateToTimezone(d, selectedTimezone.value));
    const previousSelectedDates = [...selectedDates.value]; // Create a copy for comparison

    // Only find truly new or removed dates by comparing the normalized date strings
    const addedDate = newSelectedDates.find(d => !previousSelectedDates.includes(d));
    const removedDate = previousSelectedDates.find(d => !newSelectedDates.includes(d));

    // Update the internal state
    date.value = dates;
    selectedDates.value = newSelectedDates;
    
    promptVisible.value = false;

    // Early return if no actual changes (this prevents timezone-triggered false changes)
    if (!addedDate && !removedDate) {
        return;
    }

    if (dates.length < date.value.length) {
        return;
    }

    if (addedDate || removedDate) {
        const dateToCheck = addedDate || removedDate;
        // Parse date in the selected timezone
        const dateObj = parseDateString(dateToCheck, selectedTimezone.value);
        const weekday = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long',
            timeZone: selectedTimezone.value 
        });
        const futureDatesExist = checkFutureDates(dateToCheck);

        if (addedDate) {
            showPrompt('selectWeekly', `Repeat future ${weekday}s`, dateToCheck);
        } else if (removedDate && futureDatesExist) {
            showPrompt('removeFuture', `Remove future ${weekday}s?`, dateToCheck);
        }
    }

    // Emit the updated dates to parent
    emit('dates-updated', selectedDates.value);
};

// Weekly events management
const createWeeklyEvents = (startDateStr) => {
    const timezone = localTimezone.value;
    const startDate = moment.tz(startDateStr, timezone).hour(12);
    const targetDay = startDate.day();
    const currentDate = moment().tz(timezone);
    
    // For admins, use the full range of displayed months; for regular users, stick to 6 months
    const maxMonths = isAdmin.value ? Math.max(displayedMonths.value, 6) : 6;
    const maxDateRange = moment().tz(timezone).add(maxMonths, 'months').endOf('day');
    
    const newDates = [...date.value];
    
    // Start from the selected date
    let nextDate = startDate.clone();
    
    // Keep adding weekly occurrences until we reach the maximum range
    while (nextDate.isSameOrBefore(maxDateRange)) {
        const dateStr = nextDate.format('YYYY-MM-DD');
        const dateObj = createDateAtNoon(dateStr, timezone);
        
        // Only add the date if it's not already selected
        if (!selectedDates.value.includes(dateStr)) {
            newDates.push(dateObj);
        }
        
        // Move to next week
        nextDate = nextDate.clone().add(1, 'weeks');
    }
    
    date.value = newDates;
    selectedDates.value = newDates.map(d => normalizeDateToTimezone(d, timezone));

    // Emit the updated dates to parent
    emit('dates-updated', selectedDates.value);
};

const removeWeeklyEvents = (startDateStr) => {
    const timezone = localTimezone.value;
    const startDate = moment.tz(startDateStr, timezone).hour(12);
    const startDay = startDate.day();
    
    // For admins, use the full range of displayed months; for regular users, stick to 6 months
    const maxMonths = isAdmin.value ? Math.max(displayedMonths.value, 6) : 6;
    const maxDateRange = moment().tz(timezone).add(maxMonths, 'months').endOf('day');

    // Keep dates that are either:
    // 1. Not on the same day of week as the selected date, or
    // 2. Not after the selected date, or
    // 3. Beyond the maximum date range
    const newDates = date.value.filter(d => {
        const dateStr = normalizeDateToTimezone(d, timezone);
        const dateObj = moment.tz(dateStr, timezone).hour(12);
        return dateObj.day() !== startDay || 
               !dateObj.isAfter(startDate) || 
               dateObj.isAfter(maxDateRange);
    });

    date.value = newDates;
    selectedDates.value = newDates.map(d => normalizeDateToTimezone(d, timezone));

    // Emit the updated dates to parent
    emit('dates-updated', selectedDates.value);
};

// Prompt handling
const showPrompt = (action, message, date) => {
    promptVisible.value = true;
    promptMessage.value = message;
    promptAction.value = action;
    selectedDate.value = date;
};

const handlePromptYes = () => {
    if (promptAction.value === 'selectWeekly') {
        createWeeklyEvents(selectedDate.value);
    } else if (promptAction.value === 'removeFuture') {
        removeWeeklyEvents(selectedDate.value);
    }
    promptVisible.value = false;
    promptAction.value = null;
    selectedDate.value = null;
};

// Embargo date methods
const handleEmbargoToggleChange = (value) => {
    if (value) {
        // Toggle set to "Yes" - show calendar
        showEmbargoCalendar();
    } else {
        // Toggle set to "No" - clear embargo date
        event.embargo_date = null;
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
        date.setUTCHours(12, 0, 0, 0);
        event.embargo_date = date.toISOString().slice(0, 19).replace('T', ' ');
        embargoToggle.value = true; // Ensure toggle is set to Yes
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

// Calendar layout toggle
const setCalendarColumns = (columns) => {
    calendarColumns.value = columns;
    // Save preference to cookie
    document.cookie = `calendarColumns=${columns}; path=/; max-age=${60 * 60 * 24 * 365}`; // 1 year
    // Update the CSS dynamically
    updateCalendarColumnsCSS();
};

// Get calendar columns preference from cookie
const getCalendarColumnsFromCookie = () => {
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'calendarColumns') {
            return parseInt(value) || 1;
        }
    }
    return 1; // Default to 1 column if no cookie found
};

const updateCalendarColumnsCSS = () => {
    // Find or create the style element for calendar columns
    let styleElement = document.getElementById('calendar-columns-style');
    if (!styleElement) {
        styleElement = document.createElement('style');
        styleElement.id = 'calendar-columns-style';
        document.head.appendChild(styleElement);
    }
    
    // Different styles for 1x vs 2x layout on desktop
    // On mobile, always center the numbers regardless of the setting
    const cellInnerStyles = calendarColumns.value === 1 ? `
        @media (min-width: 768px) {
            .dp__cell_inner {
                align-items: start !important;
                justify-content: left !important;
                padding-top: 16% !important;
                padding-left: 16% !important;
                font-size: 1.6rem !important;
            }
        }
        @media (max-width: 767px) {
            .dp__cell_inner {
                align-items: center !important;
                justify-content: center !important;
                padding-top: 0% !important;
                padding-left: 0% !important;
                font-size: 1.6rem !important;
            }
        }
    ` : `
        .dp__cell_inner {
            align-items: center !important;
            justify-content: center !important;
            padding-top: 0% !important;
            padding-left: 0% !important;
            font-size: 1.6rem !important;
        }
    `;
    
    const cssRule = `
        .row-layout .dp__menu_inner {
            grid-template-columns: repeat(${calendarColumns.value}, 1fr) !important;
        }
        
        ${cellInnerStyles}
    `;
    
    styleElement.textContent = cssRule;
};

// Calendar navigation
const loadMoreMonths = () => {
    // For non-admins, cap at 6 months
    // For admins, cap at 12 months maximum
    if (!isAdmin.value) {
        displayedMonths.value = 6;
    } else {
        // For admins: if less than 6, go to 6; if 6-8, go to 9; if 9-11, go to 12; if 12+, stay at 12
        if (displayedMonths.value < 6) {
            displayedMonths.value = 6;
        } else if (displayedMonths.value < 9) {
            displayedMonths.value = 9;
        } else if (displayedMonths.value < 12) {
            displayedMonths.value = 12;
        }
        // If already at 12 or more, do nothing (stay capped at 12)
    }
    
    // Update the year labels after component re-renders
    setTimeout(() => {
        updateMonthYearElements();
    }, 200);
};

const showPreviousMonths = () => {
    // Get current date
    const currentDate = new Date(previewDate.value);
    
    // Calculate the new date (2 months back instead of 3)
    const newMonth = currentDate.getMonth() - 2;
    const newYear = currentDate.getFullYear() + Math.floor(newMonth / 12);
    const adjustedMonth = ((newMonth % 12) + 12) % 12; // Handle negative months
    
    // Update previewDate
    currentDate.setMonth(currentDate.getMonth() - 2);
    previewDate.value = currentDate;
    
    // Use the DatePicker's API method to change month programmatically
    if (calendarRef.value) {
        // Set month and year using component's method
        calendarRef.value.setMonthYear({ 
            month: adjustedMonth, 
            year: newYear 
        });
        
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

// Initialize timezones
const initializeTimezones = () => {
    timezones.value = moment.tz.names().map(name => ({ name }));
};

// Watch for timezone changes and emit to parent
watch(localTimezone, (newTimezone) => {
    emit('timezone-changed', newTimezone);
});

// Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { dates: ['Please select at least one date'] };
        }
        
        return isValid;
    },
    
    getDates: () => {
        return selectedDates.value;
    },
    
    setDates: (dates) => {
        if (dates && dates.length > 0) {
            const showDates = dates.map(dateStr => {
                return createDateAtNoon(dateStr, selectedTimezone.value);
            });
            
            date.value = showDates;
            selectedDates.value = showDates.map(d => 
                normalizeDateToTimezone(d, selectedTimezone.value)
            );
            
            // Emit the updated dates to parent so state is synced
            emit('dates-updated', selectedDates.value);
        }
    }
});

// Lifecycle hooks
onMounted(() => {
    initializeTimezones();
    
    // Initialize embargo toggle based on existing embargo date
    embargoToggle.value = !!event.embargo_date;
    
    windowWidth.value = window?.innerWidth ?? 0;
    displayedMonths.value = initialMonthsToShow.value;
    window?.addEventListener('resize', handleResize);
    
    // Load calendar columns preference from cookie
    calendarColumns.value = getCalendarColumnsFromCookie();
    
    // Initialize calendar columns CSS
    updateCalendarColumnsCSS();
    
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

// Clear all selected dates method
const clearAllDates = () => {
    date.value = [];
    selectedDates.value = [];
    promptVisible.value = false;
    emit('dates-updated', []);
};
</script>

<style>
/* Include all the existing calendar styles from dates.vue */
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
   font-size: 3.1rem;
   font-weight: 400;
   display: flex;
   justify-content: flex-start !important;
   align-items: center;
   padding:0 0 1rem 0;
   
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
}

/* Calendar header (days of week) */
.dp__calendar_header {
   color: #666;
   font-weight: normal;
   font-size: 1.2rem;
}

.dp__calendar_row {
   margin: 0 !important;
   gap: 0 !important;
}

/* Calendar item with border solution and square aspect ratio */
.dp__calendar_item {
   margin: 0.35rem !important;
   padding: 0 !important;
   font-size: 1.4rem;
   display: flex;
   justify-content: center;
   position: relative;
   width: calc(100% / 7) !important;
   border: 1px solid #cfcfcf;
   border-radius: 2rem;
}

.dp__calendar_item:has(.dp__active_date) {
    border: 1px solid #222222 !important;
}
.dp__calendar_item:has(.dp--past) {
    background-color: #f5f5f5 !important;
}
.dp__calendar_item:not(:has(.dp__pointer)) {
    border: none !important;
}

/* Remove redundant borders */
/* .dp__calendar_item:first-child {
   border-left: none;
}

.dp__calendar_row:first-child .dp__calendar_item {
   border-top: none;
} */

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
    align-items: start;
    justify-content: left;
    font-weight: normal !important;
    color: #222222 !important;
    border-radius: 1.75rem !important;
    padding-top: 16% !important;
    padding-left: 16% !important;
    font-size: 1.6rem;
}


.dp__cell_disabled {
   opacity: 0.3;
   cursor: auto !important;
}

/* Hover state */
.dp__cell_inner.dp__pointer:not(.dp--past):hover {
   box-shadow: 0 0 0 1.5px black !important;
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
   position: sticky !important;
   top: 0 !important;
   z-index: 1000 !important;
   background-color: white !important;
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
    padding: 0 !important;
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
        position: relative !important;
    }
    
    /* Override the default display for the calendars container to create grid */
    .dp__menu_inner {
        display: grid !important;
        grid-template-columns: repeat(1, 1fr) !important;
        gap: 20px !important;
        width: 100% !important;
        padding: 1rem !important;
    }
    
    /* Calendar columns are now managed dynamically by JavaScript */
    
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

/* Custom days of week header for modal calendars */
.dp--header-wrap::after {
    content: '';
    display: block;
    position: absolute;
    bottom: -40px;
    left: 0;
    right: 0;
    height: 40px;
    background: white;
    border-bottom: 1px solid #e5e5e5;
    z-index: 1001;
}

.dp--header-wrap::before {
    content: 'Sun       Mon       Tue       Wed       Thu       Fri       Sat';
    display: block;
    position: absolute;
    bottom: -40px;
    left: 0rem;
    right: 0.6rem;
    height: 40px;
    background: white;
    font-size: 1.4rem;
    color: #666;
    font-weight: normal;
    z-index: 1002;
    text-align: justify;
    line-height: 40px;
    text-align-last: justify;
    white-space: pre;
    padding: 0 calc(50% / 7 / 2);
    box-sizing: border-box;
}

/* Hide the original calendar headers since we have our custom one */
.dp__calendar_header {
    display: none !important;
}

/* Add top padding to calendar content to account for fixed header */
.dp__calendar {
    padding-top: 17px;
}

</style>
