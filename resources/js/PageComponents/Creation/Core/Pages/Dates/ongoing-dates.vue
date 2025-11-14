<template>
    <div class="w-full">
        <!-- Create a flex container for desktop layout -->
        <div class="w-full flex flex-col gap-6 md:mt-[-1rem] md:mt-0" :class="[
            {'mt-[-3.5rem]': layoutMode === 'creation-desktop'},
            shouldUseMobileLayout ? '' : 'md:flex-row'
        ]">
            <!-- Icon button column (creation mode only) -->
            <div v-if="showIconButton" class="hidden md:block w-48 px-8 mt-12">
                <div class="h-52 mt-12 w-full items-center justify-center flex">
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
            
            <!-- Main content area - take most of the width on desktop -->
            <div class="flex-grow overflow-hidden relative w-full bg-white">
                <!-- Text button (mobile or edit mode) -->
                <div v-if="showTextButton" 
                     class="mb-6"
                     :class="{'md:hidden': layoutMode === 'creation-desktop'}">
                    <button 
                        @click="$emit('back-to-selection')"
                        class="text-xl text-left text-gray-500 hover:text-black underline whitespace-nowrap w-full"
                    >
                        Change Date Type
                    </button>
                </div>
                
                <!-- Select Dates header when no dates selected -->
                <div v-if="!selectedDatesCount" class="pb-4">
                    <h2 class="text-black">Select Dates</h2>
                </div>

                <!-- Header with count -->
                <div v-else class="flex justify-between items-end pb-4">
                    <h2 class="text-black">{{ selectedDatesCount }} {{ selectedDatesCount === 1 ? 'Night' : 'Nights' }}</h2>
                </div>

                <!-- Validation error -->
                <p v-if="$v.selectedDays.$error" class="text-red-500 mb-2 p-8">
                    Please select at least one day of the week
                </p>
                <div class="flex flex-col gap-8 px-2" :class="shouldUseMobileLayout ? '' : 'md:flex-row'">
                    <!-- Left section - Days selection only -->
                    <div class="flex-grow">
                        <div class="mt-12">
                            <!-- Mobile: Horizontal scroll -->
                            <div class="md:hidden overflow-x-auto overflow-y-hidden scrollbar-hide py-2">
                                <div class="flex gap-4 px-1" style="scroll-snap-type: x mandatory;">
                                    <button
                                        v-for="(day, index) in daysOfWeek"
                                        :key="day.short"
                                        @click="toggleDay(index)"
                                        :class="[
                                            'flex-shrink-0 w-[22vw] h-[22vw] snap-start rounded-3xl text-sm font-medium transition-all duration-200 border flex items-start justify-start p-4',
                                            selectedDays.includes(index) 
                                                ? 'bg-black text-white border-black border-2' 
                                                : 'bg-white text-gray-700 border-neutral-300 hover:shadow-focus-black hover:border-[#222222] hover:bg-neutral-50'
                                        ]"
                                    >
                                        <div class="text-2xl mt-1">{{ day.short }}</div>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Desktop: Grid layout (7 columns) -->
                            <div class="hidden md:grid md:grid-cols-7 gap-4 px-1">
                                <button
                                    v-for="(day, index) in daysOfWeek"
                                    :key="day.short"
                                    @click="toggleDay(index)"
                                    :class="[
                                        'aspect-square p-[16%] rounded-3xl text-sm font-medium transition-all duration-200 border flex items-start justify-start',
                                        selectedDays.includes(index) 
                                            ? 'bg-black text-white border-black border-2' 
                                            : 'bg-white text-gray-700 border-neutral-300 hover:shadow-focus-black hover:border-[#222222] hover:bg-neutral-50'
                                    ]"
                                >
                                    <div class="text-2xl mt-1">{{ day.short }}</div>
                                </button>
                            </div>
                            
                            <p v-if="$v.selectedDays.$error" class="text-red-500 text-sm mt-2">
                                Please select at least one day of the week
                            </p>
                        </div>
                        <div class="flex flex-col gap-4 mt-8">
                            <div class="border border-neutral-300 rounded-2xl p-8">
                                <h3 class="text-2xl font-bold mb-2">Start Date</h3>
                                <div class="flex items-center justify-between">
                                    <button 
                                        @click="openStartDateModal"
                                        class="hover:border-[#222222] text-gray-500 hover:bg-neutral-50 transition-all duration-200 underline"
                                        :disabled="selectedDays.length === 0"
                                    >
                                        <span v-if="customStartDate">{{ formatDate(customStartDate) }}</span>
                                        <span v-else-if="calculatedStartDate">{{ formatDate(calculatedStartDate) }}</span>
                                        <span v-else class="text-gray-400">Select start date</span>
                                    </button>
                                </div>
                            </div>

                            <!-- End Date Selection -->
                            <div class="border border-neutral-300 rounded-2xl p-8">
                                <h3 class="text-2xl font-bold mb-2">Last Date</h3>
                                <button 
                                    @click="openLastDateModal"
                                    class="hover:border-[#222222] text-gray-500 hover:bg-neutral-50 transition-all duration-200 underline"
                                >
                                    <span v-if="endDate">{{ formatDate(endDate) }}</span>
                                    <span v-else>{{ formatDate((() => {
                                        const sixMonthsFromNow = new Date();
                                        sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
                                        return sixMonthsFromNow;
                                    })()) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right column - All configuration options (1/4 width) -->
                    <div class="w-full flex flex-col gap-8" :class="shouldUseMobileLayout ? '' : 'md:w-1/3 md:mt-12 md:mt-12 lg:px-8'">
                        <!-- Start Date Selection -->
                        

                        <!-- Show Times -->
                        
                        <textarea 
                            name="Show times" 
                            class="text-2.5xl md:text-1xl border rounded-2xl p-4 w-full" 
                            :class="{ 
                                'border-red-500 focus:shadow-focus-error': showTimesError,
                                'border-neutral-300 focus:border-[#222222] focus:shadow-focus-black': !showTimesError
                            }"
                            v-model="event.show_times" 
                            placeholder="Please provide a brief description of your show times, e.g., doors open at 7 PM, show starts at 8 PM"
                            rows="4"
                            style="white-space: pre-wrap;"
                        ></textarea>
                        <p v-if="showTimesError" class="text-red-500 text-sm mt-2">
                            Too many characters (max 500)
                        </p>

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

                        <!-- Embargo Date Section -->
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
                                    :dark="false"
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

                <!-- Start Date Modal -->
                <div v-if="showStartDateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[500] p-4 overflow-y-auto">
                    <div class="bg-white rounded-2xl w-[600px] mx-4 max-h-[90vh] my-auto flex flex-col">
                        <div class="flex items-center justify-between p-8 pb-6 border-b border-gray-200">
                            <h3 class="text-2xl font-bold">Select Start Date</h3>
                            <button 
                                @click="cancelStartDate"
                                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
                            >
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-8 pt-6 pb-0">
                            <!-- Show previous months button - hide when we reach 12 months before today -->
                            <div v-if="canShowMorePreviousMonths" class="flex items-center justify-center gap-4 pb-4">
                                <button 
                                    @click="showPreviousMonthsStartModal"
                                    class="text-black underline font-semibold hover:text-gray-600"
                                >
                                    Show previous months
                                </button>
                            </div>
                            
                            <div class="max-h-[400px] overflow-y-auto">
                                <VueDatePicker
                                    ref="startDateCalendarRef"
                                    v-model="tempStartDate"
                                    :enable-time-picker="false"
                                    :dark="false"
                                    :timezone="selectedTimezone"
                                    :preview-date="previewDateStartModal"
                                    :multi-calendars="displayedMonthsStartModal"
                                    multi-calendars-solo
                                    inline
                                    auto-apply
                                    month-name-format="long"
                                    hide-offset-dates
                                    :month-change-on-scroll="false"
                                    week-start="0"
                                    @update:model-value="selectStartDate"
                                    class="start-date-calendar modal-calendar"
                                />
                            </div>
                            
                            <!-- Load More Button - hide when we reach 12 months ahead of today -->
                            <div v-if="canShowMoreFutureMonths" class="w-full flex justify-center py-4">
                                <button 
                                    @click="loadMoreMonthsStartModal"
                                    class="text-black underline font-semibold hover:text-gray-600"
                                >
                                    Show more months
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-4 p-8 pt-6 border-t border-gray-200 bg-white rounded-b-2xl">
                            <button 
                                @click="cancelStartDate"
                                class="px-6 py-2 border rounded-lg hover:bg-gray-100"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="confirmStartDate"
                                :class="[
                                    'px-6 py-2 rounded-lg transition-colors',
                                    tempStartDate 
                                        ? 'bg-black text-white hover:bg-gray-800' 
                                        : 'border border-gray-300 text-gray-500 cursor-not-allowed'
                                ]"
                                :disabled="!tempStartDate"
                            >
                                Set Start Date
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Last Date Options Modal -->
                <div v-if="showLastDateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[500] p-4 overflow-y-auto">
                    <div class="bg-white rounded-2xl w-[600px] mx-4 max-h-[90vh] my-auto flex flex-col">
                        <div class="flex items-center justify-between p-8 pb-6 border-b border-gray-200">
                            <h3 class="text-2xl font-bold">Choose End Date</h3>
                            <button 
                                @click="showLastDateModal = false"
                                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
                            >
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-8 pt-6 pb-0">
                            <!-- Show previous months button - hide when we reach 12 months before today -->
                            <div v-if="canShowMorePreviousMonthsEnd" class="flex items-center justify-center gap-4 pb-4">
                                <button 
                                    @click="showPreviousMonthsEndModal"
                                    class="text-black underline font-semibold hover:text-gray-600"
                                >
                                    Show previous months
                                </button>
                            </div>
                            
                            <div class="max-h-[400px] overflow-y-auto">
                                <VueDatePicker
                                    ref="endDateCalendarRef"
                                    v-model="tempEndDate"
                                    :enable-time-picker="false"
                                    :dark="false"
                                    :timezone="selectedTimezone"
                                    :preview-date="previewDateEndModal"
                                    :multi-calendars="displayedMonthsEndModal"
                                    multi-calendars-solo
                                    :min-date="effectiveStartDate || new Date()"
                                    inline
                                    auto-apply
                                    month-name-format="long"
                                    hide-offset-dates
                                    :month-change-on-scroll="false"
                                    week-start="0"
                                    @update:model-value="selectEndDate"
                                    class="end-date-calendar modal-calendar"
                                />
                            </div>
                            
                            <!-- Load More Button - hide when we reach 12 months ahead of today -->
                            <div v-if="canShowMoreFutureMonthsEnd" class="w-full flex justify-center py-4">
                                <button 
                                    @click="loadMoreMonthsEndModal"
                                    class="text-black underline font-semibold hover:text-gray-600"
                                >
                                    Show more months
                                </button>
                            </div>
                        </div>
                        
                        <!-- Calendar action buttons -->
                        <div class="flex justify-between gap-4 p-8 pt-6 border-t border-gray-200 bg-white rounded-b-2xl">
                            <button 
                                @click="extendToSixMonths(); showLastDateModal = false"
                                class="px-6 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-all duration-200"
                            >
                                Extend to 6 months
                            </button>
                            <div class="flex gap-4">
                                <button 
                                    @click="showLastDateModal = false"
                                    class="px-6 py-2 border rounded-lg hover:bg-gray-100"
                                >
                                    Cancel
                                </button>
                                <button 
                                    @click="confirmEndDate(); showLastDateModal = false"
                                    :class="[
                                        'px-6 py-2 rounded-lg transition-colors',
                                        tempEndDate 
                                            ? 'bg-black text-white hover:bg-gray-800' 
                                            : 'border border-gray-300 text-gray-500 cursor-not-allowed'
                                    ]"
                                    :disabled="!tempEndDate"
                                >
                                    Set End Date
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>                    
        </div>
    </div>
