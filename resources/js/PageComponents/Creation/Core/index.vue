<template>
    <div class="relative text-1xl font-medium w-full h-screen flex flex-col">
        <!-- Top Navigation Bar -->
        <div class="h-24 md:h-[8.8rem] fixed md:relative top-0 left-0 right-0 w-full flex-none bg-white z-[1000]">
            <div class="mx-auto px-8 md:px-[2.6rem] h-full flex justify-between items-center">
                <!-- Left: EI Logo/Link -->
                <a :href="listingsUrl" class="flex flex-col items-center gap-1" :class="isHome ? 'text-primary font-medium' : 'text-neutral-600'">
                    <img src="/storage/website-files/Everything_Immersive_logo_Short.png" alt="EI" class="w-12 h-12" />
                </a>

                <!-- Right: Questions? and Save & exit, Airbnb's outlined pills -->
                <div class="flex items-center gap-3">
                    <!-- Tips: the Helper Boxes under each question. On for a first listing
                         or after a month away; this switches them for this listing. -->
                    <button
                        type="button"
                        :aria-pressed="tips.on"
                        :aria-label="tips.on ? 'Hide tips' : 'Show tips'"
                        class="inline-flex items-center gap-[0.6rem] h-[3.6rem] md:h-[4rem] px-[1.1rem] md:px-[1.5rem] rounded-full border bg-white text-[1.3rem] md:text-[1.4rem] font-semibold text-[#222222] transition-colors"
                        :class="tips.on ? 'border-[#222222] shadow-[inset_0_0_0_1px_#222222]' : 'border-[#dddddd] hover:border-[#222222]'"
                        data-tips-toggle
                        @click="toggleTips"
                    >
                        <svg class="w-[1.8rem] h-[1.8rem]" :fill="tips.on ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a6 6 0 00-3.6 10.8c.7.5 1.1 1.3 1.1 2.2h5c0-.9.4-1.7 1.1-2.2A6 6 0 0012 3z" />
                            <path stroke-linecap="round" d="M9.5 18.5h5M10.5 21h3" fill="none" />
                        </svg>
                        <span class="hidden sm:inline">Tips</span>
                    </button>

                    <a 
                        href="mailto:support@everythingimmersive.com?subject=Event Creation Question"
                        class="inline-flex items-center h-[3.6rem] md:h-[4rem] px-[1.3rem] md:px-[1.7rem] rounded-full border border-[#dddddd] hover:border-[#222222] bg-white text-[1.3rem] md:text-[1.4rem] font-semibold text-[#222222] transition-colors"
                    >
                        Questions?
                    </a>
                    <button
                        type="button"
                        :disabled="isSubmitting"
                        class="inline-flex items-center h-[3.6rem] md:h-[4rem] px-[1.3rem] md:px-[1.7rem] rounded-full border border-[#dddddd] hover:border-[#222222] bg-white text-[1.3rem] md:text-[1.4rem] font-semibold text-[#222222] transition-colors disabled:opacity-50"
                        @click="saveAndExit"
                    >
                        {{ exiting ? 'Saving…' : 'Save & exit' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Why Save & exit (or the show run picker) could not save: under the top bar, on every screen. -->
        <div v-if="exitError" class="fixed md:absolute top-24 md:top-[8.8rem] left-0 right-0 z-[1001] flex justify-center px-4 pointer-events-none">
            <p class="pointer-events-auto mt-2 px-5 py-3 rounded-full bg-[#222222] text-white text-[1.3rem] shadow-[0_4px_16px_rgba(0,0,0,0.2)]" role="alert">
                {{ exitError }}
                <button type="button" class="ml-3 underline" data-exit-error-ok @click="dismissExit">OK</button>
            </p>
        </div>

        <!-- Main Content Area (with proper scrolling). Typing anywhere clears the message above. -->
        <!-- Hidden while Save & exit checks the step, so an unfinished step's red
             hints never flash on the way out; back again if it has to stay. -->
        <div class="md:flex-1 md:overflow-y-auto pt-24 md:pt-0 pb-40 md:pb-0" :class="{ 'opacity-0': exiting }" @input="dismissExit">
            <div 
                class="mx-auto md:min-h-full md:flex"
                :class="currentStep !== 'Dates' ? 'max-w-screen-xl' : ''"
            >
                <div :class="['w-full mx-auto', containerWidthClass]">
                    <div class="h-full md:flex px-8 pb-8">
                        <component :is="currentComponent" ref="currentComponentRef" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation (fixed at bottom), Airbnb's: a progress bar in three
             parts, Back as plain text, the show run picker beside it, Next. -->
        <div class="fixed md:relative bottom-0 left-0 right-0 w-full flex-none bg-white z-[1000]">
            <div class="w-full flex gap-[0.6rem]">
                <div v-for="(part, index) in progressParts" :key="index" class="flex-1 h-[0.6rem] bg-[#ebebeb] overflow-hidden">
                    <div class="h-full bg-[#222222] transition-all duration-300" :style="{ width: `${part}%` }" />
                </div>
            </div>

            <div class="flex justify-between items-center gap-4 px-6 py-6 md:px-[2.6rem] md:py-[1.6rem] mx-auto">
                <div class="flex items-center gap-[2.4rem] min-w-0">
                    <button
                        v-if="!isFirstStep && currentStep !== 'EventType'"
                        @click="goToPrevious"
                        class="flex-shrink-0 px-[1rem] py-[1rem] -ml-[1rem] rounded-lg text-[1.6rem] font-semibold text-[#222222] hover:underline hover:bg-[#f7f7f7]"
                    >
                        Back
                    </button>
                    <ShowRunPicker />
                </div>

                <button
                    @click="goToNext"
                    :disabled="isSubmitting || !isComponentReady"
                    :class="[
                        'flex-shrink-0 h-[4.8rem] px-[3.8rem] rounded-[0.8rem] text-[1.6rem] font-semibold transition-colors flex items-center gap-2',
                        !isSubmitting && isComponentReady ? 'bg-[#222222] text-white hover:bg-black' : 'bg-[#dddddd] text-white cursor-not-allowed'
                    ]"
                >
                    <svg 
                        v-if="isSubmitting && !exiting"
                        class="animate-spin h-5 w-5" 
                        xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    {{ isSubmitting && !exiting ? (isLastStep ? 'Finishing...' : 'Saving...') : (isLastStep ? 'Submit' : 'Next') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed, onMounted } from 'vue';
import EventType from './Pages/event-type.vue';
import Category from './Pages/category.vue';
import Genres from './Pages/genres.vue';
import Location from './Pages/location.vue';
import Remote from './Pages/remote.vue'; // Import Remote component
import Description from './Pages/description.vue';
import Name from './Pages/name.vue';
import Dates from './Pages/dates.vue';
import Tickets from './Pages/tickets.vue';
import Images from './Pages/images.vue';
import Advisories from './Pages/advisories.vue';
import Content from './Pages/Advisories/content.vue';
import Mobility from './Pages/Advisories/mobility.vue';
import Review from './Pages/review.vue';
import ShowRunPicker from './Components/show-run-picker.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
});

