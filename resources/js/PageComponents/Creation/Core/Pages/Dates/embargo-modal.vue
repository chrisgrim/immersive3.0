<template>
    <!-- When the event first shows on EI: a day and a time where the event is.
         Airbnb's pop-up: close on the left, the calendar, a time menu, then
         Cancel and Save. Shared by the specific-dates and ongoing steps. -->
    <Teleport to="body">
        <div class="c-embargo fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[1003]" role="dialog" aria-modal="true" aria-labelledby="embargo-title" @click="$emit('cancel')">
            <div ref="dialog" tabindex="-1" class="bg-white w-full md:w-[64rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl max-h-[92dvh] flex flex-col focus:outline-none" data-embargo-modal @click.stop>
                <div class="relative flex items-center justify-center h-[6.4rem] px-[2.4rem] border-b border-[#ebebeb] flex-shrink-0">
                    <button type="button" aria-label="Close" class="absolute left-[1.6rem] w-[4rem] h-[4rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" @click="$emit('cancel')">
                        <svg class="w-[1.6rem] h-[1.6rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                    <h2 id="embargo-title" class="text-[1.6rem] font-semibold text-[#222222]">When should it go live?</h2>
                </div>

                <div class="flex-1 overflow-y-auto px-[2.4rem] md:px-[6.4rem] pt-[2.8rem]">

                    <!-- Airbnb's fixed bar: the month being looked at and the weekdays.
                         The month names below scroll by; when one reaches the top this follows it. -->
                    <div ref="bar" data-embargo-bar>
                        <p class="text-[2.2rem] leading-[2.8rem] font-semibold text-[#222222]">{{ shownMonth }}</p>
                        <div class="mt-[1.2rem] grid grid-cols-7 pb-[1rem] border-b border-[#ebebeb] text-center text-[1.3rem] text-[#6a6a6a]">
                            <span v-for="weekday in WEEKDAYS" :key="weekday">{{ weekday }}</span>
                        </div>
                    </div>
                    <div ref="scroller" class="max-h-[34rem] overflow-y-auto border-b border-[#ebebeb]" @scroll="followMonth">
                        <VueDatePicker
                            v-model="day"
                            :enable-time-picker="false"
                            :dark="false"
                            :timezone="timezone"
                            :start-date="new Date()"
                            focus-start-date
                            :min-date="today"
                            disable-month-select
                            disable-year-select
                            :multi-calendars="monthCount"
                            multi-calendars-solo
                            inline
                            auto-apply
                            month-name-format="long"
                            hide-offset-dates
                            :month-change-on-scroll="false"
                            week-start="0"
                            class="embargo-calendar modal-calendar"
                        />
                        <!-- Six months at a time; a later go-live date is a tap away (up to two years). -->
                        <button v-if="monthCount < MAX_MONTHS" type="button" class="w-full py-[1.6rem] text-[1.4rem] font-semibold underline text-[#222222]" data-embargo-more-months @click="showMoreMonths">
                            Show more months
                        </button>
                    </div>

                    <label class="mt-[3.2rem] mb-[3.2rem] flex items-center justify-between gap-4 px-[1.6rem] h-[5.6rem] rounded-[1.2rem] border border-[#b0b0b0] focus-within:border-[#222222] focus-within:shadow-[0_0_0_1px_#222222]">
                        <span class="text-[1.4rem] text-[#6a6a6a]">Time</span>
                        <select v-model="time" class="flex-1 min-w-0 text-right text-[1.6rem] font-medium text-[#222222] bg-transparent border-0 shadow-none outline-none focus:outline-none focus:ring-0 focus:ring-offset-0 focus:border-0 focus:shadow-none cursor-pointer" data-embargo-time>
                            <option v-for="option in times" :key="option" :value="option">{{ formatEmbargoTime(option) }}</option>
                        </select>
                    </label>
                </div>

                <div class="flex items-center justify-between gap-4 px-[2.4rem] md:px-[6.4rem] py-[2rem] border-t border-[#ebebeb] flex-shrink-0">
                    <button type="button" class="text-[1.6rem] font-semibold text-[#222222] underline px-2 py-2 rounded-lg hover:bg-[#f7f7f7]" @click="$emit('cancel')">Cancel</button>
                    <button type="button" :disabled="!day || isPast" class="h-[4.8rem] px-[2.4rem] rounded-[0.8rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold disabled:bg-[#dddddd] disabled:cursor-not-allowed" data-embargo-save @click="save">
                        {{ !day ? 'Pick a day' : (isPast ? 'Pick a later time' : `Go live ${summary}`) }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import moment from 'moment-timezone';
import '@vuepic/vue-datepicker/dist/main.css';
import { useMonthBar } from '@/composables/useMonthBar';
import { embargoDateForAPI, embargoDateToDate, embargoTimeOf, formatEmbargoTime, normalizeDateToTimezone } from '@/composables/dateUtils';

const props = defineProps({
    // As stored: "YYYY-MM-DD HH:mm:ss" in the event's timezone, or null.
    embargoDate: { type: String, default: null },
    timezone: { type: String, required: true },
});
const emit = defineEmits(['save', 'cancel']);

const today = new Date();

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const scroller = ref(null);
const bar = ref(null);
const { shownMonth, followMonth, hideFirstName } = useMonthBar(scroller, bar);

// The calendar draws its months after mounting.
onMounted(() => nextTick(() => {
    hideFirstName();
    followMonth();
}));

const day = ref(props.embargoDate ? embargoDateToDate(props.embargoDate, props.timezone) : null);

// Months shown: six, or enough to reach a date already picked further out.
const MAX_MONTHS = 24;
const monthsTo = (date) => (date ? (date.getFullYear() - new Date().getFullYear()) * 12 + date.getMonth() - new Date().getMonth() + 1 : 0);
const monthCount = ref(Math.min(MAX_MONTHS, Math.max(6, monthsTo(day.value))));
const showMoreMonths = () => {
    monthCount.value = Math.min(MAX_MONTHS, monthCount.value + 6);
    nextTick(() => {
        hideFirstName();
        followMonth();
    });
};
const time = ref(embargoTimeOf(props.embargoDate));

// Every half hour of the day.
const times = Array.from({ length: 48 }, (_, index) => `${String(Math.floor(index / 2)).padStart(2, '0')}:${index % 2 ? '30' : '00'}`);

const summary = computed(() => {
    const picked = normalizeDateToTimezone(day.value, props.timezone);
    if (!picked) return '';
    const [year, month, date] = picked.split('-').map(Number);
    const label = new Date(Date.UTC(year, month - 1, date, 12)).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
    // The zone's short name on that day (PST or PDT), or its offset where it has none.
    const zone = moment.tz(`${picked} ${time.value}`, 'YYYY-MM-DD HH:mm', props.timezone).format('z');
    return `${label}, ${formatEmbargoTime(time.value)} (${zone})`;
});

// The day and time must still be ahead where the event is (the server refuses a past one).
const isPast = computed(() => {
    const picked = normalizeDateToTimezone(day.value, props.timezone);
    return Boolean(picked) && !moment.tz(`${picked} ${time.value}`, 'YYYY-MM-DD HH:mm', props.timezone).isAfter(moment());
});

// Escape closes; focus moves into the pop-up and back where it was after.
const dialog = ref(null);
let returnFocus = null;
const onKeydown = (event) => {
    if (event.key === 'Escape') emit('cancel');
};
onMounted(() => {
    returnFocus = document.activeElement;
    document.addEventListener('keydown', onKeydown);
    nextTick(() => dialog.value?.focus());
});
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    returnFocus?.focus?.();
});

const save = () => {
    if (!day.value || isPast.value) return;
    emit('save', embargoDateForAPI(day.value, props.timezone, time.value));
};
</script>

<style>
/* The Dates pages make each month's header sticky with its own weekday row.
   Here, as on Airbnb, one fixed bar above the box (month + weekdays) does
   that job: the month names below just scroll by, with no weekday rows or
   arrows of their own. */
.c-embargo .embargo-calendar .dp--header-wrap {
    position: relative !important;
    top: auto !important;
    margin: 2.4rem 0 1.2rem !important;
}
.c-embargo .embargo-calendar .dp--header-wrap::before,
.c-embargo .embargo-calendar .dp--header-wrap::after {
    display: none !important;
}
.c-embargo .embargo-calendar .dp__calendar {
    padding-top: 0 !important;
}
[data-embargo-modal] .embargo-calendar.modal-calendar .dp--header-wrap .dp--arrow-btn-nav,
[data-embargo-modal] .embargo-calendar.modal-calendar .dp--header-wrap .dp__arrow_btn {
    display: none !important;
}
</style>
