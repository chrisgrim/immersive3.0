<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <div class="w-full max-w-[64rem] mx-auto mb-16">
                <h2>Community Image</h2>
                <p class="text-gray-500 mt-4">This image will represent your community across the platform</p>
            </div>

            <div class="mb-8 flex flex-col items-center justify-center">
                <!-- Image Upload Area -->
                <div class="relative w-full max-w-[45rem] aspect-[16/9]">
                    <input 
                        type="file" 
                        ref="fileInput"
                        class="hidden" 
                        accept="image/jpeg,image/png,image/webp"
                        @change="handleFileChange" 
                    />
                    
                    <!-- Empty State or Image -->
                    <div 
                        @click="triggerFileInput"
                        class="w-full h-full flex items-center justify-center rounded-2xl cursor-pointer"
                        :class="profileImage ? '' : 'border border-dashed border-neutral-400 hover:border-black hover:border-2 bg-neutral-100'"
                    >
                        <template v-if="!profileImage">
                            <div class="text-center">
                                <svg class="w-16 h-16 mx-auto mb-4">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-image-line`" />
                                </svg>
                                <p class="text-lg">Click to upload image</p>
                            </div>
                        </template>
                        <img 
                            v-else
                            :src="profileImage.url" 
                            class="w-full h-full object-cover rounded-2xl hover:opacity-90 transition-opacity"
                            alt="Community image" 
                        />
                    </div>
                </div>

                <!-- Validation Errors -->
                <div 
                    v-if="validationError" 
                    class="mt-4 w-full max-w-md mx-auto bg-red-50 border-2 border-red-500 rounded-xl p-4"
                >
                    <p class="text-red-600 font-semibold text-lg text-center">
                        {{ validationError }}
                    </p>
                </div>

                <!-- Image Requirements -->
                <div class="mt-8 text-gray-500 text-center">
                    <p>Image requirements:</p>
                    <ul class="list-disc list-inside">
                        <li>Minimum 400 x 225 pixels</li>
                        <li>Maximum file size: 10MB</li>
                        <li>Supported formats: JPEG, PNG, WebP</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';

const community = inject('community');
const errors = inject('errors');

const profileImage = ref(null);
const fileInput = ref(null);
const validationError = ref('');

// Constants
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const MIN_WIDTH = 400;  // Updated to smaller size
const MIN_HEIGHT = 225; // Updated to maintain 16:9 ratio
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

// Methods
const validateFile = (file) => {
    return new Promise((resolve, reject) => {
        if (!ALLOWED_TYPES.includes(file.type)) {
            validationError.value = 'Please use JPEG, PNG, or WebP image formats.';
            return resolve(false);
        }
        
        if (file.size > MAX_FILE_SIZE) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            validationError.value = `Image size (${sizeMB}MB) exceeds the 10MB limit.`;
            return resolve(false);
        }

        const img = new Image();
        img.src = URL.createObjectURL(file);
        
        img.onload = () => {
            URL.revokeObjectURL(img.src);
            if (img.width < MIN_WIDTH || img.height < MIN_HEIGHT) {
                validationError.value = `Image must be at least ${MIN_WIDTH}x${MIN_HEIGHT} pixels.`;
                resolve(false);
            } else {
                validationError.value = '';
                resolve(true);
            }
        };

        img.onerror = () => {
            URL.revokeObjectURL(img.src);
            validationError.value = 'Error loading image. Please try another file.';
            resolve(false);
        };
    });
};

const handleFileChange = async (event) => {
    const file = event.target.files[0];
    if (file) {
        const isValid = await validateFile(file);
        if (isValid) {
            validationError.value = '';
            const reader = new FileReader();
            reader.onload = (e) => {
                profileImage.value = {
                    url: e.target.result,
                    file
                };
            };
            reader.readAsDataURL(file);
        }
    }
    event.target.value = '';
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

// Component API
defineExpose({
    isValid: () => !validationError.value,
    submitData: () => {
        const formData = new FormData();
        if (profileImage.value?.file) {
            formData.append('image', profileImage.value.file);
        }
        return formData;
    }
});

// Initialize
const imageUrl = import.meta.env.VITE_IMAGE_URL;

onMounted(() => {
    if (community.images?.[0]) {
        profileImage.value = {
            url: `${imageUrl}${community.images[0].large_image_path}`,
            isExisting: true
        };
    }
});
</script>