</template>

<script setup>
import { ref, computed, inject, watch, onMounted } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { required } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';
import moment from 'moment-timezone';
import { 
    generateRecurringDates,
    formatDateForDisplay,
    addMonths,
    parseDateString,
    createDateAtNoon,
    getBrowserTimezone,
    daysBetween
} from '@/composables/dateUtils';

// Props and emits
const props = defineProps({
    selectedTimezone: {
        type: String,
        default: 'America/New_York'
    }
});

const emit = defineEmits(['dates-generated', 'back-to-selection', 'timezone-changed']);

// Injected dependencies
const event = inject('event');
const errors = inject('errors');
const selectedTimezone = computed(() => props.selectedTimezone);
const isSidebarCollapsed = inject('isSidebarCollapsed', ref(true));

// Days of the week configuration
const daysOfWeek = [
    { short: 'Sun', full: 'Sunday' },
    { short: 'Mon', full: 'Monday' },
    { short: 'Tue', full: 'Tuesday' },
    { short: 'Wed', full: 'Wednesday' },
    { short: 'Thu', full: 'Thursday' },
    { short: 'Fri', full: 'Friday' },
    { short: 'Sat', full: 'Saturday' }
];

// Reactive state
const selectedDays = ref([]);
const customStartDate = ref(null);
const endDate = ref(null);
const isOngoing = ref(false); // Default to showing end date
const showStartDateModal = ref(false);
const showLastDateModal = ref(false);
const tempStartDate = ref(null);
const tempEndDate = ref(null);
const timezones = ref([]);
const localTimezone = ref(props.selectedTimezone);

