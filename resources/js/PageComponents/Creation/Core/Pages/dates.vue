<template>
    <main class="w-full pb-24">
        <div v-if="event.showtype=== null || event.showtype=== 'a'">
            <div class="flex flex-col w-full">
                <h2>Do you have specific dates?</h2>
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
        <div v-else class="fixed left-0 top-0 w-full h-[calc(100vh-8rem)] flex overflow-hidden">
            <div class="flex flex-col w-9/12 overflow-y-auto">
                <vue-cal
                    :time="false"
                    :hide-title="true"
                    hide-view-selector
                    small
                    :disable-views="['years', 'year', 'week', 'day']"
                    active-view="month"
                    :multi-day="true"
                    :months="6"
                    @cell-click="onDateSelect"
                    :min-date="new Date()"
                    :max-date="new Date(new Date().setMonth(new Date().getMonth() + 6))"
                    style="height: 100%; overflow-y: auto;"
                    :events="events"
                />
            </div>
            <div class="w-3/12 border-l border-gray-200 h-full flex flex-col justify-between bg-white">
                <div class="flex justify-between items-center p-8 border-b border-gray-200">
                    <a href="/hosting/events" class="text-3xl font-bold hover:opacity-70">
                        EI
                    </a>
                    <div class="flex items-center gap-4">
                        <button class="text-black hover:bg-gray-100 rounded-lg">
                            Questions
                        </button>
                        <a 
                            href="/hosting/events" 
                            class="px-6 py-3 border border-black hover:bg-gray-100 rounded-lg"
                        >
                            Exit
                        </a>
                    </div>
                </div>
                <div class="h-full flex flex-col justify-between">
                    <div class="">
                        <div v-if="!selectedDatesCount" class="p-8">
                            <h2>Dates</h2>
                            <p class="mt-8">Step 1: Select your event's dates</p>
                        </div>
                        <p v-if="$v.selectedDates.$error" 
                            class="text-red-500 mb-2 p-8">
                            Please select at least one date
                        </p>
                        <div v-else class="flex justify-between items-center h-44 p-8">
                            <h3 class="text-4xl">{{ selectedDatesCount }} {{ selectedDatesCount === 1 ? 'Night' : 'Nights' }}</h3>
                            <div 
                                @mouseover="hoveredLocation = 'clearAllDates'" 
                                @mouseout="hoveredLocation = null" 
                                @click="clearAllDates" 
                                class="cursor-pointer bg-white"
                            >
                                <component :is="hoveredLocation === 'clearAllDates' ? RiCloseCircleFill : RiCloseCircleLine" />
                            </div>
                        </div>
                        <div v-if="selectedDatesCount" class="p-8 relative">
                            <div>
                                <textarea 
                                    name="Show times" 
                                    class="text-2xl font-normal border border-gray-300 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full" 
                                    v-model="event.show_times" 
                                    @input="$v.event.show_times.$touch"
                                    placeholder="Please provide a brief description of your show times, e.g., doors open at 7 PM, show starts at 8 PM"
                                    rows="6"
                                    style="white-space: pre-wrap;"
                                ></textarea>
                                <p v-if="$v.event.show_times.$dirty && $v.event.show_times.maxLength.$invalid" 
                                   class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                                    Too many characters
                                </p>
                            </div>
                            <div class="mt-4 flex w-full border p-4 rounded-2xl border-gray-300 hover:bg-gray-100 hover:shadow-[0_0_0_2px_black]">
                                <p class="text-lg">Timezone: </p>
                                <select id="timezone" v-model="selectedTimezone" class="pl-2 ml-2 font-bold w-full cursor-pointer hover:bg-transparent">
                                    <option v-for="timezone in timezones" :key="timezone.name" :value="timezone.name">
                                        {{ timezone.name }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="promptVisible" class="mt-4 p-4 rounded-2xl relative border-black border hover:bg-gray-100 hover:shadow-[0_0_0_1.5px_black]">
                                <div class="cursor-pointer" @click="handlePromptYes">
                                    <p>{{ promptMessage }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col gap-2">
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
                                    <vue-cal
                                        :time="false"
                                        :hide-title="true"
                                        hide-view-selector
                                        small
                                        :disable-views="['years', 'year', 'week', 'day']"
                                        active-view="month"
                                        :min-date="new Date()"
                                        @cell-click="selectEmbargoDate"
                                        style="height: 400px;"
                                    />
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
    </main>
</template>

<script setup>
// 1. Imports
import { ref, computed, inject, onMounted, watch } from 'vue';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { maxLength, required } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';

// 2. Injected Dependencies
const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');

// 3. Refs & State
const events = ref([]);
const selectedDates = ref([]);
const promptVisible = ref(false);
const promptMessage = ref('');
const promptAction = ref(null);
const selectedDate = ref(null);
const hoveredLocation = ref(null);
const tempSelectedDates = ref([]);
const tempShowTimes = ref('');
const previousShowType = ref(null);
const timezones = ref([]);
const selectedTimezone = ref(Intl.DateTimeFormat().resolvedOptions().timeZone);
const userGMTOffset = ref('');
const showEmbargoModal = ref(false);
const tempEmbargoDate = ref(null);

// 4. Validation Rules
const rules = {
    event: {
        show_times: { maxLength: maxLength(500) }
    },
    selectedDates: {
        required: (value) => event.showtype !== 's' || (event.showtype === 's' && value.length > 0)
    }
};

const $v = useVuelidate(rules, { 
    event,
    selectedDates 
});

// 5. Computed Properties
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

// 6. Core Date Selection Methods
const onDateSelect = (day) => {
    hoveredLocation.value = null;
    const date = new Date(day);
    date.setHours(0, 0, 0, 0);
    
    if (isNaN(date.getTime())) return;

    const formattedDate = date.toISOString().split('T')[0];
    const weekday = date.toLocaleDateString('en-US', { weekday: 'long' });

    if (selectedDates.value.includes(formattedDate)) {
        handleDateDeselection(formattedDate, weekday);
    } else {
        handleDateSelection(formattedDate, weekday);
    }
};

// 7. Weekly Events Handling (Your Original Code)
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

const createWeeklyEvents = async (startDateStr) => {
    const { default: moment } = await import('moment-timezone');
    const timezone = selectedTimezone.value;
    const startDate = moment.tz(startDateStr, timezone);
    const targetDay = startDate.day();
    
    for (let i = 1; i < 26; i++) {
        const nextDate = startDate.clone().add(i, 'weeks');
        if (nextDate.isAfter(moment().add(180, 'days'))) break;
        
        const formattedDate = nextDate.format('YYYY-MM-DD');
        
        if (!selectedDates.value.includes(formattedDate)) {
            selectedDates.value.push(formattedDate);
            events.value.push({ 
                start: formattedDate, 
                end: formattedDate, 
                title: 'Selected' 
            });
        }
    }
};

const removeWeeklyEvents = async (startDateStr) => {
    const { default: moment } = await import('moment-timezone');
    const timezone = selectedTimezone.value;
    const startDate = moment.tz(startDateStr, timezone);
    const startDay = startDate.day();

    selectedDates.value = selectedDates.value.filter(dateStr => {
        const date = moment.tz(dateStr, timezone);
        return !(date.isAfter(startDate) && date.day() === startDay);
    });
    
    events.value = events.value.filter(event => selectedDates.value.includes(event.start));
};

// 8. Prompt & Helper Methods
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

const checkFutureDates = (dateStr) => {
    const date = new Date(dateStr);
    const weekday = date.getDay();
    
    return selectedDates.value.some(d => {
        const existingDate = new Date(d);
        return existingDate > date && existingDate.getDay() === weekday;
    });
};

// 9. Date Management Methods
const setSpecificDates = () => {
    event.showtype = 's';
};

const clearAllDates = () => {
    selectedDates.value = [];
    events.value = [];
    event.show_times = '';
};

// 10. Embargo Date Methods
const toggleEmbargoDate = () => {
    if (hasEmbargoDate.value) {
        event.embargo_date = null;
    } else {
        showEmbargoCalendar();
    }
};

const showEmbargoCalendar = () => {
    showEmbargoModal.value = true;
};

const selectEmbargoDate = (day) => {
    tempEmbargoDate.value = day;
};

const confirmEmbargoDate = () => {
    if (tempEmbargoDate.value) {
        const date = new Date(tempEmbargoDate.value);
        date.setHours(0, 0, 0, 0);
        event.embargo_date = date.toISOString().slice(0, 19).replace('T', ' ');
        showEmbargoModal.value = false;
        tempEmbargoDate.value = null;
    }
};

const clearEmbargoToggle = () => {
    event.embargo_date = null;
};

// 11. API Methods
const initializeTimezones = async () => {
    const { default: moment } = await import('moment-timezone');
    timezones.value = moment.tz.names().map(name => ({ name }));
};

// 12. Component API
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
            dateArray: formattedDates,
            timezone: selectedTimezone.value,
            show_times: event.show_times,
            embargo_date: event.embargo_date
        };
        return data;
    }
});

