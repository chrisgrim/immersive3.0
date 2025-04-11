<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <div class="w-full max-w-[64rem] mx-auto mb-16">
                <h2 v-if="showCropper || !mainImage" class="text-black">Add the poster image of your event</h2>
                <h2 v-else class="text-black">Add additional images</h2>
            </div>

            <!-- Normal layout when not cropping -->
            <div>
                <!-- Rest of your existing template content -->
                <div v-if="!mainImage" class="mb-8 flex flex-col items-center justify-center pt-12">
                    <!-- Empty Main Image Slot -->
                    <div 
                        @click="triggerMainFileInput"
                        class="relative w-[30rem] h-[40rem] flex items-center justify-center border border-dashed border-neutral-300 rounded-2xl cursor-pointer hover:border-solid hover:border-[#222222] hover:shadow-focus-black transition-all duration-200 bg-neutral-50"
                    >
                        <input 
                            type="file" 
                            ref="mainFileInput"
                            class="hidden" 
                            accept="image/*"
                            @change="handleMainFileChange" 
                        />
                        <component :is="RiImageCircleLine" class="w-16 h-16 text-neutral-400" />
                    </div>
                </div>

                <!-- Grid layout after main image is set -->
                <div v-else class="grid grid-cols-3 gap-4">
                    <!-- Main Image Column -->
                    <div class="col-span-1">
                        <div class="relative h-full w-full">
                            <img :src="mainImage.url" class="w-full h-full object-cover rounded-2xl" />
                            <div 
                                v-if="!images.length"
                                @click="removeMainImage" 
                                class="absolute top-2 right-2 cursor-pointer bg-white rounded-full"
                                @mouseenter="hoveredMain = true"
                                @mouseleave="hoveredMain = false"
                            >
                                <component :is="hoveredMain ? RiCloseCircleFill : RiCloseCircleLine" />
                            </div>
                            <input 
                                type="file" 
                                ref="mainFileInput"
                                class="hidden" 
                                accept="image/*"
                                @change="handleMainFileChange" 
                            />
                            <div 
                                v-if="images.length"
                                class="absolute inset-0 cursor-pointer hover:bg-black/10 rounded-2xl transition-colors duration-200"
                                @click="triggerMainFileInput"
                            />
                        </div>
                    </div>

                    <!-- Secondary Images Column -->
                    <div class="col-span-2">
                        <div class="grid grid-cols-2 gap-4 h-full">
                            <draggable
                                v-model="images"
                                class="contents"
                                handle=".handle"
                                item-key="id"
                                @change="handleSort"
                            >
                                <template #item="{element, index}">
                                    <div 
                                        class="relative handle aspect-[3/2]"
                                    >
                                        <img :src="element.url" class="w-full h-full object-cover rounded-2xl" />
                                        <div 
                                            @click="removeImage(index)" 
                                            class="absolute top-2 right-2 cursor-pointer bg-white rounded-full"
                                            @mouseenter="hoveredImage = index"
                                            @mouseleave="hoveredImage = null"
                                        >
                                            <component :is="hoveredImage === index ? RiCloseCircleFill : RiCloseCircleLine" />
                                        </div>
                                    </div>
                                </template>
                            </draggable>

                            <!-- Empty slots -->
                            <template v-for="i in remainingSlots" :key="'empty-' + i">
                                <div 
                                    @click="triggerFileInput"
                                    class="relative aspect-[3/2] flex items-center justify-center border border-dashed border-neutral-300 rounded-2xl cursor-pointer hover:border-solid hover:border-[#222222] hover:shadow-focus-black transition-all duration-200"
                                >
                                    <input 
                                        type="file" 
                                        class="hidden fileInput" 
                                        multiple 
                                        accept="image/*"
                                        @change="handleFileChange" 
                                    />
                                    <component :is="RiImageCircleLine" class="w-16 h-16 text-neutral-400" />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Video Section - Updated for multiple platforms -->
            <div class="mt-16 max-w-[64rem] mx-auto">
                <p v-if="showMainImageError" 
                   class="text-red-500 text-1xl text-center mb-4">
                    Please add a poster image for your event
                </p>
                <Videos 
                    v-model="videos" 
                    :maxVideos="4" 
                    :showInSlideshow="showVideosInSlideshow"
                    @update:showInSlideshow="showVideosInSlideshow = $event"
                />
            </div>
        </div>
    </main>

    <!-- Teleport the cropper to body for fullscreen -->
    <teleport to="body">
        <div v-if="showCropper" class="fixed inset-0 z-50 bg-black/90 flex flex-col items-center justify-center p-4">
            <div class="w-full max-w-4xl h-[80vh]">
                <Cropper
                    class="h-full w-full"
                    :src="cropperImage"
                    :stencil-props="{
                        aspectRatio: 3/4
                    }"
                    :image-restriction="'none'"
                    @change="onChange"
                />
                <div class="mt-6 flex justify-center gap-4">
                    <button 
                        @click="cancelCrop"
                        class="px-6 py-3 border border-white text-white rounded-lg hover:bg-white/10 transition-colors duration-200"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="completeCrop"
                        class="px-6 py-3 bg-white text-black rounded-lg hover:bg-neutral-200 transition-colors duration-200"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref, computed, onMounted, inject, watch } from 'vue';