// Start date modal calendar navigation
const displayedMonthsStartModal = ref(6);
const previewDateStartModal = ref(new Date());
const startDateCalendarRef = ref(null);

// End date modal calendar navigation
const displayedMonthsEndModal = ref(6);
const previewDateEndModal = ref(new Date());
const endDateCalendarRef = ref(null);

// Computed property to check if we can show more previous months
const canShowMorePreviousMonths = computed(() => {
    // Calculate how many months back from today we are
    const today = new Date();
    const previewDate = new Date(previewDateStartModal.value);
    const monthsDiff = (today.getFullYear() - previewDate.getFullYear()) * 12 + 
                       (today.getMonth() - previewDate.getMonth());
    
    // Allow going back up to 12 months from today
    return monthsDiff < 12;
});

// Computed property to check if we can show more future months
const canShowMoreFutureMonths = computed(() => {
    const today = new Date();
    
    // Calculate what the last visible month WOULD BE if we added 3 more months
    const futureLastVisibleMonth = new Date(previewDateStartModal.value);
    futureLastVisibleMonth.setMonth(futureLastVisibleMonth.getMonth() + displayedMonthsStartModal.value + 3 - 1);
    
    // Calculate months difference from today to that future last visible month
    const monthsFromToday = (futureLastVisibleMonth.getFullYear() - today.getFullYear()) * 12 + 
                            (futureLastVisibleMonth.getMonth() - today.getMonth());
    
    // Only show button if adding 3 more months wouldn't exceed 12 months ahead
    return monthsFromToday <= 12;
});

