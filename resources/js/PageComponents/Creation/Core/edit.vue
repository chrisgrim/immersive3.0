<template>
    <div class="relative text-1xl font-medium w-full h-[calc(100vh-8rem)] flex flex-col">

        <!-- Main Content Area with separate scrolling -->
        <div class="flex-1 flex h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row">
                <!-- Navigation Sidebar with own scroll -->
                <div 
                    class="flex-shrink-0 overflow-y-auto border-r border-gray-200 w-full lg-air:w-[40rem] xl-air:w-[56rem] lg-air:block transition-all duration-300"
                    :class="{ 
                        'hidden': currentSection,
                        'md:!hidden': isSidebarHidden && currentStep === 'Dates'
                    }">
                    <div class="flex items-center justify-center">
                        <NavSidebar 
                            :event="event"
                            :currentStep="currentStep"
                            @navigate="handleNavigation"
                        />
                    </div>
                </div>

                <!-- Main Content Column -->
                <div 
                    class="flex-1 flex-col h-full w-full md:w-auto transition-all duration-300"
                    :class="[
                        currentSection ? 'flex' : 'hidden md:flex',
                        { 'md:!w-full': isSidebarHidden && currentStep === 'Dates' }
                    ]">
                    <!-- Mobile back button - changed to relative positioning -->
                    <div 
                        v-if="isMobile && currentSection" 
                        class="relative bg-white px-8 pt-12 pb-4"
                    >
                        <div class="flex items-center gap-4">
                            <button 
                                @click="handleNavigation(null)"
                                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                            >
                                <svg 
                                    class="w-8 h-8" 
                                    viewBox="0 0 24 24" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    stroke-width="2" 
                                    stroke-linecap="round" 
                                    stroke-linejoin="round"
                                >
                                    <path d="M19 12H5"/>
                                    <path d="M12 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Scrollable Component Area -->
                    <div class="flex-1 overflow-y-auto relative" ref="containerRef">
                        <div 
                            class="w-full xl:w-2/3 mx-auto"
                            :class="currentSection ? 'pt-4 md:pt-40 md:pb-40' : 'pt-20 md:pt-40 md:pb-40'">
                            <div class="p-8">
                                <component 
                                    :is="currentComponent" 
                                    ref="currentComponentRef"
                                    @toggle-sidebar="toggleSidebar"
                                />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fixed Footer -->
                    <div class="flex border-t border-gray-200 bg-white h-32 justify-end items-center">
                        <div class="px-8 py-6 flex gap-4">

                            <!-- Update button -->
                            <button 
                                @click="saveChanges"
                                :disabled="isSubmitting || isSubmittingEvent"
                                :class="{
                                    'px-6 py-3 rounded-lg transition-colors': true,
                                    'bg-black text-white hover:bg-gray-800': !isSubmitting,
                                    'bg-gray-300 text-gray-500 cursor-not-allowed': isSubmitting || isSubmittingEvent
                                }"
                            >
                                <div class="flex items-center gap-2">
                                    <svg 
                                        v-if="isSubmitting"
                                        class="animate-spin h-5 w-5" 
                                        xmlns="http://www.w3.org/2000/svg" 
                                        fill="none" 
                                        viewBox="0 0 24 24"
                                    >
                                        <circle 
                                            class="opacity-25" 
                                            cx="12" 
                                            cy="12" 
                                            r="10" 
                                            stroke="currentColor" 
                                            stroke-width="4"
                                        />
                                        <path 
                                            class="opacity-75" 
                                            fill="currentColor" 
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{ isSubmitting ? 'Updating...' : 'Update' }}
                                </div>
                            </button>

                            <!-- Resubmit button -->
                            <button 
                                v-if="event.status === 'n'"
                                @click="handleSubmitClick"
                                :disabled="isSubmitting || isSubmittingEvent"
                                :class="{
                                    'px-6 py-3 rounded-lg transition-colors border border-black': true,
                                    'bg-white text-black hover:bg-gray-100': !isSubmittingEvent,
                                    'bg-gray-300 text-gray-500 cursor-not-allowed': isSubmitting || isSubmittingEvent
                                }"
                            >
                                <div class="flex items-center gap-2">
                                    <svg 
                                        v-if="isSubmittingEvent"
                                        class="animate-spin h-5 w-5" 
                                        xmlns="http://www.w3.org/2000/svg" 
                                        fill="none" 
                                        viewBox="0 0 24 24"
                                    >
                                        <circle 
                                            class="opacity-25" 
                                            cx="12" 
                                            cy="12" 
                                            r="10" 
                                            stroke="currentColor" 
                                            stroke-width="4"
                                        />
                                        <path 
                                            class="opacity-75" 
                                            fill="currentColor" 
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{ isSubmittingEvent ? 'Submitting...' : 'Resubmit' }}
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Toast Notification -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="showSuccessModal"
                class="fixed top-36 right-4 z-50 bg-white rounded-xl shadow-lg p-4 max-w-sm border"
            >
                <div class="flex items-center gap-3">
                    <svg 
                        class="w-6 h-6 text-green-500 flex-shrink-0" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path 
                            stroke-linecap="round" 
                            stroke-linejoin="round" 
                            stroke-width="2" 
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    <p class="text-gray-600">Event updated successfully</p>
                </div>
            </div>
        </Transition>

        <!-- Confirmation Modal -->
        <Teleport to="body">
            <div v-if="showConfirmModal" 
                 class="fixed inset-0 flex items-center justify-center z-50"
            >
                <div class="absolute inset-0 bg-black/50" @click="showConfirmModal = false"></div>
                <div class="relative bg-white rounded-xl p-12 max-w-xl w-full mx-4">
                    <h3 class="text-xl font-medium mb-2">Ready to Submit?</h3>
                    <p class="text-gray-500 mb-4">Have you made all your changes to your event?</p>
                    
                    <div class="flex justify-end gap-3">
                        <button 
                            @click="showConfirmModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="handleConfirmedSubmit"
                            class="px-4 py-2 text-white bg-black rounded-lg hover:bg-gray-800"
                        >
                            Submit
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed, onMounted, nextTick } from 'vue';
import NavSidebar from './Pages/navSidebar.vue';
import EventType from './Pages/event-type.vue';
import Category from './Pages/category.vue';
import Genres from './Pages/genres.vue';
import Location from './Pages/location.vue';
import Remote from './Pages/remote.vue';
import Description from './Pages/description.vue';
import Name from './Pages/name.vue';
import Dates from './Pages/dates.vue';
import Tickets from './Pages/tickets.vue';
import Images from './Pages/images.vue';
import Advisories from './Pages/advisories.vue';
import Content from './Pages/Advisories/content.vue';
import Mobility from './Pages/Advisories/mobility.vue';
import Review from './Pages/review.vue';

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