// The listings of this event's own organization (a member switches to it, staff just look).
const listingsUrl = computed(() => (props.event?.organizer?.slug ? `/hosting/events?organizer=${props.event.organizer.slug}` : '/hosting/events'));


const event = reactive(props.event);
const user = reactive(props.user);
const currentStep = ref('EventType');
const isSubmitting = ref(false);
const errors = ref({});
const currentComponentRef = ref(null);
const isHome = ref(false); // Add missing isHome property

const stepsWithLocation = ['EventType', 'Name', 'Category', 'Genres', 'Location', 'Description', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility', 'Review'];
const stepsWithoutLocation = ['EventType', 'Name', 'Category', 'Genres', 'Remote', 'Description', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility', 'Review'];

const components = {
    EventType,
    Category,
    Genres,
    Location,
    Remote, // Add Remote component
    Description,
    Name,
    Dates,
    Tickets,
    Images,
    Advisories,
    Content,
    Mobility,
    Review  // Add this line
};

const steps = computed(() => (event.attendance_type_id === 1 || event.hasLocation) ? stepsWithLocation : stepsWithoutLocation);

const currentComponent = computed(() => components[currentStep.value]);

const currentStepIndex = computed(() => steps.value.indexOf(currentStep.value));
const isFirstStep = computed(() => currentStepIndex.value === 0);
const isLastStep = computed(() => currentStepIndex.value === steps.value.length - 1);
const progress = computed(() => ((currentStepIndex.value + 1) / steps.value.length) * 100);

// Airbnb's bar in three parts: the basics, dates to pictures, then advisories and review.
const PHASES = [
    ['EventType', 'Name', 'Category', 'Genres', 'Location', 'Remote', 'Description'],
    ['Dates', 'Tickets', 'Images'],
    ['Advisories', 'Content', 'Mobility', 'Review'],
];
const progressParts = computed(() => PHASES.map((phase) => {
    const inPhase = phase.filter((step) => steps.value.includes(step));
    const done = inPhase.filter((step) => steps.value.indexOf(step) <= currentStepIndex.value).length;
    return inPhase.length ? (done / inPhase.length) * 100 : 0;
}));
const isComponentReady = ref(true); // Default to true

const containerWidthClass = computed(() => {
    const componentName = currentComponent.value.__name?.toLowerCase();
    
    if (componentName === 'dates') return 'lg:w-full pt-40 pb-0';
    if (componentName === 'images') return 'lg:w-2/3 pt-40 md:pb-20';
    return 'lg:w-1/2 pt-40 md:pb-20';
});

const goToPrevious = () => {
    if (!isFirstStep.value) {
        const prevStep = steps.value[currentStepIndex.value - 1];
        setStep(prevStep);
    }
};

// Step mapping with numeric values for easy comparison
const STEP_MAP = {
    'EventType': '1',
    'Name': '2',
    'Category': '3',
    'Genres': '4',
    'Location': '5',
    'Remote': '5',
    'Description': '6',
    'Dates': '7',
    'Tickets': '8',
    'Images': '9',
    'Advisories': 'A',
    'Content': 'B',
    'Mobility': 'C',
    'Review': 'D'
};

provide('setComponentReady', (ready) => {
    isComponentReady.value = ready;
});

// Reverse mapping for initialization
const REVERSE_STEP_MAP = Object.fromEntries(
    Object.entries(STEP_MAP).map(([key, value]) => [value, key])
);

// Returns true when the browser is on its way to the events page
const submitEvent = async () => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(`/hosting/event/${event.slug}/submit`);
        window.location.href = '/hosting/events?submitted=' + encodeURIComponent(event.name);
        return true;
    } catch (error) {
        console.error('Submission error:', error);
        return false;
    }
};

