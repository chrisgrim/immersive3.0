<template>
    <main class="w-full">
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
                <div v-if="event.showtype === 'a'" class="w-full flex justify-end h-[6.3rem]">
                    <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
                </div>
                <div v-else class="h-[6.3rem]"></div>
            </div>
        </div>
        <div v-else class="fixed left-[25rem] top-0 w-[calc(100vw-25rem)] h-screen flex overflow-hidden">
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
            <div class="w-3/12 border-l border-gray-200 h-screen flex flex-col justify-between h-screen">
                <div class="h-full flex flex-col justify-between">
                    <div class="">
                        <div v-if="!selectedDatesCount" class="p-8">
                            <h2>Dates</h2>
                            <p class="mt-8">Step 1: Select your event's dates</p>
                        </div>
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
                        </div>
                    </div>
                    <div class="w-full flex justify-between p-8">
                        <button @click="event.showtype = null" class="mt-8 text-xl rounded-2xl underline">Switch show type</button>
                        <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';

Date.prototype.addDays = function(days) {
    const date = new Date(this.valueOf());
    date.setDate(date.getDate() + days);
    return date;
};


const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

const events = ref([]);
const selectedDates = ref([]);
const promptVisible = ref(false);
const promptMessage = ref('');
const promptAction = ref(null);
const selectedDate = ref(null);
const hoveredLocation = ref(null);

const timezones = ref([]);
const selectedTimezone = ref('');
const userGMTOffset = ref('');

const rules = {
    event: {
        show_times: {
            maxLength: maxLength(1000),
        }
    }
};
const $v = useVuelidate(rules, { event });

const selectedDatesCount = computed(() => selectedDates.value.length);

const fetchTimezones = async () => {
    const { default: moment } = await import('moment-timezone');
    timezones.value = moment.tz.names().map(tz => ({
        name: tz,
        offset: moment.tz(tz).format('Z')
    }));

    const userTimezone = event.timezone ? event.timezone : moment.tz.guess();
    const matchedTimezone = timezones.value.find(tz => tz.name === userTimezone);
    if (matchedTimezone) {
        selectedTimezone.value = matchedTimezone.name;
        userGMTOffset.value = matchedTimezone.offset;
    }
};

const onDateSelect = (day) => {
    hoveredLocation.value = null;
    const date = new Date(day);
    if (isNaN(date.getTime())) {
        console.error("Invalid date:", day);
        return;
    }

    const formattedDate = date.toISOString().split('T')[0];
    const weekday = date.toLocaleDateString('en-US', { weekday: 'long' });

    if (selectedDates.value.includes(formattedDate)) {
        selectedDates.value = selectedDates.value.filter(d => d !== formattedDate);
        events.value = events.value.filter(event => event.start !== formattedDate);
        
        const futureDatesExist = selectedDates.value.some(dateStr => {
            const futureDate = new Date(dateStr);
            return futureDate > new Date(formattedDate) && futureDate.getDay() === new Date(formattedDate).getDay();
        });

        if (futureDatesExist) {
            promptMessage.value = `Remove future ${weekday}s?`;
            promptAction.value = 'removeFuture';
            selectedDate.value = formattedDate;
            promptVisible.value = true;
        } else {
            promptVisible.value = false;
        }
    } else {
        selectedDates.value.push(formattedDate);
        events.value.push({ start: formattedDate, end: formattedDate, title: 'Selected' });

        const futureDatesExist = selectedDates.value.some(dateStr => {
            const futureDate = new Date(dateStr);
            return futureDate > new Date(formattedDate) && futureDate.getDay() === new Date(formattedDate).getDay();
        });

        if (!futureDatesExist) {
            promptMessage.value = `Repeat future ${weekday}s`;
            promptAction.value = 'selectWeekly';
            selectedDate.value = formattedDate;
            promptVisible.value = true;
        } else {
            promptVisible.value = false;
        }
    }
};

const handlePromptYes = () => {
    if (promptAction.value === 'selectWeekly') {
        createWeeklyEvents(selectedDate.value);
    } else if (promptAction.value === 'removeFuture') {
        removeWeeklyEvents(selectedDate.value);
    }
    promptVisible.value = false;
};

const handlePromptNo = () => {
    promptVisible.value = false;
};

const createWeeklyEvents = (startDateStr) => {
    const startDate = new Date(startDateStr);
    const weekday = startDate.toLocaleDateString('en-US', { weekday: 'long' });

    for (let i = 1; i < 26; i++) {
        const nextDate = new Date(startDate).addDays(i * 7);
        if (nextDate > new Date().addDays(180)) break;
        const formattedDate = nextDate.toISOString().split('T')[0];
        if (!selectedDates.value.includes(formattedDate)) {
            selectedDates.value.push(formattedDate);
            events.value.push({ start: formattedDate, end: formattedDate, title: 'Selected' });
        }
    }
};

const removeWeeklyEvents = (startDateStr) => {
    const startDate = new Date(startDateStr);
    const weekday = startDate.toLocaleDateString('en-US', { weekday: 'long' });

    selectedDates.value = selectedDates.value.filter(dateStr => {
        const date = new Date(dateStr);
        return !(date > startDate && date.getDay() === startDate.getDay());
    });

    events.value = events.value.filter(event => selectedDates.value.includes(event.start));
};

const clearAllDates = () => {
    selectedDates.value = [];
    events.value = [];
};

const handleSubmit = async () => {
    errors.value = {};
    const isFormValid = await $v.value.$validate();
    if (!isFormValid) {
        return;
    }

    const formattedDates = selectedDates.value.map(date => new Date(date).toISOString().slice(0, 19).replace('T', ' '));
    await onSubmit({ showtype: event.showtype, dateArray: formattedDates, timezone: selectedTimezone.value, show_times:event.show_times });
};

const setSpecificDates = () => {
    // clearAllDates();
    event.showtype = 's';
};

const switchToAlways = () => {
    if (event.showtype === 's') {
        // Restore the dates and show times from temporary storage
        selectedDates.value = [...tempSelectedDates.value];
        event.show_times = tempShowTimes.value;
    }
    event.showtype = 'a';
};

onMounted(() => {
    fetchTimezones();

    if (event.shows && event.shows.length > 0) {
        event.shows.forEach(show => {
            const formattedDate = new Date(show.date).toISOString().split('T')[0];
            selectedDates.value.push(formattedDate);
            events.value.push({ start: formattedDate, end: formattedDate, title: 'Selected' });
        });
    }
});
</script>


<style>
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
</style>
