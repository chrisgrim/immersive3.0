<template>
    <div class="relative text-1xl font-medium w-full h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row">
                <!-- Navigation Sidebar -->
                <div 
                    class="flex-shrink-0 overflow-y-auto border-r border-gray-200 w-full lg-air:w-[31rem] xl-air:w-[50rem] lg-air:block" 
                    :class="{ 'hidden': currentSection }">
                    <div class="flex items-center justify-center">
                        <NavSidebar 
                            :community="community"
                            :is-mobile="isMobile"
                            :active-section="currentSection"
                            @navigate="handleNavigation"
                        />
                    </div>
                </div>

                <!-- Main Content Column -->
                <div 
                    class="flex-1 flex-col h-full w-full md:w-auto"
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
                        <div class="px-8 py-6">
                            <button 
                                @click="saveChanges"
                                :disabled="isSubmitting"
                                :class="{
                                    'px-6 py-3 rounded-lg transition-colors': true,
                                    'bg-black text-white hover:bg-gray-800': !isSubmitting,
                                    'bg-gray-300 text-gray-500 cursor-not-allowed': isSubmitting
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
                    <p class="text-gray-600">Community updated successfully</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed, onMounted, onUnmounted } from 'vue';
import NavSidebar from './Pages/navSidebar.vue';
import Name from './Pages/name.vue';
import Description from './Pages/description.vue';
import Image from './Pages/image.vue';
import Curators from './Pages/curators.vue';

const props = defineProps({
    community: {
        type: Object,
        required: true
    }
});

const community = reactive(props.community);

// Create a reactive user object from window.Laravel.user
const user = reactive({
    id: window.Laravel?.user?.id,
    isModerator: window.Laravel?.user?.isModerator,
    isAdmin: window.Laravel?.user?.isAdmin
});

const currentSection = ref(window.Laravel.isMobile ? null : 'Name');
const isSubmitting = ref(false);
const errors = ref({});
const currentComponentRef = ref(null);
const showSuccessModal = ref(false);
const isMobile = ref(window.Laravel.isMobile);

// Define available steps
const steps = ['Name', 'Description', 'Image', 'Curators'];

const components = {
    Name,
    Description,
    Image,
    Curators
};

const currentComponent = computed(() => components[currentSection.value]);

const handleNavigation = (section) => {
    if (isMobile.value) {
        currentSection.value = section;
    } else {
        currentSection.value = section;
    }
};

const saveChanges = async () => {
    try {
        const isValid = await currentComponentRef.value.isValid();
        if (!isValid) return;

        const submitData = await currentComponentRef.value.submitData();
        if (!submitData) return;
        
        isSubmitting.value = true;
        
        // Use different endpoints based on the type of update
        const endpoint = currentSection.value === 'Curators' 
            ? `/communities/${community.slug}/curators`
            : `/communities/${community.slug}`;
            
        const response = await axios.post(endpoint, submitData);
        
        if (response.data) {
            Object.assign(community, response.data);
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

onMounted(() => {
    // No need for resize listener anymore
});

onUnmounted(() => {
    // No need to remove listener
});

// Provide shared state
provide('community', community);
provide('isSubmitting', isSubmitting);
provide('errors', errors);
provide('user', user);
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