// Computed properties for end date modal navigation
const canShowMorePreviousMonthsEnd = computed(() => {
    const today = new Date();
    const previewDate = new Date(previewDateEndModal.value);
    const monthsDiff = (today.getFullYear() - previewDate.getFullYear()) * 12 + 
                       (today.getMonth() - previewDate.getMonth());
    return monthsDiff < 12;
});

const canShowMoreFutureMonthsEnd = computed(() => {
    const today = new Date();
    const futureLastVisibleMonth = new Date(previewDateEndModal.value);
    futureLastVisibleMonth.setMonth(futureLastVisibleMonth.getMonth() + displayedMonthsEndModal.value + 3 - 1);
    const monthsFromToday = (futureLastVisibleMonth.getFullYear() - today.getFullYear()) * 12 + 
                            (futureLastVisibleMonth.getMonth() - today.getMonth());
    return monthsFromToday <= 12;
});

// Embargo state
const showEmbargoModal = ref(false);
const tempEmbargoDate = ref(null);
const embargoToggle = ref(false);

// Check if we're in edit mode (vs creation mode)
const isEditMode = computed(() => {
    return event.status === 'e' || event.status === 'p';
});

// Layout mode: explicit state for clarity
const layoutMode = computed(() => {
    // Note: We don't have windowWidth in ongoing-dates, so we use CSS responsive classes for mobile
    if (!isEditMode.value) return 'creation-desktop';
    if (!isSidebarCollapsed.value) return 'edit-desktop-sidebar';
    return 'edit-desktop-collapsed';
});

// Clear boolean flags for UI elements
const showTextButton = computed(() => {
    // Always show text button (will be hidden on desktop creation via CSS)
    return true;
});

const showIconButton = computed(() => {
    return layoutMode.value === 'creation-desktop';
});

const shouldUseMobileLayout = computed(() => {
    // Use mobile-style stacked layout when sidebar is visible in edit mode
    return layoutMode.value === 'edit-desktop-sidebar';
});

// Show times validation
const showTimesError = computed(() => {
    return event.show_times && event.show_times.length > 500;
});

// Embargo computed properties
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

// Validation rules
const rules = {
    selectedDays: { 
        required: (value) => value.length > 0 
    }
};

const $v = useVuelidate(rules, { selectedDays });

