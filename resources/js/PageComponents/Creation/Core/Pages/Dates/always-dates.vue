<template>
    <div class="w-full">
        <!-- Flex container for desktop layout -->
        <div class="w-full flex flex-col gap-6 md:flex-row justify-center md:mt-[-1rem] md:mt-0" :class="{'mt-[-3.5rem]': layoutMode === 'creation-desktop'}">
            <!-- Icon button column (creation mode only) -->
            <div v-if="showIconButton" class="hidden md:block w-48 px-8">
                <div class="h-52 w-full justify-center flex mt-8">
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

            <!-- Main content area -->
            <div class="flex-grow overflow-hidden relative w-full bg-white" :class="{'md:max-w-5xl': shouldApplyMaxWidth}">
                <div class="md:p-2">
                    <!-- Text button (mobile or edit mode) -->
                    <div v-if="showTextButton" 
                         class="mb-6 md:mb-8"
                         :class="{'md:hidden': layoutMode === 'creation-desktop'}">
                        <button 
                            @click="$emit('back-to-selection')"
                            class="text-xl text-left text-gray-500 hover:text-black underline whitespace-nowrap w-full"
                        >
                            Change Date Type
                        </button>
                    </div>
                    
                    <!-- Header section -->
                    <div class="flex items-center justify-between mb-8">
                        <div v-if="hasEndDate">
                            <h2 class="font-medium text-black">Always Available</h2>
                        </div>
                    </div>
                    
                    <div class="text-left space-y-8">
                        <!-- Current End Date Display -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm">
                            
                            <div class="mb-12">
                                <h4 class="text-lg text-gray-600 mb-2">Your event will remain searchable until this date</h4>
                                <button 
                                    @click="openEndDateModal"
                                    class="text-3xl font-bold text-gray-900 hover:text-gray-700 transition-colors underline text-left"
                                >
                                    {{ formatDate(endDate) }}
                                </button>
                            </div>

                            <div class="">
                                <div class="text-6xl font-bold text-gray-900 leading-none">
                                    {{ daysUntilEnd }} <span class="text-lg font-normal">days remaining</span>
                                </div>
                            </div>
                        </div>
                       
                        <!-- Show Times Section -->
                        <div class="flex flex-col w-full">
                            <textarea 
                                name="Show times" 
                                class="text-2.5xl font-normal border rounded-2xl p-4 w-full" 
                                :class="{ 
                                    'border-red-500 focus:shadow-focus-error': showTimesError,
                                    'border-neutral-300 focus:border-[#222222] focus:shadow-focus-black': !showTimesError
                                }"
                                v-model="event.show_times" 
                                placeholder="Please provide a brief description of your show times, e.g., available 24/7, or call to schedule"
                                rows="4"
                                style="white-space: pre-wrap;"
                            ></textarea>
                            <p v-if="showTimesError" class="text-red-500 text-sm mt-2">
                                Too many characters (max 500)
                            </p>
                        </div>

                        <!-- Timezone Section -->

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
                    </div>
                </div>
            </div>
        </div>

        <!-- End Date Modal -->
        <div v-if="showEndDateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[500] p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl w-[600px] mx-4 max-h-[90vh] my-auto flex flex-col">
                <div class="flex items-center justify-between p-8 pb-6 border-b border-gray-200">
                    <h3 class="text-2xl font-bold">Choose End Date</h3>
                    <button 
                        @click="showEndDateModal = false"
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
                            v-model="tempEndDate"
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
                            @update:model-value="selectEndDate"
                            class="end-date-calendar modal-calendar"
                        />
                    </div>
                </div>
                
                <!-- Calendar action buttons -->
                <div class="flex justify-between gap-4 p-8 pt-6 border-t border-gray-200 bg-white rounded-b-2xl">
                    <button 
                        @click="extendToSixMonths(); showEndDateModal = false"
                        class="px-6 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-all duration-200"
                    >
                        Extend to 6 months
                    </button>
                    <div class="flex gap-4">
                        <button 
                            @click="cancelEndDate"
                            class="px-6 py-2 border rounded-lg hover:bg-gray-100"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="confirmEndDate"
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
</template>

<script setup>
import { ref, computed, inject, onMounted, watch } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import VueDatePicker from '@vuepic/vue-datepicker';
import moment from 'moment-timezone';
import { 
    formatDateForDisplay,
    addMonths,
    createDateAtNoon,
    daysBetween,
    getBrowserTimezone
} from '@/composables/dateUtils';

