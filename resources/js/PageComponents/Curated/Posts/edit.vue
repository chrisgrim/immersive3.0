<template>
    <div class="relative text-1xl font-medium w-full h-[calc(100vh-8rem)] flex flex-col">
        <!-- Main Content Area with separate scrolling -->
        <div class="flex-1 flex h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row">
                <!-- Navigation Sidebar with own scroll -->
                <div 
                    class="flex-shrink-0 overflow-y-auto border-r border-gray-200 w-full lg-air:w-[40rem] xl-air:w-[56rem] lg-air:block" 
                    :class="{ 'hidden': currentSection }">
                    <div class="flex items-center justify-center">
                        <NavSidebar 
                            :post="post"
                            :community="community"
                            :current-step="currentStep"
                            :shelves="shelves"
                            @navigate="handleNavigation"
                            @status-change="handleStatusChange"
                            @shelf-change="handleShelfChange"
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
                                <component 
                                    :is="currentComponent" 
                                    ref="currentComponentRef"
                                    :post="post"
                                    :community="community"
                                    :shelves="shelves" 
                                />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fixed Footer -->
                    <div 
                        v-if="currentStep === 'Name'"
                        class="flex border-t border-gray-200 bg-white h-32 justify-end items-center"
                    >
                        <div class="px-8 py-6 flex gap-4">
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

        <!-- Success Toast -->
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
                    <p class="text-gray-600">Post updated successfully</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, provide, reactive, computed } from 'vue';
import NavSidebar from './Pages/navSidebar.vue';
import Name from './Pages/name.vue';
import Image from './Pages/image.vue';
import Content from './Pages/content.vue';

const props = defineProps({
    value: {
        type: Object,
        required: true
    },
    community: {
        type: Object,
        required: true
    },
    shelves: {
        type: Array,
        default: () => []
    }
});

// Initialize post with the value prop
const post = reactive({
    ...props.value,
    cards: props.value?.cards || [],
    name: props.value?.name || '',
    blurb: props.value?.blurb || ''
});

const currentStep = ref('Content');
const isSubmitting = ref(false);
const errors = ref({});
const currentComponentRef = ref(null);
const showSuccessModal = ref(false);
const currentSection = ref(null);

// Define available steps
const steps = ['Name', 'Image', 'Content'];

const components = {
    Name,
    Image,
    Content
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

const handleStatusChange = async (status) => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(
            `/communities/${props.community.slug}/posts/${post.slug}`,
            { status }
        );
        
        if (response.data) {
            Object.assign(post, response.data);
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

const handleShelfChange = async (shelfId) => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(
            `/communities/${props.community.slug}/posts/${post.slug}`,
            { shelf_id: shelfId }
        );
        
        if (response.data) {
            Object.assign(post, response.data);
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

const saveChanges = async () => {
    try {
        const isValid = await currentComponentRef.value.isValid();
        if (!isValid) return;

        const submitData = await currentComponentRef.value.submitData();
        if (!submitData) return;
        
        isSubmitting.value = true;
        const response = await axios.post(
            `/communities/${props.community.slug}/posts/${post.slug}`,
            submitData
        );
        
        if (response.data) {
            // If slug changed, redirect to new URL
            if (response.data.slug !== post.slug) {
                window.location.href = `/communities/${props.community.slug}/posts/${response.data.slug}/edit`;
                return;
            }
            
            Object.assign(post, response.data);
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

// Provide shared state
provide('post', post);
provide('community', props.community);
provide('isSubmitting', isSubmitting);
provide('errors', errors);
provide('shelves', props.shelves);
</script>