// Computed properties
const calculatedStartDate = computed(() => {
    if (selectedDays.value.length === 0) return null;
    
    const timezone = selectedTimezone.value;
    const today = moment.tz(timezone);
    
    // Find the next occurrence of the first selected day
    const firstSelectedDay = Math.min(...selectedDays.value);
    
    let checkDate = today.clone();
    for (let i = 0; i < 7; i++) {
        if (checkDate.day() === firstSelectedDay) {
            // If it's today, start from next week to give time to set up
            if (i === 0) {
                checkDate.add(7, 'days');
            }
            return checkDate.toDate();
        }
        checkDate.add(1, 'day');
    }
    
    return today.add(7, 'days').toDate();
});

const effectiveStartDate = computed(() => {
    return customStartDate.value || calculatedStartDate.value;
});

// Generate date strings in YYYY-MM-DD format
const generatedDateStrings = computed(() => {
    if (selectedDays.value.length === 0 || !effectiveStartDate.value) {
        return [];
    }
    
    const timezone = selectedTimezone.value;
    
    // Use endDate if set, otherwise default to 6 months from start date
    const maxDate = endDate.value 
        ? endDate.value
        : addMonths(effectiveStartDate.value, 6, timezone);
    
    // Use the centralized date utility for generating recurring dates
    return generateRecurringDates(
        selectedDays.value,
        effectiveStartDate.value,
        maxDate,
        timezone
    );
});

// For internal display, convert to Date objects
const generatedDates = computed(() => {
    const timezone = selectedTimezone.value;
    return generatedDateStrings.value.map(dateStr => createDateAtNoon(dateStr, timezone));
});

const selectedDatesCount = computed(() => generatedDateStrings.value.length);

// Methods
const toggleDay = (dayIndex) => {
    const index = selectedDays.value.indexOf(dayIndex);
    if (index > -1) {
        selectedDays.value.splice(index, 1);
    } else {
        selectedDays.value.push(dayIndex);
    }
    selectedDays.value.sort();
};

const getSelectedDaysText = () => {
    if (selectedDays.value.length === 0) return '';
    if (selectedDays.value.length === 1) {
        return daysOfWeek[selectedDays.value[0]].full;
    }
    
    const dayNames = selectedDays.value.map(index => daysOfWeek[index].short);
    return dayNames.join(', ');
};

const formatDate = (date) => {
    if (!date) return '';
    return formatDateForDisplay(date, selectedTimezone.value);
};

const formatDateWithDay = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const extendToSixMonths = () => {
    endDate.value = addMonths(effectiveStartDate.value, 6, selectedTimezone.value);
};

// Start date modal methods
const openStartDateModal = () => {
    if (customStartDate.value) {
        const d = new Date(customStartDate.value);
        tempStartDate.value = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
        // Start preview from the selected date
        previewDateStartModal.value = new Date(d.getFullYear(), d.getMonth(), 1);
    } else {
        tempStartDate.value = null;
        // Start preview from today
        previewDateStartModal.value = new Date();
    }
    
    // Reset calendar to default view
    displayedMonthsStartModal.value = 6;
    
    showStartDateModal.value = true;
};

const showPreviousMonthsStartModal = () => {
    // Get current preview date
    const currentDate = new Date(previewDateStartModal.value);
    
    // Calculate the new date (2 months back)
    const newMonth = currentDate.getMonth() - 2;
    const newYear = currentDate.getFullYear() + Math.floor(newMonth / 12);
    const adjustedMonth = ((newMonth % 12) + 12) % 12; // Handle negative months
    
    // Update previewDate
    currentDate.setMonth(currentDate.getMonth() - 2);
    previewDateStartModal.value = currentDate;
    
    // Increase displayed months by 2
    displayedMonthsStartModal.value += 2;
    
    // Use the DatePicker's API method to change month programmatically
    if (startDateCalendarRef.value) {
        startDateCalendarRef.value.setMonthYear({ 
            month: adjustedMonth, 
            year: newYear 
        });
    }
};

const loadMoreMonthsStartModal = () => {
    // Increment by 3 months (button will hide when reaching 12 months from today)
    displayedMonthsStartModal.value += 3;
};

const selectStartDate = (selectedDate) => {
    tempStartDate.value = selectedDate;
};

const confirmStartDate = () => {
    if (tempStartDate.value) {
        customStartDate.value = createDateAtNoon(tempStartDate.value, selectedTimezone.value);
        showStartDateModal.value = false;
        tempStartDate.value = null;
    }
};