import { RiImageCircleLine, RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import draggable from 'vuedraggable';
import Videos from './videos.vue';

// Utility functions
const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

// Constants
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const MIN_DIMENSION = 400; // Minimum pixels for shortest side (lowered from 800)
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

// State refs
const mainImage = ref(null);
const hoveredMain = ref(false);
const showCropper = ref(false);
const cropperImage = ref('');
const images = ref([]);
const hoveredImage = ref(null);
const deletedImages = ref([]);
const videos = ref([]);
const showVideosInSlideshow = ref(true);
const showMainImageError = ref(false);
const mainFileInput = ref(null);

// Injected values
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const event = inject('event');
const setComponentReady = inject('setComponentReady');

// Computed properties
const remainingSlots = computed(() => {
    return Math.max(0, 4 - images.value.length);
});

// Image handling methods
const handleSort = ({ moved }) => {
    if (moved) {
        // Get image IDs before reordering to detect any potential duplicates
        const imageIdsBeforeReordering = new Set(images.value.map(img => img.id));
        
        // Explicitly update all ranks after drag
        images.value.forEach((image, index) => {
            image.rank = index + 1; // Ranks start at 1 for additional images
        });
        
        // Re-sort by rank to ensure correct order
        images.value.sort((a, b) => a.rank - b.rank);
        
        // Verify no duplicate images were created during reordering
        const imageIdsAfterReordering = new Set(images.value.map(img => img.id));
        
        // If we have more ids after reordering, we have duplicates
        if (imageIdsAfterReordering.size > imageIdsBeforeReordering.size) {
            // De-duplicate by id
            const seenIds = new Set();
            images.value = images.value.filter(img => {
                if (seenIds.has(img.id)) {
                    return false; // Skip duplicate
                }
                seenIds.add(img.id);
                return true;
            });
            
            // Reassign ranks after de-duplication
            images.value.forEach((image, index) => {
                image.rank = index + 1;
            });
        }
    }
};

const validateFile = (file) => {
    return new Promise((resolve, reject) => {
        // Check file type
        if (!ALLOWED_TYPES.includes(file.type)) {
            alert(`"${file.name}" is not a supported image type. Please use JPEG, PNG, or WebP.`);
            return resolve(false);
        }
        
        // Check file size
        if (file.size > MAX_FILE_SIZE) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            alert(`"${file.name}" (${sizeMB}MB) exceeds the 5MB size limit.`);
            return resolve(false);
        }

        // Check image dimensions
        const img = new Image();
        img.src = URL.createObjectURL(file);
        
        img.onload = () => {
            URL.revokeObjectURL(img.src);
            const smallestSide = Math.min(img.width, img.height);
            if (smallestSide < MIN_DIMENSION) {
                alert(`Image's smallest side must be at least ${MIN_DIMENSION}px. This image's smallest side is ${smallestSide}px.`);
                resolve(false);
            } else {
                resolve(true);
            }
        };

        img.onerror = () => {
            URL.revokeObjectURL(img.src);
            alert('Error loading image. Please try another file.');
            resolve(false);
        };
    });
};

const handleFileChange = async (event) => {
    const files = Array.from(event.target.files);
    for (const file of files) {
        const isValid = await validateFile(file);
        if (isValid) {
            const reader = new FileReader();
            reader.onload = (e) => {
                // Calculate the next rank as the current length + 1
                const nextRank = images.value.length + 1;
                
                images.value.push({
                    url: e.target.result,
                    file,
                    rank: nextRank,
                    id: Date.now() + Math.random() // More unique ID for draggable
                });
                
                // Re-sort by rank to ensure correct order
                images.value.sort((a, b) => a.rank - b.rank);
            };
            reader.readAsDataURL(file);
        }
    }
    event.target.value = '';
};

// Create a wrapper function for adding to deletedImages
const addToDeletedImages = (imagePath) => {
    deletedImages.value.push(imagePath);
};

const removeImage = (index) => {
    const removedImage = images.value[index];
    if (removedImage.isExisting) {
        const imagePath = removedImage.url.replace(imageUrl, '');
        addToDeletedImages(imagePath);
    }
    images.value.splice(index, 1);
    
    // Reassign ranks sequentially after removing an image
    images.value.forEach((image, idx) => {
        image.rank = idx + 1; // Ranks start at 1 for additional images
    });
};

