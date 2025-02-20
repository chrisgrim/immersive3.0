<template>
    <nav class="relative flex flex-col items-center flex-shrink-0 w-full mx-auto pt-12">
        <!-- Static Header -->
        <div class="w-full flex items-center gap-4 pb-8 z-50 bg-white p-10 lg-air:max-w-[40rem]">
            <a 
                :href="`/users/${user.id}`" 
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
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
            </a>
            <a 
                :href="`/users/${user.id}`" 
                class="ml-4 text-3xl md:text-5xl font-semibold truncate"
            >
                Profile
            </a>
        </div>

        <!-- Scrollable Content -->
        <div class="w-full flex flex-col md:items-center overflow-y-auto max-h-[calc(100vh-20rem)]">
            <div class="space-y-10 lg-air:max-w-[40rem] p-10 mb-20">
                <!-- Name -->
                <button
                    @click="$emit('navigate', 'Name')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Name',
                            'border border-neutral-200': currentStep !== 'Name'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Name</h3>
                    <p class="text-4xl text-neutral-600 mb-4 break-words hyphens-auto overflow-hidden">{{ user.name || 'No name set' }}</p>
                </button>

                <!-- Images -->
                <button
                    @click="$emit('navigate', 'Image')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Image',
                            'border border-neutral-200': currentStep !== 'Image'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Image</h3>
                    <div class="flex gap-4 justify-center mb-8">
                        <template v-if="userImage">
                            <picture class="w-40 h-40 flex-shrink-0">
                                <source 
                                    :srcset="userImage + '?t=' + timestamp"
                                    type="image/webp"
                                >
                                <img 
                                    :key="timestamp"
                                    :src="userImage + '?t=' + timestamp"
                                    class="w-40 h-40 rounded-full object-cover"
                                    alt="User profile image"
                                    @error="console.error('Error loading image: ' + userImage)"
                                />
                            </picture>
                        </template>
                        <div v-else class="w-40 h-40 rounded-full bg-neutral-200 flex items-center justify-center flex-shrink-0">
                            <span class="text-2xl font-bold text-neutral-400">
                                {{ user.name?.charAt(0).toUpperCase() || '?' }}
                            </span>
                        </div>
                    </div>
                </button>

                <!-- Account Settings -->
                <button
                    @click="$emit('navigate', 'Account')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Account',
                            'border border-neutral-200': currentStep !== 'Account'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Account Settings</h3>
                    <p class="text-2xl text-neutral-600 mb-4 break-words hyphens-auto overflow-hidden">
                        Manage your notifications
                    </p>
                </button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true
    },
    currentStep: {
        type: String,
        default: null
    }
});

const timestamp = ref(Date.now());

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);

const userImage = computed(() => {
    // First check for images array
    const firstImage = props.user.images?.[0];
    if (firstImage) {
        return `${imageUrl.value}${firstImage.large_image_path}`;
    }
    
    // Then check for thumbImagePath
    if (props.user.thumbImagePath) {
        return `${imageUrl.value}${props.user.thumbImagePath}`;
    }

    // Finally check for gravatar
    if (props.user.gravatar) {
        return props.user.gravatar;
    }
    
    return null;
});

// Update timestamp when user or images change
watch(() => [props.user.images, props.user.thumbImagePath], () => {
    timestamp.value = Date.now();
}, { deep: true });

defineEmits(['navigate']);
</script>