const cancelStartDate = () => {
    showStartDateModal.value = false;
    tempStartDate.value = null;
};

// End date modal methods
const openLastDateModal = () => {
    if (endDate.value) {
        const d = new Date(endDate.value);
        tempEndDate.value = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
        // Start preview from the selected end date
        previewDateEndModal.value = new Date(d.getFullYear(), d.getMonth(), 1);
    } else {
        tempEndDate.value = null;
        // Start preview from start date or today
        const startDate = effectiveStartDate.value || new Date();
        previewDateEndModal.value = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
    }
    
    // Reset calendar to default view
    displayedMonthsEndModal.value = 6;
    
    showLastDateModal.value = true;
};

const showPreviousMonthsEndModal = () => {
    // Get current preview date
    const currentDate = new Date(previewDateEndModal.value);
    
    // Calculate the new date (2 months back)
    const newMonth = currentDate.getMonth() - 2;
    const newYear = currentDate.getFullYear() + Math.floor(newMonth / 12);
    const adjustedMonth = ((newMonth % 12) + 12) % 12; // Handle negative months
    
    // Update previewDate
    currentDate.setMonth(currentDate.getMonth() - 2);
    previewDateEndModal.value = currentDate;
    
    // Increase displayed months by 2
    displayedMonthsEndModal.value += 2;
    
    // Use the DatePicker's API method to change month programmatically
    if (endDateCalendarRef.value) {
        endDateCalendarRef.value.setMonthYear({ 
            month: adjustedMonth, 
            year: newYear 
        });
    }
};

const loadMoreMonthsEndModal = () => {
    // Increment by 3 months (button will hide when reaching 12 months from today)
    displayedMonthsEndModal.value += 3;
};

const selectEndDate = (selectedDate) => {
    tempEndDate.value = selectedDate;
};

const confirmEndDate = () => {
    if (tempEndDate.value) {
        // Validate that end date is after start date
        const selectedEndDate = new Date(tempEndDate.value);
        const startDate = effectiveStartDate.value;
        
        if (startDate && selectedEndDate <= startDate) {
            alert('End date must be after the start date');
            return;
        }
        
        endDate.value = createDateAtNoon(tempEndDate.value, selectedTimezone.value);
        showLastDateModal.value = false;
        tempEndDate.value = null;
    }
};

