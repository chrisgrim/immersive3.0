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
                :href="`/communities/${community.slug}/listings`" 
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
            <a :href="`/communities/${community.slug}/listings`" class="text-4xl font-semibold truncate">Listings</a>
        </div>

        <!-- Name -->
        <div 
            @click="$emit('navigate', 'Name')"
            class="p-6 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50">
            <h3 class="text-xl font-semibold mb-4">Name</h3>
            <p class="text-gray-600 mb-2 line-clamp-1 break-words hyphens-auto">{{ community.name || 'No name set' }}</p>
            <p class="text-xl text-gray-500 line-clamp-2 break-words hyphens-auto">{{ community.blurb || 'No blurb set' }}</p>
        </div>

        <!-- Description -->
        <div 
            @click="$emit('navigate', 'Description')"
            class="p-6 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50">
            <h3 class="text-xl font-semibold mb-4">Description</h3>
            <p class="text-xl text-gray-600 line-clamp-3 break-words hyphens-auto">{{ community.description || 'No description set' }}</p>
        </div>

        <!-- Image -->
        <div 
            @click="$emit('navigate', 'Image')"
            class="p-6 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50">
            <h3 class="text-xl font-semibold mb-4">Image</h3>
            <div class="aspect-video rounded-xl overflow-hidden bg-gray-100">
                <img 
                    v-if="communityImage"
                    :src="communityImage"
                    class="w-full h-full object-cover"
                    alt="Community image"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                    <span class="text-gray-400">No image set</span>
                </div>
            </div>
        </div>

        <!-- Curators -->
        <div 
            @click="$emit('navigate', 'Curators')"
            class="p-6 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50"
        >
            <h3 class="text-lg font-semibold mb-4">Curators</h3>
            <div>
                <p 
                    v-for="(curator, index) in displayedCurators" 
                    :key="curator.id" 
                    class="text-gray-600 text-xl leading-tight"
                >
                    {{ curator.name }}
                </p>
                <p v-if="community.curators?.length > 7" class="text-gray-600 text-sm">...</p>
                <p v-if="!community.curators?.length" class="text-gray-600 text-sm">
                    No curators set
                </p>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    community: {
        type: Object,
        required: true
    },
    isMobile: Boolean,
    activeSection: String
});

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);

const communityImage = computed(() => {
    if (props.community.images?.[0]) {
        return `${imageUrl.value}${props.community.images[0].large_image_path}`;
    }
    return null;
});

const displayedCurators = computed(() => {
    if (props.community.curators) {
        return props.community.curators.slice(0, 7);
    }
    return [];
});

defineEmits(['navigate']);
</script>