// Save what is on the current step without moving on: for Save & exit, and
// for the show run picker before it reloads the page. Answers 'saved',
// 'invalid' (the step shows its own message), 'duplicate' (the Name step shows
// its warning; saving again goes through) or 'refused' (exitError says why).
// The message under the top bar: goes by itself after a few seconds, on OK,
// on typing, and on any step change.
const exitError = ref('');
let exitTimer = null;
const showExit = (text) => {
    exitError.value = text;
    clearTimeout(exitTimer);
    exitTimer = setTimeout(() => { exitError.value = ''; }, 6000);
};
const dismissExit = () => {
    clearTimeout(exitTimer);
    exitError.value = '';
};
const saveCurrentStep = async () => {
    dismissExit();
    if (isLastStep.value || !currentComponentRef.value) return 'saved';
    try {
        // Inside the try: on the Location step checking can itself call the server.
        if (!(await currentComponentRef.value.isValid?.())) return 'invalid';
        const submitData = await currentComponentRef.value.submitData();
        if (currentStep.value === 'Name' && currentComponentRef.value.duplicateAcknowledged) {
            submitData instanceof FormData ? submitData.append('acknowledge_duplicate', '1') : (submitData.acknowledge_duplicate = true);
        }
        const response = await axios.post(`/api/hosting/event/${event.slug}`, submitData);
        if (response.data.event) Object.assign(event, response.data.event);
        return 'saved';
    } catch (error) {
        if (currentStep.value === 'Name' && error.response?.status === 409 && currentComponentRef.value.handleDuplicateError?.(error)) {
            return 'duplicate';
        }
        errors.value = error.response?.data?.errors ?? (error.response?.data?.message ? { general: [error.response.data.message] } : {});
        showExit(`Could not save: ${Object.values(error.response?.data?.errors ?? {}).flat()[0] ?? error.response?.data?.message ?? 'please try again.'}`);
        return 'refused';
    }
};
provide('saveCurrentStep', saveCurrentStep);

// Save & exit: the draft already exists, so this step is saved when it is
// filled in, and left for later when it is not (as Add a venue does). Only a
// duplicate name to confirm, or a save the server refuses, keeps you here,
// with the reason showing.
const exiting = ref(false);
const saveAndExit = async () => {
    if (isSubmitting.value) return;
    exiting.value = true;
    isSubmitting.value = true;
    let leaving = false;
    try {
        const result = await saveCurrentStep();
        // Unfinished is fine to leave; a step that tried to save something and
        // failed (adding a new venue) keeps you here with the reason.
        const kept = result === 'invalid' ? currentComponentRef.value?.keepsOnExit?.() : null;
        if (kept) {
            showExit(`Could not save: ${kept}`);
        } else if (result === 'saved' || result === 'invalid') {
            leaving = true;
            window.location.href = listingsUrl.value;
        }
    } finally {
        // Never stuck: whatever went wrong, the buttons work again.
        if (!leaving) {
            exiting.value = false;
            isSubmitting.value = false;
        }
    }
};

