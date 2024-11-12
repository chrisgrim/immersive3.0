<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <!-- Header -->
            <h2>Add some photos of your event</h2>
            <p class="text-gray-500 font-normal mt-4 mb-8">Add up to 5 images of your event. Drag to reorder</p>
            
            <!-- Image Grid -->
            <draggable
                v-model="images"
                class="dragArea grid grid-cols-2 gap-4 w-full" 
                handle=".handle"
                @change="handleSort"
            >
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

                <template v-for="i in remainingSlots" :key="'empty-' + i">
                    <div 
                        @click="triggerFileInput"
                        :class="[
                            'relative aspect-[3/2] flex items-center justify-center border border-dashed border-gray-300 rounded-2xl cursor-pointer non-draggable hover:border-black hover:border-2',
                            images.length === 0 && i === 1 ? 'col-span-2' : ''
                        ]"
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

            <!-- YouTube Section -->
            <div class="mt-12">
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
// 1. Imports
import { ref, inject, computed, onMounted } from 'vue';
import { VueDraggableNext as draggable } from 'vue-draggable-next';
import { RiImageCircleLine, RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";

// 2. Constants
const MAX_IMAGES = 5;
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/webp'];

// 3. Injections & State
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const event = inject('event');
const images = ref([]);
const hoveredImage = ref(null);
const youtubeUrl = ref('');
const youtubeId = ref('');
const youtubeError = ref('');

// 4. Computed
const remainingSlots = computed(() => MAX_IMAGES - images.value.length);

// 5. Image Handling Methods
const validateFile = (file) => {
    if (!ALLOWED_TYPES.includes(file.type)) {
        alert(`"${file.name}" is not a supported image type. Please use JPEG, PNG, GIF, SVG, or WebP.`);
        return false;
    }
    
    if (file.size > MAX_FILE_SIZE) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        alert(`"${file.name}" (${sizeMB}MB) exceeds the 2MB size limit.`);
        return false;
    }
    
    return true;
};

const processFiles = (files) => {
    if (files.length + images.value.length > MAX_IMAGES) {
        alert(`You can only add up to ${MAX_IMAGES} images.`);
        return;
    }

    Array.from(files).forEach(file => {
        if (validateFile(file)) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const newRank = images.value.length;
                images.value.push({ 
                    url: e.target.result, 
                    file,
                    rank: newRank
                });
                handleSort({ moved: true });
            };
            reader.readAsDataURL(file);
        }
    });
};

const handleFileChange = (event) => processFiles(event.target.files);
const removeImage = (index) => images.value.splice(index, 1);
const triggerFileInput = (event) => event.currentTarget.querySelector('.fileInput')?.click();

const handleSort = ({ moved }) => {
    if (moved) {
        images.value.forEach((image, index) => {
            image.rank = index;
        });
    }
};

// 6. YouTube Handling Methods
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
    if (event?.images?.length) {
        images.value = event.images
            .sort((a, b) => a.rank - b.rank)
            .map((image, index) => ({
                url: `${imageUrl}${image.large_image_path}`,
                isExisting: true,
                rank: index
            }));
    }

    if (event?.video) {
        youtubeId.value = event.video;
        youtubeUrl.value = `https://youtube.com/watch?v=${event.video}`;
    }
});

// 8. Component API
defineExpose({
    isValid: async () => true, // Images are optional
    submitData: () => {
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
        formData.append('video', youtubeId.value || '');

        return formData;
    }
});
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
