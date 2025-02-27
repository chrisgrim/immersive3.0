<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <div class="mb-8 flex flex-col items-center justify-center">
                <!-- Profile Image Display/Upload Area -->
                <div class="relative w-[25rem] h-[25rem]">
                    <input 
                        type="file" 
                        ref="fileInput"
                        class="hidden" 
                        accept="image/*"
                        @change="handleFileChange" 
                    />
                    
                    <!-- Empty State or Image -->
                    <div 
                        @click="triggerFileInput"
                        class="w-full h-full flex items-center justify-center rounded-full cursor-pointer"
                        :class="profileImage ? '' : 'border border-dashed border-neutral-400 hover:border-black hover:border-2 bg-neutral-100'"
                    >
                        <template v-if="!profileImage">
                            <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
                        </template>
                        <img 
                            v-else
                            :key="profileImage.url"
                            :src="profileImage.isExisting ? profileImage.url + '?t=' + Date.now() : profileImage.url"
                            class="w-full h-full object-cover rounded-full hover:opacity-90 transition-opacity"
                            alt="Profile" 
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

                <!-- Remove Image Button -->
                <div v-if="profileImage" class="flex justify-center mt-4">
                    <button 
                        @click="removeImage"
                        class="text-red-500 hover:text-red-700 flex items-center gap-2"
                    >
                        <component :is="RiDeleteBinLine" class="w-5 h-5" />
                        Remove Image
                    </button>
                </div>

                <!-- Image Requirements -->
                <div class="mt-8 text-gray-500 text-center">
                    <p>Image requirements:</p>
                    <ul class="list-disc list-inside">
                        <li>Square image (1:1 ratio)</li>
                        <li>Minimum 400x400 pixels</li>
                        <li>Maximum file size: 5MB</li>
                        <li>Supported formats: JPEG, PNG, WebP</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { 
    RiImageCircleLine, 
    RiDeleteBinLine 
} from "@remixicon/vue";

const profileImage = ref(null);
const fileInput = ref(null);
const validationError = ref('');
const deletedImage = ref(null);

// Use user instead of owner
const user = inject('user');

// Constants
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const MIN_DIMENSION = 400; // Minimum 400x400 pixels
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const validateFile = (file) => {
    return new Promise((resolve, reject) => {
        // Check file type
        if (!ALLOWED_TYPES.includes(file.type)) {
            validationError.value = 'Please use JPEG, PNG, or WebP image formats.';
            return resolve(false);
        }
        
        // Check file size
        if (file.size > MAX_FILE_SIZE) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            validationError.value = `Image size (${sizeMB}MB) exceeds the 5MB limit.`;
            return resolve(false);
        }

        // Check image dimensions
        const img = new Image();
        img.src = URL.createObjectURL(file);
        
        img.onload = () => {
            URL.revokeObjectURL(img.src);
            if (img.width < MIN_DIMENSION || img.height < MIN_DIMENSION) {
                validationError.value = `Image must be at least ${MIN_DIMENSION}x${MIN_DIMENSION} pixels.`;
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
                if (profileImage.value?.isExisting) {
                    deletedImage.value = profileImage.value.url.replace(imageUrl, '');
                }
                profileImage.value = {
                    url: e.target.result,
                    file,
                    isExisting: false
                };
            };
            reader.readAsDataURL(file);
        }
    }
    event.target.value = '';
};

const removeImage = () => {
    if (profileImage.value?.isExisting) {
        deletedImage.value = profileImage.value.url.replace(imageUrl, '');
    }
    profileImage.value = null;
};

const triggerFileInput = () => {
    if (fileInput.value) {
        fileInput.value.click();
    }
};

// Add updateFromOwner method
const updateFromOwner = (newData) => {
    console.log('Updating from new data:', newData?.images);
    const timestamp = Date.now();
    
    if (newData?.images?.[0]) {
        profileImage.value = {
            url: `${imageUrl}${newData.images[0].large_image_path}?t=${timestamp}`,
            isExisting: true
        };
    } else if (newData?.thumbImagePath) {
        profileImage.value = {
            url: `${imageUrl}${newData.thumbImagePath}?t=${timestamp}`,
            isExisting: true
        };
    } else if (newData?.gravatar) {
        profileImage.value = {
            url: `${newData.gravatar}?t=${timestamp}`,
            isExisting: true
        };
    } else {
        profileImage.value = null;
    }
    console.log('Updated profile image:', profileImage.value);
};

defineExpose({
    isValid: async () => {
        if (!profileImage.value) {
            return true;
        }
        return !validationError.value;
    },
    submitData: () => {
        const formData = new FormData();
        
        if (profileImage.value?.file) {
            formData.append('image', profileImage.value.file);
        }
        
        if (!profileImage.value && deletedImage.value) {
            formData.append('remove_image', 'true');
        }
        
        if (deletedImage.value) {
            formData.append('deletedImage', deletedImage.value);
        }

        // Return null if no changes
        return formData.has('image') || formData.has('remove_image') ? formData : null;
    },
    updateFromOwner
});

// Update the initialization section
const imageUrl = import.meta.env.VITE_IMAGE_URL;

onMounted(() => {
    const timestamp = Date.now();
    
    if (user?.images?.[0]) {
        profileImage.value = {
            url: `${imageUrl}${user.images[0].large_image_path}?t=${timestamp}`,
            isExisting: true
        };
    } else if (user?.thumbImagePath) {
        profileImage.value = {
            url: `${imageUrl}${user.thumbImagePath}?t=${timestamp}`,
            isExisting: true
        };
    } else if (user?.gravatar) {
        profileImage.value = {
            url: `${user.gravatar}?t=${timestamp}`,
            isExisting: true
        };
    }
});
</script>