const cancelEndDate = () => {
    showLastDateModal.value = false;
    tempEndDate.value = null;
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

// Watch for changes and emit generated dates as strings
watch(generatedDateStrings, (newDates) => {
    emit('dates-generated', newDates);
}, { deep: true });

// Watch for timezone changes and emit to parent
watch(localTimezone, (newTimezone) => {
    emit('timezone-changed', newTimezone);
});

// Initialize timezones
const initializeTimezones = () => {
    timezones.value = moment.tz.names().map(name => ({ name }));
};

onMounted(() => {
    initializeTimezones();
    
    // Initialize embargo toggle based on existing embargo date
    embargoToggle.value = !!event.embargo_date;
});

// Watch for changes to hasEmbargoDate to keep toggle in sync
watch(hasEmbargoDate, (newValue) => {
    embargoToggle.value = newValue;
});

// Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error && generatedDateStrings.value.length > 0;
        
        if (!isValid) {
            errors.value = { 
                dates: selectedDays.value.length === 0 
                    ? ['Please select at least one day of the week'] 
                    : ['Please configure your recurring dates properly']
            };
        }
        
        return isValid;
    },
    
    getDates: () => {
        // Return dates as YYYY-MM-DD strings
        return generatedDateStrings.value;
    },
    
    getConfiguration: () => {
        return {
            selectedDays: selectedDays.value,
            startDate: effectiveStartDate.value,
            endDate: endDate.value
        };
    },
    
    setConfiguration: (config) => {
        if (config.selectedDays) selectedDays.value = config.selectedDays;
        if (config.startDate) {
            customStartDate.value = createDateAtNoon(config.startDate, selectedTimezone.value);
        }
        if (config.endDate) {
            endDate.value = createDateAtNoon(config.endDate, selectedTimezone.value);
        }
    },
    
    reconstructFromDates: (dates, storedStartDate = null) => {
        if (!dates || dates.length === 0) return;
        
        // If we have a stored start date, use it directly
        if (storedStartDate) {
            customStartDate.value = new Date(storedStartDate);
        }
        
        // Analyze the dates to determine which days of the week were selected
        const dayFrequency = {};
        const parsedDates = dates.map(dateStr => {
            // Parse as YYYY-MM-DD in local timezone to avoid timezone shifts
            const [year, month, day] = dateStr.split('-');
            return new Date(year, month - 1, day, 12, 0, 0); // Set to noon to avoid timezone issues
        }).sort((a, b) => a - b);
        
        // Count occurrences of each day of the week
        parsedDates.forEach(date => {
            const dayOfWeek = date.getDay();
            dayFrequency[dayOfWeek] = (dayFrequency[dayOfWeek] || 0) + 1;
        });
        
        // Determine which days were selected
        // For ongoing events, if we have very few dates (like just starting), include all days
        // Otherwise, require multiple occurrences to confirm it's a weekly pattern
        const totalDates = parsedDates.length;
        const minOccurrences = totalDates <= 7 ? 1 : 2; // If 7 or fewer dates, any day counts
        
        const reconstructedDays = Object.keys(dayFrequency)
            .filter(day => dayFrequency[day] >= minOccurrences)
            .map(day => parseInt(day))
            .sort();
        
        if (reconstructedDays.length > 0) {
            selectedDays.value = reconstructedDays;
            
            // Set the start date to the earliest date (only if not already set)
            if (!customStartDate.value) {
                customStartDate.value = new Date(parsedDates[0]);
            }
            
            // Determine if it's ongoing based on the existing pattern
            // If we have a lot of dates extending far into the future, assume ongoing
            const lastDate = parsedDates[parsedDates.length - 1];
            const now = new Date();
            const sixMonthsFromNow = new Date();
            sixMonthsFromNow.setMonth(now.getMonth() + 6);
            
            // If the last date is within a month of 6 months from now, assume it's ongoing
            const oneMonthBeforeSixMonths = new Date(sixMonthsFromNow);
            oneMonthBeforeSixMonths.setMonth(oneMonthBeforeSixMonths.getMonth() - 1);
            
            // Set end date from event's closingDate if available (this preserves manually set end dates)
            if (event.closingDate) {
                endDate.value = new Date(event.closingDate);
            }
            // If no closingDate, endDate stays null and uses default 6-month calculation
            
            console.log('Reconstructed ongoing configuration:', {
                selectedDays: selectedDays.value,
                startDate: customStartDate.value,
                endDate: endDate.value,
                isOngoing: isOngoing.value,
                lastDate: lastDate,
                totalDates: parsedDates.length
            });
        }
    }
});
</script>

<style>
/* Calendar styling for modals */
.start-date-calendar .dp--arrow-btn-nav,
.end-date-calendar .dp--arrow-btn-nav {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.start-date-calendar .dp__arrow_btn,
.end-date-calendar .dp__arrow_btn {
    width: 40px;
    height: 40px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 50%;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}

.start-date-calendar .dp__arrow_btn:hover,
.end-date-calendar .dp__arrow_btn:hover {
    background-color: #f3f3f3;
}

.start-date-calendar .dp__inner_nav,
.end-date-calendar .dp__inner_nav {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.start-date-calendar .dp__arrow_btn_container,
.end-date-calendar .dp__arrow_btn_container {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.start-date-calendar .dp__arrow_btn svg,
.end-date-calendar .dp__arrow_btn svg {
    fill: #333 !important;
    width: 1rem !important;
    height: 1rem !important;
}

/* Modal calendar styling for vertical scrolling - only for end date modal */
.modal-calendar .dp__menu_inner {
    display: flex !important;
    flex-direction: column !important;
    gap: 1rem !important;
}

.modal-calendar .dp__calendar {
    width: 100% !important;
}

/* Ensure smooth scrolling in modal */
.modal-calendar {
    scroll-behavior: smooth;
}

.modal-calendar .dp__month_year_wrap {
    font-size: 2.5rem !important;
    padding: 0rem !important;
    gap: 0.5rem !important;
    align-items: center !important;
    margin-bottom: 0.5rem !important;
}

.modal-calendar .dp__month_year_wrap .dp__month_year_select {
    flex: none !important;
    width: auto !important;
}

.modal-calendar .dp__month_year_wrap .dp__month_year_select:nth-child(2) {
    font-size: 1.5rem !important;
}

.modal-calendar .dp--arrow-btn-nav {
    display: none !important;
}

/* Both start and end date calendars use the same modal styling */
</style>
