<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full gap-8">
            <h2 class="text-2xl font-semibold mb-6">Featured Image</h2>

            <!-- Current Image Display -->
            <div class="relative">
                <div v-if="!hasImage" 
                     @click="showImageModal = true"
                     class="relative aspect-[16/9] flex items-center justify-center border border-dashed border-neutral-300 rounded-2xl cursor-pointer hover:border-black hover:border-2"
                >
                    <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
                </div>
                <div v-else :class="['relative', isVisible ? 'aspect-[16/9]' : 'h-16']">
                    <img 
                        v-if="isVisible"
                        :src="imageUrl + hasImage" 
                        class="w-full h-full object-cover rounded-2xl" 
                        alt="Featured image"
                    />
                    <div 
                        v-if="isVisible"
                        @click="deleteImage" 
                        class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white rounded-full"
                        @mouseenter="hoveredImage = true"
                        @mouseleave="hoveredImage = false"
                    >
                        <component :is="hoveredImage ? RiCloseCircleFill : RiCloseCircleLine" class="w-8 h-8" />
                    </div>
                    <div class="absolute bottom-4 left-4 drop-shadow-lg">
                        <ToggleSwitch
                            v-if="hasImage"
                            v-model="isVisible"
                            left-label="Hidden"
                            right-label="Visible"
                            text-size="lg"
                            @update:modelValue="handleVisibilityChange" 
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Selection Modal -->
        <Teleport to="body">
            <div v-if="showImageModal" 
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                 @click="showImageModal = false"
            >
                <div class="bg-white rounded-3xl p-8 max-w-3xl w-full mx-4" @click.stop>
                    <div class="flex justify-between items-center mb-8">
                        <h2 class="text-2xl font-bold">Add Featured Image</h2>
                        <button 
                            @click="showImageModal = false"
                            class="p-2 hover:bg-neutral-100 rounded-full transition-colors duration-200"
                        >
                            <component :is="RiCloseCircleLine" class="w-10 h-10" />
                        </button>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Upload Image Option -->
                        <div 
                            @click="triggerFileInput"
                            class="p-8 border border-neutral-300 hover:border-[#222222] rounded-3xl cursor-pointer transition-all duration-200 flex items-center gap-4"
                        >
                            <component :is="RiImageCircleLine" class="w-10 h-10" />
                            <span class="text-2xl">Upload an image</span>
                            <input 
                                type="file" 
                                ref="fileInput"
                                class="hidden" 
                                accept="image/jpeg,image/png,image/webp"
                                @change="handleFileChange" 
                            />
                        </div>

                        <!-- Select Event Option -->
                        <div class="p-8 border border-neutral-300 hover:border-[#222222] rounded-3xl cursor-pointer transition-all duration-200">
                            <EventSearch @select="handleEventSelect" />
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </main>
</template>

<script setup>
import { ref, computed, inject } from 'vue';
import { 
    RiImageCircleLine,
    RiCloseCircleLine,
    RiCloseCircleFill 
} from '@remixicon/vue';
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';
import EventSearch from '../event-search.vue';
import axios from 'axios';

const post = inject('post');
const community = inject('community');
const isSubmitting = inject('isSubmitting');
const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);

// State
const showImageModal = ref(false);
const hoveredImage = ref(false);
const fileInput = ref(null);

// Computed
const hasImage = computed(() => {
    if (post.event_id) {
        return post.featured_event_image?.largeImagePath;
    } else if (post.images?.length > 0) {
        return post.images[0].large_image_path;
    } else {
        return post.largeImagePath;
    }
});

const isVisible = computed({
    get: () => post.type !== 'h',
    set: (value) => {
        post.type = value ? 's' : 'h';
    }
});

// Methods
const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        const formData = new FormData();
        formData.append('image', file);
        submitData(formData);
        showImageModal.value = false;
    }
};

const handleEventSelect = (event) => {
    if (event) {
        post.event_id = event.id;
        submitData({ event_id: event.id });
        showImageModal.value = false;
    }
};

const deleteImage = () => {
    submitData({ deleteImage: true });
};

const handleVisibilityChange = (value) => {
    post.type = value ? 's' : 'h';
    submitData({ type: post.type });
};

// Helper method to submit data to the server
const submitData = async (data) => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(
            `/communities/${community.slug}/posts/${post.slug}`,
            data
        );
        
        if (response.data) {
            Object.assign(post, response.data);
        }
    } catch (error) {
        console.error('Error:', error);
    } finally {
        isSubmitting.value = false;
    }
};

// Component API
defineExpose({
    isValid: async () => true, // Image is optional
    submitData: (additionalData = null) => {
        if (additionalData) {
            return additionalData;
        }
        return null;
    }
});
</script>