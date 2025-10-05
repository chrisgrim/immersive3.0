<template>
    <div class="relative text-1xl font-medium w-full h-[calc(100vh-8rem)] flex flex-col">
        <!-- Main Content Area with separate scrolling -->
        <div class="flex-1 md:flex h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row">
                <!-- Navigation Sidebar with own scroll -->
                <div 
                    class="flex-shrink-0 overflow-y-auto border-r border-gray-200 w-full lg-air:w-[40rem] xl-air:w-[56rem] lg-air:block" 
                    :class="{ 'hidden': currentSection }">
                    <div class="flex items-center justify-center">
                        <NavSidebar 
                            :organizer="organizer"
                            :currentStep="currentStep"
                            @navigate="handleNavigation"
                        />
                    </div>
                </div>

                <!-- Main Content Column -->
                <div 
                    class="md:flex-1 flex-col h-screen md:h-full w-full md:w-auto transition-all duration-300"
                    :class="currentSection ? 'flex' : 'hidden md:flex'">
                    <!-- Mobile back button -->
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
                    <div class="flex-1 overflow-y-auto">
                        <div 
                            class="w-full xl:w-2/3 mx-auto"
                            :class="currentSection ? 'pt-4 md:pt-40 md:pb-40' : 'pt-20 md:pt-40 md:pb-40'">
                            <div class="p-8">
                                <component :is="currentComponent" ref="currentComponentRef" />
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
                                    <LoadingSpinner v-if="isSubmitting" />
                                    {{ isSubmitting ? 'Updating...' : 'Update' }}
                                </div>
                            </button>

                            <!-- Submit button -->
                            <button 
                                v-if="organizer.status === 'n'"
                                @click="handleSubmitClick"
                                :disabled="isSubmitting || isSubmittingEvent"
                                :class="{
                                    'px-6 py-3 rounded-lg transition-colors border border-black': true,
                                    'bg-white text-black hover:bg-gray-100': !isSubmittingEvent,
                                    'bg-gray-300 text-gray-500 cursor-not-allowed': isSubmitting || isSubmittingEvent
                                }"
                            >
                                <div class="flex items-center gap-2">
                                    <LoadingSpinner v-if="isSubmittingEvent" />
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
                    <p class="text-gray-600">Organizer updated successfully</p>
                </div>
            </div>
        </Transition>

        <!-- Error Toast Notification -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="showErrorModal"
                class="fixed top-36 right-4 z-50 bg-white rounded-xl shadow-lg p-4 max-w-sm border"
            >
                <div class="flex items-center gap-3">
                    <svg 
                        class="w-6 h-6 text-red-500 flex-shrink-0" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path 
                            stroke-linecap="round" 
                            stroke-linejoin="round" 
                            stroke-width="2" 
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                    <p class="text-gray-600">{{ errorMessage }}</p>
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
                    <p class="text-gray-500 mb-4">Have you made all your changes to your organizer?</p>
                    
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

        <!-- Mobile Bottom Navigation - Only show on mobile when sidebar is visible -->
        <NavBarMobile 
            v-if="isMobile && !currentSection" 
            :user="user" 
        />
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed } from 'vue';
import NavSidebar from './Pages/navSidebar.vue';
import Name from './Pages/name.vue';
import Image from './Pages/image.vue';
import Social from './Pages/social.vue';
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue';
import NavBarMobile from '../Nav/nav-bar-mobile.vue';

const props = defineProps({
    organizer: {
        type: Object,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
});

const organizer = reactive(props.organizer);
const user = reactive(props.user);
const currentStep = ref('Name');
const isSubmitting = ref(false);
const errors = ref({});
const currentComponentRef = ref(null);
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const errorMessage = ref('');
const currentSection = ref(null);
const showConfirmModal = ref(false);
const isSubmittingEvent = ref(false);

// Define available steps
const steps = ['Name', 'Image', 'Social'];

const components = {
    Name,
    Image,
    Social
};

const isMobile = computed(() => window?.Laravel?.isMobile ?? false);
const currentComponent = computed(() => components[currentStep.value]);

const handleNavigation = (section) => {
    if (window?.Laravel?.isMobile) {
        currentSection.value = section;
        if (section) {
            setStep(section);
        }
    } else {
        setStep(section);
    }
};

const setStep = (step) => {
    if (steps.includes(step)) {
        currentStep.value = step;
    }
};

const saveChanges = async () => {
    try {
        const isValid = await currentComponentRef.value.isValid();
        if (!isValid) return;

        const submitData = await currentComponentRef.value.submitData();
        if (!submitData) return;
        
        isSubmitting.value = true;
        errors.value = {};
        errorMessage.value = '';
        
        const response = await axios.post(`/organizers/${organizer.slug}`, submitData);
        
        if (response.data.organizer) {
            // If name/slug changed, update the URL without redirecting
            if (response.data.organizer.slug !== organizer.slug) {
                const newUrl = `/organizers/${response.data.organizer.slug}/edit`;
                window.history.pushState({}, '', newUrl);
            }
            
            Object.assign(organizer, response.data.organizer);
            showSuccessModal.value = true;
            setTimeout(() => {
                showSuccessModal.value = false;
            }, 3000);
        }
    } catch (error) {
        console.error('Error:', error);
        
        // Extract and display validation errors
        if (error.response?.status === 422 && error.response?.data?.errors) {
            errors.value = error.response.data.errors;
            
            // Get the first error message for display
            const firstErrorField = Object.keys(errors.value)[0];
            errorMessage.value = errors.value[firstErrorField][0];
        } else if (error.response?.data?.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'Failed to update organizer. Please try again.';
        }
        
        showErrorModal.value = true;
        setTimeout(() => {
            showErrorModal.value = false;
        }, 5000);
    } finally {
        isSubmitting.value = false;
    }
};

const handleSubmitClick = () => {
    showConfirmModal.value = true;
};

const handleConfirmedSubmit = async () => {
    showConfirmModal.value = false;
    // First save any changes, then submit
    await saveChanges();
    await submitOrganizer();
};

const submitOrganizer = async () => {
    try {
        isSubmittingEvent.value = true;
        const response = await axios.post(`/organizers/${organizer.slug}/submit`);
        
        if (response.data.organizer) {
            window.location.href = `/organizers/${response.data.organizer.slug}`;
        }
    } catch (error) {
        console.error('Error:', error);
    } finally {
        isSubmittingEvent.value = false;
    }
};

// Provide shared state
provide('organizer', organizer);
provide('user', user);
provide('isSubmitting', isSubmitting);
provide('errors', errors);
</script>

<style scoped>
a, button {
    transition: all 0.2s ease-in-out;
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>