// 13. Watchers
watch(() => event.showtype, (newType, oldType) => {
    if (oldType === 's' && newType === 'a') {
        tempSelectedDates.value = [...selectedDates.value];
        tempShowTimes.value = event.show_times;
        previousShowType.value = 's';
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
});

// 14. Lifecycle Hooks
onMounted(() => {
    initializeTimezones();
    if (event.shows?.length > 0) {
        event.shows.forEach(show => {
            const formattedDate = new Date(show.date).toISOString().split('T')[0];
            selectedDates.value.push(formattedDate);
            events.value.push({ 
                start: formattedDate, 
                end: formattedDate, 
                title: 'Selected' 
            });
        });
    }
});
</script>

<style>
/* Your original styling preserved exactly as is */
.vuecal__cell--disabled {
    color: #000000;
    cursor: not-allowed;
    background: #ebebeb;
}
.vuecal__cell--today, .vuecal__cell--current {
    background-color: white;
    z-index: 1;
    border: 2px solid;
    border-radius: 1rem;
}
.vuecal__title-bar {
    min-height: 4em;
    background: white;
}
.vuecal__cell--has-events {
    background: #222222;
    border-radius: 1rem;
    color: white;
    border: 1px solid;
}
.vuecal__cell--has-events:hover {
    background: black;
}
.vuecal__cell-events-count {
    display: none;
}
.vuecal--month-view .vuecal__cell-content {
    justify-content: start;
}
.vuecal__cell {
    padding: 1rem;
    text-align: left;
}

.vuecal__arrow {
    position: relative;
    z-index: 2;
    background: #222222;
    border-radius: 9999px;
    width: 30px !important;
    height: 30px !important;
    min-width: 30px !important;
    min-height: 30px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
    padding: 0;
    margin: 0;
    box-sizing: border-box;
    border: none;
}

.vuecal__arrow--prev {
    left: 1rem;
}

.vuecal__arrow--next {
    right: 1rem;
}

.vuecal__arrow i.angle {
    display: inline-block;
    border: solid white;
    border-width: 0 2px 2px 0;
    padding: 3px;
    position: absolute;
    top: 50%;
    left: 50%;
}

.vuecal__arrow--prev i.angle {
    transform: translate(-25%, -50%) rotate(135deg);
}

.vuecal__arrow--next i.angle {
    transform: translate(-75%, -50%) rotate(-45deg);
}

.vuecal__arrow:hover {
    background: black;
}

.c-embargo .vuecal__cell--selected {
    background-color: black;
    z-index: 2;
}
.c-embargo .vuecal__cell--selected .vuecal__cell-date {
    color: white;
}
</style>
