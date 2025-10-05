<template>
    <main class="w-full h-full" :class="{'narrow-layout': isNarrowLayout}">
        <!-- Toggle Sidebar Button -->
        <div v-if="isDesktop && isPublished" class="absolute z-[500] left-12 top-12">
                <button @click="toggleSidebar" class="bg-white flex border-none p-6 rounded-2xl items-center justify-center shadow-custom-1">
                    <span class="text-2xl font-medium">{{ isSidebarHidden ? 'Collapse' : 'Expand' }}</span>
                </button>
            </div>
        <div class="mx-auto" :class="selectionWidthClass" v-if="event.showtype=== null">
            <h2>Do you have specific dates?</h2>
        </div>
        <div class="mx-auto" :class="selectionWidthClass" v-if="event.showtype=== null">
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
                        @click="setOngoingDates"
                        :class="[
                            'border rounded-2xl flex justify-between items-center w-full h-48 p-8 transition-all duration-200',
                            {
                                'border-[#222222] shadow-focus-black': event.showtype === 'o',
                                'border-neutral-300 hover:border-[#222222] hover:shadow-focus-black hover:bg-neutral-50': event.showtype !== 'o'
                            }
                        ]"
                    >
                        <div class="w-full text-left">
                            <p class="font-bold text-3xl">
                                Ongoing/Recurring
                            </p>
                            <p class="text-1xl mt-4 text-neutral-700 font-light">
                                Select days of the week for recurring shows.
                            </p>
                        </div>
                    </button>
                    <button 
                        @click="setAlwaysDates"
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
        class="relative rounded-4xl h-full">
            <!-- Ongoing Dates Component -->
            <div v-if="isOngoingMode" class="w-full">
                <OngoingDates 
                    ref="ongoingDatesRef"
                    :selected-timezone="selectedTimezone"
                    @dates-generated="handleOngoingDatesGenerated"
                    @back-to-selection="handleBackToSelection"
                    @timezone-changed="handleTimezoneChanged"
                />
            </div>

            <!-- Always Dates Component -->
            <div v-else-if="isAlwaysMode" class="w-full">
                <AlwaysDates 
                    ref="alwaysDatesRef"
                    :selected-timezone="selectedTimezone"
                    @back-to-selection="handleBackToSelection"
                    @timezone-changed="handleTimezoneChanged"
                />
            </div>

            <!-- Specific Dates Component -->
            <div v-else class="w-full h-full">
                <SpecificDates 
                    ref="specificDatesRef"
                    :selected-timezone="selectedTimezone"
                    :parent-container-width="parentContainerWidth"
                    @dates-updated="handleSpecificDatesUpdated"
                    @back-to-selection="handleBackToSelection"
                    @timezone-changed="handleTimezoneChanged"
                />
            </div>
        </div>
    </main>
</template>

<script setup>
// 1. Core Imports
import { ref, computed, inject, onMounted } from 'vue';
import { maxLength, required } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import OngoingDates from './Dates/ongoing-dates.vue';
import SpecificDates from './Dates/specific-dates.vue';
import AlwaysDates from './Dates/always-dates.vue';

const emit = defineEmits(['toggle-sidebar']);

// 2. Injected Dependencies & Core State
const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const user = inject('user');
const parentContainerWidth = inject('parentContainerWidth', ref(0));
const isSidebarCollapsed = inject('isSidebarCollapsed', ref(false));
const date = ref([]);
const isPublished = computed(() => event.status === 'p' || event.status === 'e');

// 3. Calendar State
const selectedDates = ref([]);
const ongoingDatesRef = ref(null);
const specificDatesRef = ref(null);
const alwaysDatesRef = ref(null);

// 4. State Preservation - separate state for each mode
const specificDatesState = ref([]);
const ongoingDatesState = ref({
    selectedDays: [],
    startDate: null,
    endDate: null,
    isOngoing: true
});
const alwaysDatesState = ref({
    endDate: null,
    showTimes: '',
    timezone: 'America/New_York'
});

// 5. Core State
const selectedTimezone = ref(Intl.DateTimeFormat().resolvedOptions().timeZone);
const isSidebarHidden = ref(false);

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
const isOngoingMode = computed(() => event.showtype === 'o');
const isSpecificMode = computed(() => event.showtype === 's');
const isAlwaysMode = computed(() => event.showtype === 'a');

// UI computed properties
const isDesktop = computed(() => {
    if (typeof window !== 'undefined') {
        return window.innerWidth >= 768;
    }
    return true; // Default to desktop on server
});

const isNarrowLayout = computed(() => {
    return event.status === 'e' || event.status === 'p' || !isDesktop.value;
});

const selectionWidthClass = computed(() => {
    // In edit mode (published or embargoed)
    if (isPublished.value && isDesktop.value) {
        // When "Expand" is clicked, isSidebarCollapsed = true, we want md:w-1/2
        // When sidebar visible (normal), isSidebarCollapsed = false, we want w-full  
        return isSidebarCollapsed.value ? 'w-full md:w-2/3' : 'w-full';
    }
    // In creation mode, default to w-1/2 on desktop, w-full on mobile
    return 'w-full md:w-1/2';
});

