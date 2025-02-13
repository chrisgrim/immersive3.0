<template>
    <!-- Mobile Back Button (shown only when a section is active) -->
    <div 
        v-if="isMobile && activeSection" 
        class="fixed top-0 left-0 right-0 z-50 bg-white border-neutral-300 border-b p-4"
    >
        <div class="flex items-center gap-4">
            <button 
                @click="$emit('navigate', null)"
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors"
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
    <nav class="relative flex flex-col items-center flex-shrink-0 w-full mx-auto pt-12">
        <!-- Static Header -->
        <div 
            v-if="!isMobile || !activeSection"
            class="w-full flex items-center gap-4 pb-8 z-50 bg-white p-4 lg-air:max-w-[40rem]"
        >
            <a 
                :href="`/communities/${community.slug}`" 
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
                :href="`/communities/${community.slug}/listings`" 
                class="ml-4 text-5xl font-semibold truncate"
            >
                Community
            </a>
        </div>

        <!-- Scrollable Content -->
        <div class="w-full flex flex-col items-center overflow-y-auto max-h-[calc(100vh-19rem)]">
            <div class="space-y-8 lg-air:max-w-[40rem] p-8 mb-20">
                <!-- Name -->
                <button
                    @click="$emit('navigate', 'Name')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': activeSection === 'Name',
                            'border border-neutral-200': activeSection !== 'Name'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Name</h3>
                    <p class="text-neutral-600 mb-2 line-clamp-1 break-words hyphens-auto">
                        {{ community.name || 'No name set' }}
                    </p>
                    <p class="text-xl text-neutral-500 line-clamp-2 break-words hyphens-auto">
                        {{ community.blurb || 'No blurb set' }}
                    </p>
                </button>

                <!-- Description -->
                <button
                    @click="$emit('navigate', 'Description')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': activeSection === 'Description',
                            'border border-neutral-200': activeSection !== 'Description'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Description</h3>
                    <p class="text-xl text-neutral-600 line-clamp-3 break-words hyphens-auto">
                        {{ community.description || 'No description set' }}
                    </p>
                </button>

                <!-- Image -->
                <button
                    @click="$emit('navigate', 'Image')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': activeSection === 'Image',
                            'border border-neutral-200': activeSection !== 'Image'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Image</h3>
                    <div class="aspect-[16/9] rounded-xl overflow-hidden bg-neutral-100">
                        <img 
                            v-if="communityImage"
                            :src="communityImage"
                            class="w-full h-full object-cover"
                            alt="Community image"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <span class="text-neutral-400">No image set</span>
                        </div>
                    </div>
                </button>

                <!-- Curators -->
                <button
                    @click="$emit('navigate', 'Curators')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': activeSection === 'Curators',
                            'border border-neutral-200': activeSection !== 'Curators'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Curators</h3>
                    <div>
                        <p 
                            v-for="(curator, index) in displayedCurators" 
                            :key="curator.id" 
                            class="text-neutral-600 text-xl leading-tight"
                        >
                            {{ curator.name || curator.email }}
                        </p>
                        <p v-if="community.curators?.length > 7" class="text-neutral-600 text-sm">...</p>
                        <p v-if="!community.curators?.length" class="text-neutral-600 text-sm">
                            No curators set
                        </p>
                    </div>
                </button>
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
    activeSection: String
});

const isMobile = computed(() => window?.Laravel?.isMobile ?? false);

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