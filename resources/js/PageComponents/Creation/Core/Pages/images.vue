<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <div class="w-full max-w-[64rem] mx-auto mb-16">
                <h2 v-if="showCropper || !mainImage">Add the poster image of your event</h2>
                <h2 v-else>Add additional images</h2>
            </div>

            <!-- Show cropper when editing -->
            <div v-if="showCropper" class="w-full flex justify-center">
                <div class="w-[30rem]">
                    <Cropper
                        class="h-[40rem]"
                        :src="cropperImage"
                        :stencil-props="{
                            aspectRatio: 3/4
                        }"
                        @change="onChange"
                    />
                    <div class="mt-4 flex justify-end gap-2">
                        <button 
                            @click="cancelCrop"
                            class="px-4 py-2 border rounded-lg hover:bg-gray-100"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="completeCrop"
                            class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>

            <!-- Show normal layout when not cropping -->
            <div v-else>
                <!-- Rest of your existing template content -->
                <div v-if="!mainImage" class="mb-8 flex flex-col items-center justify-center pt-12">
                    <!-- Empty Main Image Slot -->
                    <div 
                        @click="triggerMainFileInput"
                        class="relative w-[30rem] h-[40rem] flex items-center justify-center border border-dashed border-neutral-400 rounded-2xl cursor-pointer hover:border-black hover:border-2 bg-neutral-100"
                    >
                        <input 
                            type="file" 
                            ref="mainFileInput"
                            class="hidden" 
                            accept="image/*"
                            @change="handleMainFileChange" 
                        />
                        <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
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
                                class="absolute inset-0 cursor-pointer hover:bg-black/10 rounded-2xl transition-colors"
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
                                    class="relative aspect-[3/2] flex items-center justify-center border border-dashed border-gray-300 rounded-2xl cursor-pointer hover:border-black hover:border-2"
                                >
                                    <input 
                                        type="file" 
                                        class="hidden fileInput" 
                                        multiple 
                                        accept="image/*"
                                        @change="handleFileChange" 
                                    />
                                    <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            

            <!-- YouTube Section -->
            <div class="mt-16 max-w-[64rem] mx-auto">
                <p v-if="showMainImageError" 
                   class="text-red-500 text-1xl text-center mb-4">
                    Please add a poster image for your event
                </p>
                <h3 class="text-2xl mb-4">Add a YouTube Video (Optional)</h3>
                <div class="relative">
                    <input 
                        type="text"
                        v-model="youtubeUrl"
                        placeholder="Paste YouTube URL here"
                        class="w-full p-4 pr-12 border rounded-xl"
                        @input="handleYoutubeInput"
                    />
                    <div v-if="youtubeId" 
                        @click="clearYoutube"
                        class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer"
                    >
                        <component :is="RiCloseCircleLine" />
                    </div>
                </div>
                
                <div v-if="youtubeId" class="mt-4">
                    <div class="relative aspect-video w-full">
                        <iframe
                            :src="`https://www.youtube.com/embed/${youtubeId}`"
                            class="absolute top-0 left-0 w-full h-full rounded-xl"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>

                <p v-if="youtubeError" class="text-red-500 mt-2">
                    {{ youtubeError }}
                </p>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { RiImageCircleLine, RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import draggable from 'vuedraggable';

const mainImage = ref(null);
const hoveredMain = ref(false);
const showCropper = ref(false);
const cropperImage = ref('');

const images = ref([]);
const hoveredImage = ref(null);
const deletedImages = ref([]);

const remainingSlots = computed(() => {
    return Math.max(0, 4 - images.value.length);
});

const handleSort = ({ moved }) => {
    if (moved) {
        images.value.forEach((image, index) => {
            image.rank = index + 1; // Start secondary images at rank 1
        });
        console.log('After sort:', images.value);
    }
};

const handleFileChange = async (event) => {
    const files = Array.from(event.target.files);
    for (const file of files) {
        const isValid = await validateFile(file);
        if (isValid) {
            const reader = new FileReader();
            reader.onload = (e) => {
                images.value.push({
                    url: e.target.result,
                    file,
                    rank: images.value.length
                });
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
};

const handleMainFileChange = async (event) => {
    console.log('Starting main file change');
    const file = event.target.files[0];
    if (file) {
        const isValid = await validateFile(file);
        if (isValid) {
            console.log('File validated, preparing cropper');
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
    console.log('Starting crop completion');
    showMainImageError.value = false;
    
    console.log('State before crop:', {
        mainImage: mainImage.value,
        secondaryImages: [...images.value],
        deletedImages: [...deletedImages.value]
    });

    const canvas = document.querySelector('.vue-advanced-cropper canvas');
    canvas.toBlob((blob) => {
        const file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
        const reader = new FileReader();
        reader.onload = (e) => {
            // Store old main image info
            const oldMainImage = mainImage.value;
            
            // Set the new main image
            mainImage.value = { 
                url: e.target.result, 
                file,
                rank: 0
            };
            
            // Handle old main image
            if (oldMainImage?.isExisting) {
                console.log('Adding old main image to deletedImages:', oldMainImage.url);
                const oldMainImagePath = oldMainImage.url.replace(imageUrl, '');
                if (!deletedImages.value.includes(oldMainImagePath)) {
                    deletedImages.value.push(oldMainImagePath);
                }
            }
            
            showCropper.value = false;
            cropperImage.value = '';
            setComponentReady(true);

            console.log('State after crop completion:', {
                mainImage: mainImage.value,
                secondaryImages: [...images.value],
                deletedImages: [...deletedImages.value]
            });
        };
        reader.readAsDataURL(file);
    }, 'image/jpeg');
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
    console.log('1. Clicking main image');
    if (mainFileInput.value) {
        mainFileInput.value.value = '';
        mainFileInput.value.click();
    }
};

const triggerFileInput = (event) => {
    event.currentTarget.querySelector('.fileInput').click();
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

// Constants
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB instead of 2MB
const MIN_DIMENSION = 800; // Minimum pixels for shortest side
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

// Add the props and loadImages method here
const props = defineProps({
    initialImages: {
        type: Array,
        default: () => []
    }
});

// Update submitData to handle both new and existing images
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

        // Process main image first (always rank 0)
        if (mainImage.value) {
            if (mainImage.value.file) {
                formData.append('images[]', mainImage.value.file);
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

        // Process additional images with sequential ranks starting at 1
        images.value.forEach((image, index) => {
            const rank = index + 1;
            if (image.file) {
                formData.append('images[]', image.file);
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

        const currentImagesStr = JSON.stringify(currentImages);
        const deletedImagesStr = JSON.stringify(deletedImages.value);

        console.log('Image Submission:', {
            existing: currentImages.map(img => ({ id: img.id, rank: img.rank })),
            new: Array.from({ length: newImageCount }, (_, i) => ({ rank: formData.get(`ranks[${i}]`) })),
            deleted: deletedImages.value
        });

        formData.append('currentImages', currentImagesStr);
        formData.append('deletedImages', deletedImagesStr);
        formData.append('video', youtubeId.value || '');

        return formData;
    }
});

// Add these with your other refs
const youtubeUrl = ref('');
const youtubeId = ref('');
const youtubeError = ref('');

// Add these YouTube handling methods
const extractYoutubeId = (url) => {
    if (!url) return null;
    
    const patterns = [
        /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/|shorts\/)([^"&?\/\s]{11})/i,
        /^[a-zA-Z0-9_-]{11}$/
    ];

    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match && match[1]) return match[1];
    }

    return null;
};

const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

const handleYoutubeInput = debounce(async () => {
    youtubeError.value = '';
    const extractedId = extractYoutubeId(youtubeUrl.value);

    if (!youtubeUrl.value) {
        youtubeId.value = '';
        return;
    }

    if (!extractedId) {
        youtubeError.value = 'Please enter a valid YouTube URL';
        youtubeId.value = '';
        return;
    }

    if (extractedId !== youtubeId.value) {
        try {
            const response = await fetch(`https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=${extractedId}&format=json`);
            youtubeId.value = response.ok ? extractedId : '';
            if (!response.ok) youtubeError.value = 'This video cannot be embedded or does not exist';
        } catch (error) {
            youtubeError.value = 'Error validating YouTube URL';
            youtubeId.value = '';
        }
    }
}, 500);

const clearYoutube = () => {
    youtubeUrl.value = '';
    youtubeId.value = '';
    youtubeError.value = '';
};

// 7. Initialization
onMounted(() => {
    console.log('Initial event images:', event?.images);
    
    if (event?.images?.length) {
        const sortedImages = [...event.images].sort((a, b) => a.rank - b.rank);
        console.log('Sorted images with ranks:', sortedImages.map(img => ({
            id: img.id,
            rank: img.rank,
            path: img.large_image_path
        })));
        
        // Find all rank 0 images
        const rankZeroImages = sortedImages.filter(img => img.rank === 0);
        
        // If we have multiple rank 0 images, use the most recent one as main
        if (rankZeroImages.length > 1) {
            console.warn('Multiple rank 0 images found:', rankZeroImages);
            // Use the one with the highest ID (most recent) as main
            const mainImg = rankZeroImages.reduce((a, b) => a.id > b.id ? a : b);
            mainImage.value = {
                url: `${imageUrl}${mainImg.large_image_path}`,
                isExisting: true,
                id: mainImg.id,
                rank: 0
            };
            
            // Add other rank 0 images to deletedImages
            rankZeroImages
                .filter(img => img.id !== mainImg.id)
                .forEach(img => {
                    deletedImages.value.push(img.large_image_path);
                });
        } else {
            // Normal case - just one rank 0 image
            const mainImg = rankZeroImages[0];
            if (mainImg) {
                mainImage.value = {
                    url: `${imageUrl}${mainImg.large_image_path}`,
                    isExisting: true,
                    id: mainImg.id,
                    rank: 0
                };
            }
        }

        // Set additional images (rank > 0)
        images.value = sortedImages
            .filter(img => img.rank > 0)
            .map(image => ({
                url: `${imageUrl}${image.large_image_path}`,
                isExisting: true,
                rank: image.rank,
                id: image.id
            }));
    }
    
    if (event?.video) {
        youtubeId.value = event.video;
        youtubeUrl.value = `https://youtube.com/watch?v=${event.video}`;
    }

    setComponentReady(true);
});

// Add imageUrl injection
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const event = inject('event');

// Add this with your other refs
const mainFileInput = ref(null);

const setComponentReady = inject('setComponentReady');

// When starting an upload or crop operation
const startOperation = () => {
    setComponentReady(false);
};

// When operation completes
const finishOperation = () => {
    setComponentReady(true);
};

const showMainImageError = ref(false);
</script>

<style>
.vue-advanced-cropper {
    background-color: #f3f4f6;
}
</style>