// Props
const props = defineProps({
    selectedTimezone: {
        type: String,
        default: 'America/New_York'
    }
});

// Emits
const emit = defineEmits(['back-to-selection', 'timezone-changed']);

// Injected dependencies
const event = inject('event');
const user = inject('user');
const isSidebarCollapsed = inject('isSidebarCollapsed', ref(true));

// Reactive state
const endDate = ref(null);
const showEndDateModal = ref(false);
const tempEndDate = ref(null);
const timezones = ref([]);
const localTimezone = ref(props.selectedTimezone);

// Validation rules
const rules = {
    endDate: { required }
};

const $v = useVuelidate(rules, { endDate });

// Computed properties
const isEditMode = computed(() => {
    return event.status === 'e' || event.status === 'p';
});

// Layout mode: explicit state for clarity
const layoutMode = computed(() => {
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

// Apply max width constraint when NOT in mobile-style layout
const shouldApplyMaxWidth = computed(() => {
    // Only apply max width in creation mode or edit with sidebar collapsed
    return layoutMode.value === 'creation-desktop' || 
           layoutMode.value === 'edit-desktop-collapsed';
});

const hasEndDate = computed(() => !!endDate.value);

const showTimesError = computed(() => {
    return event.show_times && event.show_times.length > 500;
});

const daysUntilEnd = computed(() => {
    if (!endDate.value) return 0;
    
    const days = daysBetween(new Date(), endDate.value, localTimezone.value);
    return Math.max(0, days); // Don't show negative days
});

// Methods
const formatDate = (date) => {
    if (!date) return '';
    return formatDateForDisplay(date, localTimezone.value);
};

const extendToSixMonths = () => {
    endDate.value = addMonths(new Date(), 6, localTimezone.value);
};

// End date modal methods
const openEndDateModal = () => {
    if (endDate.value) {
        // Create a new date preserving the local date without timezone shift
        const d = new Date(endDate.value);
        tempEndDate.value = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 12, 0, 0);
    } else {
        tempEndDate.value = null;
    }
    showEndDateModal.value = true;
};

const selectEndDate = (selectedDate) => {
    tempEndDate.value = selectedDate;
};

const confirmEndDate = () => {
    if (tempEndDate.value) {
        endDate.value = createDateAtNoon(tempEndDate.value, localTimezone.value);
        showEndDateModal.value = false;
        tempEndDate.value = null;
    }
};

const cancelEndDate = () => {
    showEndDateModal.value = false;
    tempEndDate.value = null;
};

const loadTimezones = () => {
    // Use moment-timezone to get all timezone names
    const timezoneNames = moment.tz.names();
    timezones.value = timezoneNames.map(name => ({ name }));
};

// Watch for timezone changes
watch(localTimezone, (newTimezone) => {
    emit('timezone-changed', newTimezone);
});

// Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        return !$v.value.$error && !showTimesError.value;
    },
    
    getDates: () => {
        // Always events return one date set to the end date
        return endDate.value ? [endDate.value] : [];
    },
    
    getConfiguration: () => {
        return {
            endDate: endDate.value,
            timezone: localTimezone.value
        };
    },
    
    setConfiguration: (config) => {
        if (config.endDate) {
            endDate.value = createDateAtNoon(config.endDate, localTimezone.value);
        }
        if (config.timezone) {
            localTimezone.value = config.timezone;
        }
    }
});

// Lifecycle
onMounted(() => {
    loadTimezones();
    
    // Initialize with current event data or default to 6 months from now
    if (event.closingDate) {
        endDate.value = createDateAtNoon(event.closingDate, props.selectedTimezone);
    } else {
        endDate.value = addMonths(new Date(), 6, props.selectedTimezone);
    }
    
    localTimezone.value = props.selectedTimezone;
});
</script>

<style>
/* Calendar styling for modals */
.end-date-calendar .dp--arrow-btn-nav {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

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

.end-date-calendar .dp__arrow_btn:hover {
    background-color: #f3f3f3;
}

.end-date-calendar .dp__inner_nav {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.end-date-calendar .dp__arrow_btn_container {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.end-date-calendar .dp__arrow_btn svg {
    fill: #333 !important;
    width: 1rem !important;
    height: 1rem !important;
}

/* Modal calendar styling for vertical scrolling */
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
</style>
