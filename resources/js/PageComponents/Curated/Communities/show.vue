<template>
    <div>
        <div class="m-auto w-full px-10 my-10 md:my-20 lg-air:px-16 2xl-air:px-32">
            <div class="flex flex-col-reverse md:flex-row items-center justify-between gap-8 md:gap-16">
                <div class="w-full md:w-3/5">
                    <div class="w-full">
                        <div class="flex items-center gap-4">
                            <h2 class="text-4xl md:text-6xl mb-4 font-medium text-black leading-tight break-words hyphens-auto">{{ community.name }}</h2>
                        </div>
                        <p class="mb-4 text-xl md:text-2xl">{{ community.blurb }}</p>
                        
                        <!-- Rotating Curator Display -->
                        <div v-if="community.curators?.length" class="mt-8">
                            <p class="text-gray-500 text-xl">Curated by:</p>
                            <transition name="fade" mode="out-in">
                                <p :key="currentCuratorIndex" class="text-2xl font-semibold">
                                    {{ community.curators[currentCuratorIndex].name || community.curators[currentCuratorIndex].email }}
                                </p>
                            </transition>
                        </div>
                        <div class="flex items-center gap-4 mt-8">
                            <template v-if="canEdit">
                                <!-- Listings Button -->
                                <a 
                                    :href="`/communities/${community.slug}/listings`" 
                                    class="cursor-pointer"
                                >   <div class="flex items-center bg-gray-100 rounded-full pr-6 hover:bg-gray-200">
                                        <div class="w-20 h-20 flex items-center justify-center">
                                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path 
                                                    stroke-linecap="round" 
                                                    stroke-linejoin="round" 
                                                    stroke-width="2" 
                                                    d="M8 6h13M8 12h13M8 18h13" 
                                                />
                                                <circle cx="3" cy="6" r="1" />
                                                <circle cx="3" cy="12" r="1" />
                                                <circle cx="3" cy="18" r="1" />
                                            </svg>
                                        </div>
                                        <div>Edit Listings</div>
                                    </div>
                                </a>

                                <!-- Edit Button -->
                                <a 
                                    :href="`/communities/${community.slug}/edit`" 
                                    class="cursor-pointer"
                                >
                                    <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center hover:bg-gray-200">
                                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path 
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" 
                                            />
                                        </svg>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
                
                <div class="w-full md:w-2/5 mb-0">
                    <div class="relative w-full rounded-2xl overflow-hidden aspect-[16/9]">
                        <template v-if="communityImage">
                            <picture class="absolute inset-0">
                                <source 
                                    :srcset="communityImage"
                                    type="image/webp"
                                >
                                <img 
                                    :src="communityImage"
                                    :alt="community.name"
                                    class="w-full h-full object-cover"
                                    @error="handleImageError"
                                >
                            </picture>
                        </template>
                        <div 
                            v-else 
                            class="absolute inset-0 bg-neutral-100 flex items-center justify-center"
                        >
                            <span class="text-6xl font-bold text-gray-400">
                                {{ community.name?.charAt(0).toUpperCase() || '?' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="m-auto w-full">
            <div 
                v-for="(shelf, index) in shelves"
                :key="shelf.id"
                class="w-full">
                <Shelf 
                    :community="community"
                    :shelf="shelf"
                    :index="index" />
            </div>

            <div 
                v-if="shelfContainer && shelfContainer.next_page_url"
                class="loadmore text-center mt-8">
                <button 
                    class="rounded-full py-2 px-4 bg-white hover:bg-black hover:text-white hover:border-black"
                    @click="fetchShelves">
                    Load More
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Shelf from '../Shelves/show.vue'
import ShowMore from '@/GlobalComponents/show-more.vue'

const imageUrl = import.meta.env.VITE_IMAGE_URL

const props = defineProps({
    value: {
        type: Object,
        required: true
    },
    loadshelves: {
        type: Object,
        required: true
    },
    canEdit: {
        type: Boolean,
        default: false
    },
    mobile: {
        type: Boolean,
        default: false
    }
})

const community = ref(props.value)
const shelfContainer = ref(props.loadshelves)
const shelves = ref(props.loadshelves.data)
const headerImage = computed(() => 
    props.mobile ? props.value.thumbImagePath : props.value.largeImagePath
)

const communityImage = computed(() => {
    // First check for images array
    const firstImage = community.value.images?.[0];
    if (firstImage) {
        return `${imageUrl}${firstImage.large_image_path}`;
    }
    
    // Then check for thumbImagePath
    if (community.value.thumbImagePath) {
        return `${imageUrl}${community.value.thumbImagePath}`;
    }
    
    return null;
});

const currentCuratorIndex = ref(0)
let curatorInterval

const rotateCurator = () => {
    if (community.value.curators?.length) {
        currentCuratorIndex.value = (currentCuratorIndex.value + 1) % community.value.curators.length
    }
}

onMounted(() => {
    if (community.value.curators?.length > 1) {
        curatorInterval = setInterval(rotateCurator, 5000)
    }
})

onUnmounted(() => {
    if (curatorInterval) {
        clearInterval(curatorInterval)
    }
})

const onBack = () => {
    document.referrer == "" ? window.location.href = '/' : window.history.back()
}

const fetchShelves = async () => {
    try {
        const res = await axios.get(
            `/communities/${community.value.slug}/shelves/${shelfContainer.value.next_page_url.slice(-1)}/paginate`
        )
        shelfContainer.value = res.data
        shelves.value = [...shelves.value, ...res.data.data]
    } catch (error) {
        console.error('Failed to fetch shelves:', error)
    }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