const event = reactive(props.event);
const user = reactive(props.user);
const currentStep = ref('Name');
const isSubmitting = ref(false);
const isSubmittingEvent = ref(false);
const errors = ref({});
const currentComponentRef = ref(null);
const isSidebarHidden = ref(false);
const containerWidth = ref(0);
const containerRef = ref(null);

// Get isMobile from window.Laravel
const isMobile = computed(() => window?.Laravel?.isMobile ?? false);
const currentSection = ref(null);

// Simplified steps for edit mode
const steps = computed(() => event.attendance_type_id === 1 ? 
    ['Name', 'Category', 'Genres', 'Location', 'Description', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility'] :
    ['Name', 'Category', 'Genres', 'Remote', 'Description', 'Dates', 'Tickets', 'Images', 'Advisories', 'Content', 'Mobility']
);

const components = {
    EventType,
    Category,
    Genres,
    Location,
    Remote,
    Description,
    Name,
    Dates,
    Tickets,
    Images,
    Advisories,
    Content,
    Mobility,
    Review
};

const currentComponent = computed(() => components[currentStep.value]);
const currentStepIndex = computed(() => steps.value.indexOf(currentStep.value));
const progress = computed(() => ((currentStepIndex.value + 1) / steps.value.length) * 100);
const isComponentReady = ref(true);

const setStep = (step) => {
    // Capitalize first letter to match component names
    const formattedStep = step.charAt(0).toUpperCase() + step.slice(1).toLowerCase();
    if (steps.value.includes(formattedStep)) {
        currentStep.value = formattedStep;
    }
};

const goToPrevious = () => {
    const currentIndex = steps.value.indexOf(currentStep.value);
    if (currentIndex > 0) {
        currentStep.value = steps.value[currentIndex - 1];
    }
};

const showSuccessModal = ref(false);
const showConfirmModal = ref(false);

const saveChanges = async () => {
    try {
        const isValid = await currentComponentRef.value.isValid();
        if (!isValid) return;

        const submitData = await currentComponentRef.value.submitData();
        if (!submitData) return;
        
        isSubmitting.value = true;
        
        const response = await axios.post(`/api/hosting/event/${event.slug}`, submitData);
        
        if (response.data.event) {
            Object.assign(event, response.data.event);
            
            // For Images component, refresh state after submission
            if (currentStep.value === 'Images' && currentComponentRef.value) {
                if (typeof currentComponentRef.value.updateFromServer === 'function') {
                    currentComponentRef.value.updateFromServer(response.data.event);
                } else if (typeof currentComponentRef.value.resetDeletedImages === 'function') {
                    currentComponentRef.value.resetDeletedImages();
                }
            }
            
            showSuccessModal.value = true;
            setTimeout(() => {
                showSuccessModal.value = false;
            }, 3000);
        }
    } catch (error) {
        console.error('Error:', error);
    } finally {
        isSubmitting.value = false;
    }
};

const handleNavigation = (section) => {
    if (isMobile.value) {
        currentSection.value = section;
        if (section) {
            setStep(section);
        }
    } else {
        setStep(section);
    }
};

// Provide shared state
provide('event', event);
provide('user', user);
provide('isSubmitting', isSubmitting);
provide('errors', errors);
provide('setComponentReady', (ready) => {
    isComponentReady.value = ready;
});

const submitEvent = async () => {
    try {
        isSubmittingEvent.value = true;
        const response = await axios.post(`/hosting/event/${event.slug}/submit`);
        window.location.href = '/hosting/events?submitted=' + encodeURIComponent(event.name);
    } catch (error) {
        console.error('Submission error:', error);
    } finally {
        isSubmittingEvent.value = false;
    }
};

const handleSubmitClick = () => {
    showConfirmModal.value = true;
};

const handleConfirmedSubmit = async () => {
    showConfirmModal.value = false;
    await submitEvent();
};

// Measure container width on mount and window resize
const updateContainerWidth = () => {
    nextTick(() => {
        if (containerRef.value) {
            containerWidth.value = containerRef.value.clientWidth;
        }
    });
};

// Toggle sidebar method
const toggleSidebar = () => {
    isSidebarHidden.value = !isSidebarHidden.value;
    
    // Dispatch an event that dates.vue can listen for
    window.dispatchEvent(new CustomEvent('toggle-sidebar', {
        detail: { hidden: isSidebarHidden.value }
    }));
    
    // Update container width after the sidebar transition completes
    setTimeout(updateContainerWidth, 300);
};

// Provide container width to child components
provide('parentContainerWidth', containerWidth);

// Event listener for sidebar toggle
onMounted(() => {
    updateContainerWidth();
    window.addEventListener('resize', updateContainerWidth);
});
</script>

<style scoped>
a, button {
    transition: all 0.2s ease-in-out;
}

/* Add transition for modal */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

/* Add transition for sidebar toggling */
.md\:flex-row > div {
    transition: all 0.3s ease-in-out;
}
</style>