const toggleSidebar = () => {
    isSidebarHidden.value = !isSidebarHidden.value;
    emit('toggle-sidebar');
};

// 8. Date Management Methods
const setSpecificDates = () => {
    // Save current ongoing state if we're switching from ongoing
    if (event.showtype === 'o' && ongoingDatesRef.value) {
        ongoingDatesState.value = ongoingDatesRef.value.getConfiguration();
    }
    
    console.log('Switching to specific dates:', {
        previousShowtype: event.showtype,
        savedOngoingState: ongoingDatesState.value,
        specificDatesState: specificDatesState.value,
        currentSelectedDates: selectedDates.value
    });
    
    event.showtype = 's';
    
    // Restore specific dates state
    selectedDates.value = [...specificDatesState.value];
    date.value = specificDatesState.value.map(dateStr => {
        const d = new Date(dateStr);
        d.setHours(12, 0, 0, 0);
        return d;
    });
    
    // Set the specific dates in the component when it's ready
    setTimeout(() => {
        if (specificDatesRef.value) {
            specificDatesRef.value.setDates(specificDatesState.value);
        }
    }, 100);
};

const setOngoingDates = () => {
    // Note: Don't save specific dates state here because handleBackToSelection already did it
    // if we came from the selection screen. If we came directly from specific mode,
    // the state is already being tracked via handleSpecificDatesUpdated
    
    console.log('Switching to ongoing dates:', {
        previousShowtype: event.showtype,
        ongoingDatesState: ongoingDatesState.value,
        currentSelectedDates: selectedDates.value,
        specificDatesState: specificDatesState.value
    });
    
    event.showtype = 'o';
    
    // Clear current dates display
    selectedDates.value = [];
    date.value = [];
    
    // Restore ongoing dates state when component is ready (if it exists)
    setTimeout(() => {
        if (ongoingDatesRef.value && ongoingDatesState.value) {
            console.log('Restoring ongoing dates configuration:', ongoingDatesState.value);
            ongoingDatesRef.value.setConfiguration(ongoingDatesState.value);
        }
        // If no ongoing state exists, ongoing dates component starts fresh
    }, 100);
};

const setAlwaysDates = () => {
    // Save current state if we're switching from another mode
    if (event.showtype === 'o' && ongoingDatesRef.value) {
        ongoingDatesState.value = ongoingDatesRef.value.getConfiguration();
    } else if (event.showtype === 's' && selectedDates.value.length > 0) {
        specificDatesState.value = [...selectedDates.value];
    }
    
    console.log('Switching to always dates:', {
        previousShowtype: event.showtype,
        alwaysDatesState: alwaysDatesState.value
    });
    
    event.showtype = 'a';
    
    // Clear embargo date since always-dates doesn't have embargo functionality
    event.embargo_date = null;
    
    console.log('After setting showtype:', {
        showtype: event.showtype,
        isAlwaysMode: isAlwaysMode.value,
        isOngoingMode: isOngoingMode.value,
        isSpecificMode: isSpecificMode.value
    });
    
    // Clear current dates display
    selectedDates.value = [];
    date.value = [];
    
    // Restore always dates state when component is ready (if it exists)
    setTimeout(() => {
        if (alwaysDatesRef.value && alwaysDatesState.value.endDate) {
            console.log('Restoring always dates configuration:', alwaysDatesState.value);
            alwaysDatesRef.value.setConfiguration(alwaysDatesState.value);
        }
        // If no always state exists, always dates component starts with default 6-month end date
    }, 100);
};

const handleOngoingDatesGenerated = (dates) => {
    // Convert Date objects to the format expected by the rest of the component
    selectedDates.value = dates.map(date => {
        const d = new Date(date);
        d.setHours(12, 0, 0, 0);
        return d.toISOString().split('T')[0];
    });
    
    // Update the VueDatePicker date array
    date.value = dates.map(date => {
        const d = new Date(date);
        d.setHours(12, 0, 0, 0);
        return d;
    });
};

const handleBackToSelection = () => {
    // Save current state before going back to selection
    if (event.showtype === 's' && selectedDates.value.length > 0) {
        specificDatesState.value = [...selectedDates.value];
    } else if (event.showtype === 'o' && ongoingDatesRef.value) {
        ongoingDatesState.value = ongoingDatesRef.value.getConfiguration();
    } else if (event.showtype === 'a' && alwaysDatesRef.value) {
        alwaysDatesState.value = alwaysDatesRef.value.getConfiguration();
    }
    
    event.showtype = null;
    // Clear current display but preserve in state
    selectedDates.value = [];
    date.value = [];
};

const handleTimezoneChanged = (newTimezone) => {
    selectedTimezone.value = newTimezone;
};

const handleSpecificDatesUpdated = (dates) => {
    selectedDates.value = dates;
    specificDatesState.value = [...dates]; // Save to state preservation
    
    // Update the VueDatePicker date array for consistency
    date.value = dates.map(dateStr => {
        const d = new Date(dateStr);
        d.setHours(12, 0, 0, 0);
        return d;
    });
};


