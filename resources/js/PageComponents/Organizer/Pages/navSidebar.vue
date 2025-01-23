<template>
    <!-- Mobile Back Button (shown only when a section is active) -->
    <div 
        v-if="isMobile && activeSection" 
        class="fixed top-0 left-0 right-0 z-50 bg-white border-b p-4"
    >
        <div class="flex items-center gap-4">
            <button 
                @click="$emit('navigate', null)"
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
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
            <h2 class="text-xl font-semibold">{{ activeSection }}</h2>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="flex-shrink-0 space-y-8 w-full p-8 mx-auto lg-air:max-w-[34rem] pt-12 lg-air:pt-28">
        <!-- Header with back button (hidden on mobile when section is active) -->
        <div 
            v-if="!isMobile || !activeSection"
            class="w-full flex items-center gap-4 pb-8"
        >
            <a 
                href="/hosting/events" 
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors flex-shrink-0"
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
            <a href="/hosting/events" class="text-4xl font-semibold truncate">Listings</a>
        </div>

        <!-- Name -->
        <div 
            @click="$emit('navigate', 'Name')"
            class="p-6 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 w-full max-w-full"
        >
            <h3 class="text-xl font-semibold mb-4">Name</h3>
            <p class="text-gray-600 mb-2 break-words hyphens-auto overflow-hidden">{{ organizer.name || 'No name set' }}</p>
            <p class="text-gray-500 text-lg leading-tight break-words hyphens-auto overflow-hidden">{{ organizer.description || 'No description set' }}</p>
        </div>

        <!-- Images -->
        <div 
            @click="$emit('navigate', 'Image')"
            class="p-6 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Image</h3>
            <div class="flex gap-4 justify-center mb-8">
                <template v-if="organizerImage">
                    <picture class="w-40 h-40 flex-shrink-0">
                        <source 
                            :srcset="organizerImage"
                            type="image/webp"
                        >
                        <img 
                            :src="organizerImage"
                            class="w-40 h-40 rounded-full object-cover"
                            alt="Organizer image"
                        />
                    </picture>
                </template>
                <div v-else class="w-40 h-40 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                    <span class="text-2xl font-bold text-gray-400">
                        {{ organizer.name?.charAt(0).toUpperCase() || '?' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Social Links -->
        <div 
            @click="$emit('navigate', 'Social')"
            class="p-6 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Social Links</h3>
            <div class="space-y-2">
                <div v-if="organizer.website" class="flex items-center gap-2">
                    <component :is="RiSearchLine" class="w-5 h-5 text-gray-500" />
                    <span class="text-gray-600 truncate">Website</span>
                </div>
                <div v-if="organizer.email" class="flex items-center gap-2">
                    <component :is="RiMailLine" class="w-5 h-5 text-gray-500" />
                    <span class="text-gray-600 truncate">{{ organizer.email }}</span>
                </div>
                <div v-if="organizer.twitterHandle" class="flex items-center gap-2">
                    <component :is="RiTwitterLine" class="w-5 h-5 text-gray-500" />
                    <span class="text-gray-600 truncate">@{{ organizer.twitterHandle }}</span>
                </div>
                <div v-if="organizer.instagramHandle" class="flex items-center gap-2">
                    <component :is="RiInstagramLine" class="w-5 h-5 text-gray-500" />
                    <span class="text-gray-600 truncate">@{{ organizer.instagramHandle }}</span>
                </div>
                <div v-if="organizer.facebookHandle" class="flex items-center gap-2">
                    <component :is="RiFacebookBoxLine" class="w-5 h-5 text-gray-500" />
                    <span class="text-gray-600 truncate">{{ organizer.facebookHandle }}</span>
                </div>
                <div v-if="organizer.patreon" class="flex items-center gap-2">
                    <component :is="RiPatreonLine" class="w-5 h-5 text-gray-500" />
                    <span class="text-gray-600 truncate">{{ organizer.patreon }}</span>
                </div>
                <div v-if="!hasSocialLinks" class="text-gray-500">
                    No social links set
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
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
    isMobile: {
        type: Boolean,
        default: false
    },
    activeSection: {
        type: String,
        default: null
    }
});

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
</script>

<style>
/* Mobile-specific styles */
@media (max-width: 767px) {
    .fixed-nav {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow-y: auto;
        background: white;
        z-index: 40;
    }
}

/* Adjust spacing for mobile */
@media (max-width: 767px) {
    .space-y-6 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1rem;
    }
}
</style>