const goToNext = async () => {
    if (isSubmitting.value) return;
    dismissExit();
    isSubmitting.value = true;
    // Stays true once the submit redirect starts, so the button stays busy while the next page loads
    let redirecting = false;
    try {
        const isValid = await currentComponentRef.value.isValid();
        if (!isValid) return;

        if (isLastStep.value) {
            redirecting = await submitEvent();
            return;
        }

        const submitData = await currentComponentRef.value.submitData();

        // If user acknowledged duplicate name warning, include flag so backend skips the check
        if (currentStep.value === 'Name' && currentComponentRef.value.duplicateAcknowledged) {
            if (submitData instanceof FormData) {
                submitData.append('acknowledge_duplicate', '1');
            } else {
                submitData.acknowledge_duplicate = true;
            }
        }

        if (!event.status || (event.status !== 'p' && event.status !== 'e')) {
            const currentStepValue = STEP_MAP[currentStep.value];
            const existingStepValue = event.status || '0';
            
            const statusToSet = currentStepValue > existingStepValue 
                ? currentStepValue 
                : existingStepValue;
            
            // Handle both FormData and regular objects
            if (submitData instanceof FormData) {
                submitData.append('status', statusToSet);
            } else {
                submitData.status = statusToSet;
            }
        }

        const response = await axios.post(`/api/hosting/event/${event.slug}`, submitData);
        
        if (response.data.event) {
            Object.assign(event, response.data.event);
        }

        errors.value = {};
        const nextStep = steps.value[currentStepIndex.value + 1];
        setStep(nextStep);
    } catch (error) {
        console.error('Error:', error);
        
        // Check if it's a 409 Conflict due to duplicate name
        if (currentStep.value === 'Name' &&
            error.response?.status === 409 &&
            currentComponentRef.value.handleDuplicateError) {

            // Let the Name component handle the duplicate error (shows warning)
            // User can click Next again to proceed — acknowledge_duplicate flag will be sent
            const handled = currentComponentRef.value.handleDuplicateError(error);
            if (handled) {
                return;
            }
        }
        
        // Handle other errors
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            errors.value = {
                general: [error.response.data.message]
            };
        }
    } finally {
        if (!redirecting) {
            isSubmitting.value = false;
        }
    }
};

const updateUrl = (step) => {
    const url = new URL(window.location);
    url.searchParams.set('view', step);
    window.history.pushState({}, '', url);
};

const setStep = (step) => {
    dismissExit();
    if (steps.value.includes(step)) {
        // Each step starts with Next enabled; the step itself re-gates (duplicate warning,
        // image upload, …) so a greyed-out Next can't leak across steps on Back/Next.
        isComponentReady.value = true;
        currentStep.value = step;
        updateUrl(step);
    }
};

// Helper Boxes (components/helper-tip.vue read this). A choice made with the
// Tips pill is kept for this listing; otherwise they are on for someone's
// first listing, or when their last one was more than a month ago.
const TIPS_AWAY_DAYS = 30;
const tips = reactive({ on: false });
const tipsKey = `ei_tips_${event.id}`;

const toggleTips = () => {
    tips.on = !tips.on;
    try { localStorage.setItem(tipsKey, tips.on ? '1' : '0'); } catch (e) { /* private window */ }
};

const startTips = async () => {
    let saved = null;
    try { saved = localStorage.getItem(tipsKey); } catch (e) { /* private window */ }
    if (saved !== null) {
        tips.on = saved === '1';
        return;
    }
    try {
        const { data } = await axios.get('/hosting/event/user/has-created-events');
        const last = data.lastListingAt ? new Date(data.lastListingAt) : null;
        tips.on = !data.hasCreatedEvents || !last || (Date.now() - last.getTime()) > TIPS_AWAY_DAYS * 86400000;
    } catch (e) {
        tips.on = false;
    }
};

onMounted(() => {
    startTips();
    // Remove the automatic hasLocation override
    // Check for view parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const viewStep = urlParams.get('view');
    
    if (viewStep && steps.value.includes(viewStep)) {
        currentStep.value = viewStep;
    } else {
        // Existing step navigation logic
        if (event.status && REVERSE_STEP_MAP[event.status]) {
            const savedStep = REVERSE_STEP_MAP[event.status];
            const currentIndex = steps.value.indexOf(savedStep);
            
            if (currentIndex !== -1 && currentIndex < steps.value.length - 1) {
                currentStep.value = steps.value[currentIndex + 1];
            } else {
                currentStep.value = savedStep;
            }
        }

        // Update URL to match initial step
        updateUrl(currentStep.value);
    }

    // Force correct component based on attendance_type_id or hasLocation
    if (currentStep.value === 'Remote' && (event.attendance_type_id === 1 || event.hasLocation)) {
        currentStep.value = 'Location';
        updateUrl('Location');
    } else if (currentStep.value === 'Location' && event.attendance_type_id === 2 && !event.hasLocation) {
        currentStep.value = 'Remote';
        updateUrl('Remote');
    }
});

// Provide shared state
provide('event', event);
provide('user', user);
provide('isSubmitting', isSubmitting);
provide('errors', errors);
provide('helperTips', tips);
</script>

<style scoped>
/* Optional: Add transition for hover effects */
a, button {
    transition: all 0.2s ease-in-out;
}
</style>
