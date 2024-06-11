<template>
    <main class="fixed left-[25rem] top-0 w-[calc(100vw-25rem)] h-screen flex">
        <div class="flex flex-col w-9/12">
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
                style="height: 800px; overflow-y: auto;"
                :events="events"
            />
        </div>
        <div class="w-3/12 border-l border-gray-200 p-8 h-screen flex flex-col justify-between">
            <div>
                <h2>Dates</h2>
                <p class="mt-8">Step 1: Select your event's dates</p>
                <div>
                    <ul v-if="!isWeekly">
                        <li v-for="date in selectedDates" :key="date">{{ date }}</li>
                    </ul>
                    <p v-if="isWeekly">{{ weeklyDay }}</p>
                    <div v-if="selectedDates.length === 1">
                        <button @click="createWeeklyEvents(selectedDates[0])">Is this weekly?</button>
                    </div>
                </div>
            </div>
            <div class="w-full flex justify-end">
                <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
            </div>
        </div>
    </main>
</template>




<script setup>
import { ref, inject } from 'vue';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';

// Utility to add days to a date
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
const isWeekly = ref(false);
const weeklyDay = ref('');

const onDateSelect = (day) => {
    const date = new Date(day);
    if (isNaN(date.getTime())) {
        console.error("Invalid date:", day);
        return;
    }

    const formattedDate = date.toISOString().split('T')[0];
    console.log('Date:', date);
    console.log('Formatted Date:', formattedDate);

    if (formattedDate) {
        if (selectedDates.value.includes(formattedDate)) {
            selectedDates.value = selectedDates.value.filter(d => d !== formattedDate);
            events.value = events.value.filter(event => event.start !== formattedDate);
        } else {
            selectedDates.value.push(formattedDate);
            events.value.push({ start: formattedDate, end: formattedDate, title: 'Selected' });
        }
    }

    console.log('Selected Dates:', selectedDates.value);
    console.log('Events:', events.value);
};

const createWeeklyEvents = (startDateStr) => {
    const startDate = new Date(startDateStr);
    isWeekly.value = true;
    weeklyDay.value = startDate.toLocaleDateString('en-US', { weekday: 'long' });

    for (let i = 1; i <= 26; i++) {
        const nextDate = new Date(startDate).addDays(i * 7);
        const formattedDate = nextDate.toISOString().split('T')[0];
        if (!selectedDates.value.includes(formattedDate)) {
            selectedDates.value.push(formattedDate);
            events.value.push({ start: formattedDate, end: formattedDate, title: 'Selected' });
        }
    }
};

const handleSubmit = async () => {
    await onSubmit({ showtype: event.showtype });
};
</script>




<style>
.vuecal__title-bar {
    min-height: 4em;
    background: white;
}
.vuecal__cell--has-events {
    background: black;
    border-radius: 1rem;
    color: white;
}
</style>
