<template>
    <nav class="relative flex flex-col items-center flex-shrink-0 w-full mx-auto pt-12">
        <!-- Static Header -->
        <div class="w-full flex items-center gap-4 pb-8 z-50 bg-white p-10 lg-air:max-w-[40rem]">
            <button 
                @click="goBack"
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
            </button>
            <a 
                href="/hosting/events" 
                class="cursor-pointer"
            >
                <div class="rounded-full bg-neutral-100 w-16 h-16 flex items-center justify-center hover:bg-neutral-200">
                    <svg 
                        class="w-8 h-8" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2"
                    >
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="4" cy="6" r="1" fill="currentColor"/>
                            <line x1="8" y1="6" x2="20" y2="6"/>
                            <circle cx="4" cy="12" r="1" fill="currentColor"/>
                            <line x1="8" y1="12" x2="20" y2="12"/>
                            <circle cx="4" cy="18" r="1" fill="currentColor"/>
                            <line x1="8" y1="18" x2="20" y2="18"/>
                        </g>
                    </svg>
                </div>
            </a>
            <a 
                :href="`/organizers/${organizer.slug}`" 
                class="ml-4 text-3xl md:text-5xl font-semibold truncate hover:underline"
            >
                Organizer
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
                    <p class="text-4xl text-neutral-600 mb-4 break-words hyphens-auto overflow-hidden">{{ organizer.name || 'No name set' }}</p>
                    <p class="text-neutral-500 text-2xl line-clamp-3 leading-tight break-words hyphens-auto overflow-hidden">{{ organizer.description || 'No description set' }}</p>
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
                        <template v-if="organizerImage">
                            <picture class="w-40 h-40 flex-shrink-0">
                                <source 
                                    :srcset="organizerImage + '?t=' + timestamp"
                                    type="image/webp"
                                >
                                <img 
                                    :key="timestamp"
                                    :src="organizerImage + '?t=' + timestamp"
                                    class="w-40 h-40 rounded-full object-cover"
                                    alt="Organizer image"
                                    @error="console.error('Error loading image: ' + organizerImage)"
                                />
                                <template v-if="!organizerImage">
                                    <span>Error loading image</span>
                                </template>
                            </picture>
                        </template>
                        <div v-else class="w-40 h-40 rounded-full bg-neutral-200 flex items-center justify-center flex-shrink-0">
                            <span class="text-2xl font-bold text-neutral-400">
                                {{ organizer.name?.charAt(0).toUpperCase() || '?' }}
                            </span>
                        </div>
                    </div>
                </button>

                <!-- Social Links -->
                <button
                    @click="$emit('navigate', 'Social')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Social',
                            'border border-neutral-200': currentStep !== 'Social'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Social Links</h3>
                    <div class="space-y-2">
                        <div v-if="organizer.website" class="flex items-center gap-2">
                            <component :is="RiSearchLine" class="w-5 h-5 text-neutral-500" />
                            <span class="text-neutral-600 truncate">Website</span>
                        </div>
                        <div v-if="organizer.email" class="flex items-center gap-2">
                            <component :is="RiMailLine" class="w-5 h-5 text-neutral-500" />
                            <span class="text-neutral-600 truncate">{{ organizer.email }}</span>
                        </div>
                        <div v-if="organizer.twitterHandle" class="flex items-center gap-2">
                            <component :is="RiTwitterLine" class="w-5 h-5 text-neutral-500" />
                            <span class="text-neutral-600 truncate">@{{ organizer.twitterHandle }}</span>
                        </div>
                        <div v-if="organizer.instagramHandle" class="flex items-center gap-2">
                            <component :is="RiInstagramLine" class="w-5 h-5 text-neutral-500" />
                            <span class="text-neutral-600 truncate">@{{ organizer.instagramHandle }}</span>
                        </div>
                        <div v-if="organizer.facebookHandle" class="flex items-center gap-2">
                            <component :is="RiFacebookBoxLine" class="w-5 h-5 text-neutral-500" />
                            <span class="text-neutral-600 truncate">{{ organizer.facebookHandle }}</span>
                        </div>
                        <div v-if="organizer.patreon" class="flex items-center gap-2">
                            <component :is="RiPatreonLine" class="w-5 h-5 text-neutral-500" />
                            <span class="text-neutral-600 truncate">{{ organizer.patreon }}</span>
                        </div>
                        <div v-if="!hasSocialLinks" class="text-neutral-500">
                            No social links set
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { 
    RiSearchLine,
    RiTwitterLine,
    RiInstagramLine,
    RiFacebookBoxLine,
    RiMailLine,
    RiPatreonLine
} from '@remixicon/vue';

const props = defineProps({
    organizer: {
        type: Object,
        required: true
    },
    currentStep: {
        type: String,
        default: null
    }
});

const timestamp = ref(Date.now());

const isMobile = computed(() => window?.Laravel?.isMobile ?? false);

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);

const organizerImage = computed(() => {
    // First check for images array
    const firstImage = props.organizer.images?.[0];
    if (firstImage) {
        return `${imageUrl.value}${firstImage.large_image_path}`;
    }
    
    // Then check for thumbImagePath
    if (props.organizer.thumbImagePath) {
        return `${imageUrl.value}${props.organizer.thumbImagePath}`;
    }
    
    return null;
});

// Update timestamp when organizer images change
watch(() => [props.organizer.images, props.organizer.thumbImagePath], () => {
    timestamp.value = Date.now();
}, { deep: true });

const hasImage = computed(() => {
    return Boolean(props.organizer.images?.[0] || props.organizer.thumbImagePath);
});

const hasSocialLinks = computed(() => {
    return Boolean(
        props.organizer.website ||
        props.organizer.email ||
        props.organizer.twitterHandle ||
        props.organizer.instagramHandle ||
        props.organizer.facebookHandle ||
        props.organizer.patreon
    );
});

defineEmits(['navigate']);

const goBack = () => {
    window.history.back()
};
</script>