const handleMainFileChange = async (event) => {
    const file = event.target.files[0];
    if (file) {
        const isValid = await validateFile(file);
        if (isValid) {
            showMainImageError.value = false;
            const reader = new FileReader();
            reader.onload = (e) => {
                cropperImage.value = e.target.result;
                showCropper.value = true;
                setComponentReady(false);
            };
            reader.readAsDataURL(file);
        }
    }
    event.target.value = '';
};

const onChange = ({ coordinates, canvas }) => {
    // Optional: Handle real-time crop changes
};

const completeCrop = () => {
    showMainImageError.value = false;
    
    const canvas = document.querySelector('.vue-advanced-cropper canvas');
    const currentWidth = canvas.width;
    const currentHeight = canvas.height;
    
    // Calculate scale needed to ensure minimum 400px on smallest side
    const smallestSide = Math.min(currentWidth, currentHeight);
    const scale = smallestSide < 400 ? (400 / smallestSide) : 1;
    
    // Create new canvas with scaled dimensions if needed
    const outputCanvas = document.createElement('canvas');
    outputCanvas.width = Math.round(currentWidth * scale);
    outputCanvas.height = Math.round(currentHeight * scale);
    
    const outputCtx = outputCanvas.getContext('2d');
    outputCtx.drawImage(canvas, 0, 0, outputCanvas.width, outputCanvas.height);
    
    // Start with a quality of 0.95 and decrease if needed
    const createImageBlob = (quality = 0.95) => {
        outputCanvas.toBlob((blob) => {
            // Check if the blob is too large
            if (blob.size > MAX_FILE_SIZE) {
                if (quality > 0.5) {
                    // Try again with lower quality
                    createImageBlob(quality - 0.1);
                } else {
                    // If we're still too large at 0.5 quality, show an error
                    alert('The cropped image is too large. Please try a smaller image or crop a smaller portion.');
                    showCropper.value = false;
                    cropperImage.value = '';
                    setComponentReady(true);
                }
                return;
            }
            
            const file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
            const reader = new FileReader();
            reader.onload = (e) => {
                const oldMainImage = mainImage.value;
                
                mainImage.value = { 
                    url: e.target.result, 
                    file,
                    rank: 0
                };
                
                if (oldMainImage?.isExisting) {
                    const oldMainImagePath = oldMainImage.url.replace(imageUrl, '');
                    if (!deletedImages.value.includes(oldMainImagePath)) {
                        addToDeletedImages(oldMainImagePath);
                    }
                }
                
                showCropper.value = false;
                cropperImage.value = '';
                setComponentReady(true);
            };
            reader.readAsDataURL(file);
        }, 'image/jpeg', quality);
    };
    
    createImageBlob();
};

const cancelCrop = () => {
    showCropper.value = false;
    cropperImage.value = '';
    setComponentReady(true);
};

const removeMainImage = () => {
    if (mainImage.value?.isExisting) {
        const imagePath = mainImage.value.url.replace(imageUrl, '');
        addToDeletedImages(imagePath);
    }
    mainImage.value = null;
};

const triggerMainFileInput = () => {
    if (mainFileInput.value) {
        mainFileInput.value.value = '';
        mainFileInput.value.click();
    }
};

const triggerFileInput = (event) => {
    event.currentTarget.querySelector('.fileInput').click();
};

// Load initial data on component mount
onMounted(() => {
    // Clear deleted images array when component mounts
    deletedImages.value = [];
    
    if (event?.images?.length) {
        // Make a deep copy to avoid mutations affecting the sort
        const sortedImages = [...event.images].sort((a, b) => a.rank - b.rank);
        
        // Set main image (with rank 0)
        const mainImg = sortedImages.find(img => img.rank === 0);
        if (mainImg) {
            mainImage.value = {
                url: `${imageUrl}${mainImg.large_image_path}`,
                id: mainImg.id,
                isExisting: true
            };
        }
        
        // Set other images (rank > 0)
        images.value = sortedImages
            .filter(img => img.rank > 0)
            .map(img => ({
                url: `${imageUrl}${img.large_image_path}`,
                id: img.id,
                rank: img.rank,
                isExisting: true
            }));
    }
    
    // Load existing videos from the event
    if (event?.videos?.length) {
        videos.value = event.videos.map(video => ({
            id: video.platform_video_id || video.id,
            platform: video.platform,
            url: video.url
        }));
        
        // Set slideshow value based on event.video field
        showVideosInSlideshow.value = event.video === 'gallery';
    }

    setComponentReady(true);
});

