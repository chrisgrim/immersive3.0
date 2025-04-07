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
                <Videos v-model="videos" :maxVideos="4" />
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
import { ref, computed, onMounted, inject } from 'vue';
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
        // Explicitly update all ranks after drag
        images.value.forEach((image, index) => {
            image.rank = index + 1; // Ranks start at 1 for additional images
        });
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

const removeImage = (index) => {
    const removedImage = images.value[index];
    if (removedImage.isExisting) {
        deletedImages.value.push(removedImage.url.replace(imageUrl, ''));
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
                        deletedImages.value.push(oldMainImagePath);
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
        deletedImages.value.push(mainImage.value.url.replace(imageUrl, ''));
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

// Load TikTok script on mount if needed
onMounted(() => {
    if (event?.images?.length) {
        // Make a deep copy to avoid mutations affecting the sort
        const sortedImages = [...event.images].sort((a, b) => a.rank - b.rank);
        
        // Set main image (with rank 0)
        const mainImg = sortedImages.find(img => img.rank === 0);
        if (mainImg) {
            mainImage.value = {
                url: `${imageUrl}${mainImg.url}`,
                id: mainImg.id,
                isExisting: true
            };
        }
        
        // Set other images (rank > 0)
        images.value = sortedImages
            .filter(img => img.rank > 0)
            .map(img => ({
                url: `${imageUrl}${img.url}`,
                id: img.id,
                rank: img.rank,
                isExisting: true
            }));
    }
    
    if (event?.videos?.length) {
        videos.value = event.videos.map(video => ({
            id: video.id,
            platform: video.platform,
            url: video.url
        }));
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
            } else if (mainImage.value.isExisting) {
                currentImages.push({
                    url: mainImage.value.url.replace(imageUrl, ''),
                    rank: 0,
                    id: mainImage.value.id
                });
            }
        }

        // Process additional images, ensuring they have correct sequential ranks
        const sortedImages = [...images.value].sort((a, b) => a.rank - b.rank);
        
        sortedImages.forEach((image, index) => {
            const rank = index + 1; // Ensure sequential ranks starting at 1
            
            if (image.file) {
                const fileExtension = image.file.name.split('.').pop();
                const timestamp = Date.now();
                const newFileName = `image-rank-${rank}-${timestamp}.${fileExtension}`;
                const newFile = new File([image.file], newFileName, { type: image.file.type });
                
                formData.append('images[]', newFile);
                formData.append(`ranks[${newImageCount}]`, rank);
                newImageCount++;
            } else if (image.isExisting) {
                currentImages.push({
                    url: image.url.replace(imageUrl, ''),
                    rank: rank,
                    id: image.id
                });
            }
        });

        formData.append('currentImages', JSON.stringify(currentImages));
        formData.append('deletedImages', JSON.stringify(deletedImages.value));
        
        // Add videos as a JSON array
        if (videos.value.length > 0) {
            formData.append('videos', JSON.stringify(videos.value));
        } else {
            formData.append('videos', JSON.stringify([]));
        }

        return formData;
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