// 9. Component API
defineExpose({
    isValid: async () => {
        // If in ongoing mode, validate the ongoing dates component
        if (event.showtype === 'o' && ongoingDatesRef.value) {
            return await ongoingDatesRef.value.isValid();
        }
        
        // If in always mode, validate the always dates component
        if (event.showtype === 'a' && alwaysDatesRef.value) {
            return await alwaysDatesRef.value.isValid();
        }
        
        // If in specific mode, validate the specific dates component
        if (event.showtype === 's' && specificDatesRef.value) {
            return await specificDatesRef.value.isValid();
        }
        
        // Fallback validation
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { dates: ['Please select at least one date'] };
        }
        
        return isValid;
    },
    submitData: () => {
        let formattedDates = [];
        
        // Get dates based on the showtype
        if (event.showtype === 'o' && ongoingDatesRef.value) {
            const ongoingDates = ongoingDatesRef.value.getDates();
            formattedDates = ongoingDates.map(date => 
                new Date(date).toISOString().slice(0, 19).replace('T', ' ')
            );
        } else if (event.showtype === 'a' && alwaysDatesRef.value) {
            const alwaysDates = alwaysDatesRef.value.getDates();
            formattedDates = alwaysDates.map(date => 
                new Date(date).toISOString().slice(0, 19).replace('T', ' ')
            );
        } else if (event.showtype === 's' && specificDatesRef.value) {
            const specificDates = specificDatesRef.value.getDates();
            formattedDates = specificDates.map(date => 
                new Date(date).toISOString().slice(0, 19).replace('T', ' ')
            );
        } else {
            // Fallback to the internal selectedDates
            formattedDates = selectedDates.value.map(date => 
                new Date(date).toISOString().slice(0, 19).replace('T', ' ')
            );
        }
        
        const data = {
            showtype: event.showtype, // Use actual showtype: 's', 'o', or 'a'
            dateArray: formattedDates,
            timezone: selectedTimezone.value,
            show_times: event.show_times,
            embargo_date: event.embargo_date
        };
        
        // For ongoing events, include the end date configuration
        if (event.showtype === 'o' && ongoingDatesRef.value) {
            const config = ongoingDatesRef.value.getConfiguration();
            data.ongoing_config = {
                endDate: config.endDate ? (() => {
                    const date = new Date(config.endDate);
                    // Format as YYYY-MM-DD HH:MM:SS in local timezone to avoid UTC conversion
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day} 23:59:59`;
                })() : null
            };
        }
        
        // For always events, include the end date configuration
        if (event.showtype === 'a' && alwaysDatesRef.value) {
            const config = alwaysDatesRef.value.getConfiguration();
            data.always_config = {
                endDate: config.endDate ? (() => {
                    const date = new Date(config.endDate);
                    // Format as YYYY-MM-DD HH:MM:SS in local timezone to avoid UTC conversion
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day} 23:59:59`;
                })() : null
            };
        }
        
        return data;
    }
});

// 10. Lifecycle Hooks
onMounted(() => {
    // Clear any existing dates first
    date.value = [];
    selectedDates.value = [];
    
    // If event has a timezone set (from location), use it
    if (event.timezone) {
        selectedTimezone.value = event.timezone;
    }
    
    // Only set dates if we have shows and we're in specific dates mode (s), live mode (l), ongoing mode (o), or always mode (a)
    if (event.shows?.length > 0 && (event.showtype === 's' || event.showtype === 'l' || event.showtype === 'o' || event.showtype === 'a')) {
        // Convert legacy 'l' type to 's' for editing purposes
        if (event.showtype === 'l') {
            event.showtype = 's';
        }
        
        const showDates = event.shows.map(show => {
            const date = new Date(show.date);
            date.setHours(12, 0, 0, 0);
            return date;
        });
        
        date.value = showDates;
        selectedDates.value = showDates.map(d => d.toISOString().split('T')[0]);
        
        // Initialize state for specific dates mode
        if (event.showtype === 's') {
            specificDatesState.value = [...selectedDates.value];
        }
        
        // Initialize state for always dates mode
        if (event.showtype === 'a') {
            alwaysDatesState.value = {
                endDate: event.closingDate ? new Date(event.closingDate) : null,
                showTimes: event.show_times || '',
                timezone: event.timezone || selectedTimezone.value
            };
        }
        
        // Set the dates in the appropriate component when it's ready
        setTimeout(() => {
            if (event.showtype === 's' && specificDatesRef.value) {
                specificDatesRef.value.setDates(selectedDates.value);
            } else if (event.showtype === 'o' && ongoingDatesRef.value) {
                // Reconstruct the ongoing configuration from the saved dates
                ongoingDatesRef.value.reconstructFromDates(selectedDates.value);
            } else if (event.showtype === 'a' && alwaysDatesRef.value) {
                // Set the always dates configuration
                alwaysDatesRef.value.setConfiguration(alwaysDatesState.value);
            }
        }, 100);
    }
});
</script>