// Expose methods and data
defineExpose({
    isValid: async () => {
        if (!mainImage.value) {
            showMainImageError.value = true;
            return false;
        }
        showMainImageError.value = false;
        return true;
    },
    submitData: () => {
        const formData = new FormData();
        const currentImages = [];
        let newImageCount = 0;
        
        // Create a map to track which images have already been uploaded
        // This prevents duplicating the same image with different ranks
        const uploadedFileIds = new Set();
        
        // Always handle the main image first with rank 0
        if (mainImage.value) {
            if (mainImage.value.file) {
                const fileExtension = mainImage.value.file.name.split('.').pop();
                const timestamp = Date.now();
                const newFileName = `image-rank-0-${timestamp}.${fileExtension}`;
                const newFile = new File([mainImage.value.file], newFileName, { type: mainImage.value.file.type });
                
                formData.append('images[]', newFile);
                formData.append(`ranks[${newImageCount}]`, 0);
                newImageCount++;
                
                // Track that we've uploaded this file
                if (mainImage.value.id) {
                    uploadedFileIds.add(mainImage.value.id);
                }
            } else if (mainImage.value.isExisting) {
                currentImages.push({
                    url: mainImage.value.url.replace(imageUrl, ''),
                    rank: 0,
                    id: mainImage.value.id
                });
            }
        }

        // Ensure images are sorted by rank before processing
        const sortedImages = [...images.value].sort((a, b) => a.rank - b.rank);
        
        sortedImages.forEach((image, index) => {
            const rank = index + 1; // Ensure sequential ranks starting at 1
            
            // Skip if we've already processed this file
            if (image.id && uploadedFileIds.has(image.id)) {
                return;
            }
            
            if (image.file) {
                const fileExtension = image.file.name.split('.').pop();
                const timestamp = Date.now();
                const newFileName = `image-rank-${rank}-${timestamp}.${fileExtension}`;
                const newFile = new File([image.file], newFileName, { type: image.file.type });
                
                formData.append('images[]', newFile);
                formData.append(`ranks[${newImageCount}]`, rank);
                newImageCount++;
                
                // Track that we've uploaded this file
                if (image.id) {
                    uploadedFileIds.add(image.id);
                }
            } else if (image.isExisting) {
                // Ensure the rank is updated in case it changed during reordering
                currentImages.push({
                    url: image.url.replace(imageUrl, ''),
                    rank: rank,
                    id: image.id
                });
            }
        });
        
        formData.append('currentImages', JSON.stringify(currentImages));
        formData.append('deletedImages', JSON.stringify(deletedImages.value));
        
        // Add videos as a JSON array with platform and id properties
        if (videos.value.length > 0) {
            // Format the videos to match the database structure
            const formattedVideos = videos.value.map((video, index) => ({
                platform: video.platform,
                id: video.id,
                url: video.url,
                rank: index // Set sequential rank based on order
            }));
            formData.append('videos', JSON.stringify(formattedVideos));
            
            // Add the slideshow preference (gallery = on, page = off)
            formData.append('videoSlideshow', showVideosInSlideshow.value ? 'gallery' : 'page');
        } else {
            formData.append('videos', JSON.stringify([]));
            formData.append('videoSlideshow', '');
        }

        return formData;
    },
    resetDeletedImages: () => {
        deletedImages.value = [];
    },
    // Refresh component state after successful submission
    updateFromServer: (updatedEvent) => {
        // Clear existing data
        mainImage.value = null;
        images.value = [];
        deletedImages.value = [];
        
        if (updatedEvent?.images?.length) {
            // Make a deep copy to avoid mutations affecting the sort
            const sortedImages = [...updatedEvent.images].sort((a, b) => a.rank - b.rank);
            
            // Set main image (with rank 0)
            const mainImg = sortedImages.find(img => img.rank === 0);
            if (mainImg) {
                mainImage.value = {
                    url: `${imageUrl}${mainImg.large_image_path}`,
                    id: mainImg.id,
                    isExisting: true
                };
            }
            
            // Set other images (rank > 0)
            images.value = sortedImages
                .filter(img => img.rank > 0)
                .map(img => ({
                    url: `${imageUrl}${img.large_image_path}`,
                    id: img.id,
                    rank: img.rank,
                    isExisting: true
                }));
        }
        
        // Load existing videos from the event
        if (updatedEvent?.videos?.length) {
            videos.value = updatedEvent.videos.map(video => ({
                id: video.platform_video_id || video.id,
                platform: video.platform,
                url: video.url
            }));
            
            // Set slideshow value based on event.video field
            showVideosInSlideshow.value = updatedEvent.video === 'gallery';
        }
    }
});
</script>

<style>
.vue-advanced-cropper {
    background-color: rgba(30, 30, 30, 0.8);
}

/* Custom background for the cropper */
.vue-advanced-cropper__background, 
.vue-advanced-cropper__foreground {
    opacity: 1;
    background: #ff000042;
    transform: translate(-50%, -50%);
    position: absolute;
    top: 50%;
    left: 50%;
}
</style>
