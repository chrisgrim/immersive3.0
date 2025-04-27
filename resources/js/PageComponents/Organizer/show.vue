<template>
    <div class="m-auto w-full px-10 py-10 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
        <div class="flex flex-col md:flex-row md:gap-16">
            <!-- Left Column -->
            <div class="md:w-[36rem] space-y-14 mb-16 md:mb-20">
                <!-- Profile Card -->
                <div class="flex flex-row shadow-custom-6 w-full p-8 py-16 rounded-3xl gap-8">
                    <!-- Left Column - Image and Name -->
                    <div class="flex flex-col items-center w-2/3">
                        <!-- Profile Image -->
                        <div class="w-44 flex-shrink-0">
                            <div class="relative w-full">
                                <div class="relative w-full aspect-square">
                                    <div class="w-full h-full rounded-full overflow-hidden shadow-sm">
                                        <picture v-if="organizerImage">
                                            <source 
                                                type="image/webp" 
                                                :srcset="`${organizerImage}`"
                                            > 
                                            <img 
                                                class="w-full h-full object-cover"
                                                :src="`${organizerImage}`"
                                                :alt="`${organizer.name} organizer`"
                                            >
                                        </picture>
                                        <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-6xl font-bold text-gray-400">
                                                {{ organizer.name?.charAt(0).toUpperCase() || '?' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Name -->
                        <div class="w-full flex justify-center px-4">
                            <h1 class="text-3xl text-black font-medium leading-tight mt-8 text-center break-words hyphens-auto md:max-w-[25rem] overflow-hidden">
                                {{ organizer.name }}
                            </h1>
                        </div>
                    </div>

                    <!-- Right Column - Info -->
                    <div class="flex-1 flex flex-col space-y-8 m-auto">
                        <!-- Stats -->
                        <div class="flex flex-col items-start">
                            <p class="text-5xl font-semibold text-gray-900">
                                {{ organizer.events?.length || 0 }}
                            </p>
                            <p class="text-md font-bold text-gray-600">
                                Events
                            </p>
                        </div>
                        
                        <div class="w-24 h-px bg-gray-200"></div>
                        
                        <div class="flex flex-col items-start">
                            <p class="text-5xl font-semibold text-gray-900">
                                {{ calculateYearsOnEI }}
                            </p>
                            <p class="text-md font-bold text-gray-600">
                                Years on EI
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div v-if="hasSocialLinks" class="w-full border border-neutral-200 rounded-3xl p-8">
                    <div class="flex md:block md:space-y-8 justify-evenly space-x-4 md:space-x-0">
                        <!-- Website -->
                        <a v-if="organizer.website" 
                           :href="organizer.website" 
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-16 h-16 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiGlobalLine" class="w-8 h-8 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                {{ formatWebsiteUrl(organizer.website) }}
                            </span>
                        </a>

                        <!-- Email -->
                        <a v-if="organizer.email" 
                           :href="`mailto:${organizer.email}`"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-16 h-16 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiMailLine" class="w-8 h-8 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                {{ organizer.email }}
                            </span>
                        </a>

                        <!-- Twitter -->
                        <a v-if="organizer.twitterHandle" 
                           :href="`https://twitter.com/${organizer.twitterHandle}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-16 h-16 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiTwitterLine" class="w-8 h-8 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                @{{ organizer.twitterHandle }}
                            </span>
                        </a>

                        <!-- Instagram -->
                        <a v-if="organizer.instagramHandle" 
                           :href="`https://instagram.com/${organizer.instagramHandle}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-16 h-16 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiInstagramLine" class="w-8 h-8 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                @{{ organizer.instagramHandle }}
                            </span>
                        </a>

                        <!-- Facebook -->
                        <a v-if="organizer.facebookHandle" 
                           :href="`https://facebook.com/${organizer.facebookHandle}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-16 h-16 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiFacebookBoxLine" class="w-8 h-8 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                {{ organizer.facebookHandle }}
                            </span>
                        </a>

                        <!-- Patreon -->
                        <a v-if="organizer.patreon" 
                           :href="`https://patreon.com/${organizer.patreon}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-16 h-16 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiPatreonLine" class="w-8 h-8 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                {{ organizer.patreon }}
                            </span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column - Content -->
            <div class="flex-1 leading-none">
                <!-- Description -->
                <div class="whitespace-pre-wrap mb-8">
                    <div class="flex items-center gap-8 mb-8">
                        <h3 class="text-black text-5xl font-bold leading-tight break-words hyphens-auto">
                            About {{ organizer.name }}
                        </h3>
                    </div>
                    <div v-if="canEdit" class="flex items-center gap-4 mb-8">
                        <!-- Edit Button - shown only to owners and moderators -->
                        <a :href="`/organizers/${organizer.slug}/edit`" 
                            class="cursor-pointer">
                            <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center hover:bg-gray-200">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" 
                                            stroke-linejoin="round" 
                                            stroke-width="2" 
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                        </a>
                        <!-- New button with three lines and dots -->
                        <a 
                            href="/hosting/events" 
                            class="cursor-pointer"
                        >
                            <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center hover:bg-gray-200">
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
                    </div>
                    <p class="text-3xl mt-8 mb-8 break-words hyphens-auto">
                        <vue-show-more :text="organizer.description" :limit="70" />
                    </p>
                    <!-- Events Section -->
                    <div id="events" class="mt-20">
                        <h3 class="text-black text-4xl font-bold leading-tight mb-12">
                            Events by {{ organizer.name }}
                        </h3>
                        <EventListings 
                            :items="organizer.events"
                            :user="user"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { 
    RiGlobalLine,
    RiTwitterLine,
    RiInstagramLine,
    RiFacebookBoxLine,
    RiMailLine,
    RiPatreonLine
} from '@remixicon/vue';
import EventListings from '@/GlobalComponents/Grid/event-grid.vue'

const props = defineProps({
    organizer: {
        type: Object,
        required: true
    },
    user: {
        type: Object,
        required: true
    },
    canEdit: {
        type: Boolean,
        default: false
    }
});

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);

const calculateYearsOnEI = computed(() => {
    const joinDate = new Date(props.organizer.created_at);
    const now = new Date();
    const years = now.getFullYear() - joinDate.getFullYear();
    
    if (
        now.getMonth() < joinDate.getMonth() ||
        (now.getMonth() === joinDate.getMonth() && now.getDate() < joinDate.getDate())
    ) {
        return years - 1;
    }
    return years;
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

const canEdit = computed(() => {
    return ['owner', 'moderator', 'admin'].includes(props.organizer.user_role);
});

const formatWebsiteUrl = (url) => {
    if (!url) return 'Website';
    try {
        const parsedUrl = new URL(url);
        return parsedUrl.hostname;
    } catch (e) {
        return url;
    }
};
</script>