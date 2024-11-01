<template>
    <main class="w-full">
        <div class="w-full">
            <h2>Add some photos of your event</h2>
            <p class="text-gray-500 font-normal mt-4 mb-8">Add up to 5 images of your event. Drag to reorder</p>
            
            <!-- Empty State Upload Area -->
            <div v-if="!images.length"
                class="mt-6 outline-dashed rounded-2xl h-[27vw] flex justify-center items-center relative"
                @dragover.prevent
                @drop.prevent="handleDrop"
            >
                <input 
                    type="file" 
                    ref="fileInput" 
                    class="hidden" 
                    multiple 
                    accept="image/*"
                    @change="handleFileChange" 
                />
                <!-- <UploadPrompt @click="browseFiles" /> -->
            </div>

            <!-- Image Grid -->
            <draggable v-else
                v-model="images"
                class="dragArea grid grid-cols-2 gap-4 w-full" 
                handle=".handle"
                @change="handleSort"
            >
                <!-- Existing Images -->
                <template v-for="(image, index) in images" :key="index">
                    <div v-if="image" 
                        :class="[
                            'relative handle draggable-image',
                            index === 0 ? 'col-span-2 aspect-[3/2]' : 'aspect-[3/2]'
                        ]"
                    >
                        <img :src="image.url" class="w-full h-full object-cover rounded-2xl" />
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

                <!-- Empty Upload Slots -->
                <template v-for="i in remainingSlots" :key="'empty-' + i">
                    <div @click="triggerFileInput"
                        class="relative aspect-[3/2] flex items-center justify-center border border-dashed border-black rounded-2xl cursor-pointer non-draggable hover:border-black hover:border-2 hover:border-solid"
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
            </draggable>
        </div>

        <!-- Submit Button -->
        <div class="w-full flex justify-end">
            <button 
                class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" 
                @click="handleSubmit"
            >Next</button>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, computed } from 'vue';
import { RiImageCircleLine, RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { VueDraggableNext as draggable } from 'vue-draggable-next';

// Constants
const MAX_IMAGES = 5;
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/webp'];

// Injected dependencies
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

// Refs
const images = ref([]);
const fileInput = ref(null);
const hoveredImage = ref(null);

// Computed
const remainingSlots = computed(() => MAX_IMAGES - images.value.length);

// File handling methods
const validateFile = (file) => {
    if (!ALLOWED_TYPES.includes(file.type)) {
        alert(`"${file.name}" is not a supported image type. Please use JPEG, PNG, GIF, SVG, or WebP.`);
        return false;
    }
    
    if (file.size > MAX_FILE_SIZE) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        alert(`"${file.name}" (${sizeMB}MB) exceeds the 2MB size limit. Please compress the image and try again.`);
        return false;
    }
    
    return true;
};

const processFiles = (files) => {
    if (files.length + images.value.length > MAX_IMAGES) {
        alert(`You can only add up to ${MAX_IMAGES} images.`);
        return;
    }

    console.log('Current images before adding:', images.value.map(img => img.rank));

    Array.from(files).forEach(file => {
        if (validateFile(file)) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const newRank = images.value.length;
                console.log('Adding new image with rank:', newRank);
                images.value.push({ 
                    url: e.target.result, 
                    file,
                    rank: newRank
                });
                console.log('Images after adding:', images.value.map(img => img.rank));
                handleSort({ moved: true });
            };
            reader.readAsDataURL(file);
        }
    });
};

// Event handlers
const handleFileChange = (event) => processFiles(event.target.files);
const handleDrop = (event) => processFiles(event.dataTransfer.files);
const browseFiles = () => fileInput.value?.click();
const removeImage = (index) => images.value.splice(index, 1);
const triggerFileInput = (event) => event.currentTarget.querySelector('.fileInput')?.click();

// Initialize images with proper ranks
if (event?.images?.length) {
    console.log('Initial event images:', event.images.map(img => img.rank));
    images.value = event.images
        .sort((a, b) => a.rank - b.rank)
        .map((image, index) => ({
            url: `${imageUrl}${image.large_image_path}`,
            isExisting: true,
            rank: index
        }));
    console.log('After initialization:', images.value.map(img => img.rank));
}

// Update the draggable component to handle the grid layout properly
const handleSort = ({ moved }) => {
    if (moved) {
        console.log('Before sort ranks:', images.value.map(img => img.rank));
        images.value.forEach((image, index) => {
            image.rank = index;
        });
        console.log('After sort ranks:', images.value.map(img => img.rank));
    }
};

// Submit handler
const handleSubmit = async () => {
    console.log('Images before submit:', images.value.map(img => ({
        rank: img.rank,
        isFile: !!img.file,
        url: img.url
    })));

    const formData = new FormData();
    const currentImages = [];

    images.value.forEach((image, index) => {
        image.rank = index;
        if (image.file) {
            formData.append('images[]', image.file);
            formData.append('ranks[]', index);
        } else {
            currentImages.push({
                url: image.url.replace(imageUrl, ''),
                rank: index
            });
        }
    });

    formData.append('currentImages', JSON.stringify(currentImages));

    try {
        const response = await onSubmit(formData);
        if (response?.event?.images) {
            console.log('Response images:', response.event.images.map(img => img.rank));
            event.images = response.event.images;
            images.value = event.images
                .sort((a, b) => a.rank - b.rank)
                .map(image => ({
                    url: `${imageUrl}${image.large_image_path}`,
                    isExisting: true,
                    rank: image.rank
                }));
            console.log('Final images:', images.value.map(img => img.rank));
        }
        setStep('NextStep');
    } catch (error) {
        console.error('Error submitting images:', error);
    }
};
</script>

<style>
.draggable-image {
    cursor: grab;
}

.draggable-image:active {
    cursor: grabbing;
}

div > .sortable-ghost:first-child {
    grid-column: span 2 / span 2;
}

div > .sortable-ghost:first-child + div {
    grid-column: span 1 / span 1;
}
</style>
