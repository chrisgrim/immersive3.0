<template>
    <div>
        <div class="m-auto w-full px-8 my-32 lg-air:px-16 2xl-air:px-32">
            <div class="flex items-center justify-between gap-8">
                <div class="w-2/3">
                    <div class="w-full">
                        <h2 class="text-6xl mb-4 font-medium text-black">{{ community.name }}</h2>
                        <p class="mb-4 text-2xl">{{ community.blurb }}</p>
                        
                        <!-- Rotating Curator Display -->
                        <div v-if="community.curators?.length" class="mt-8">
                            <p class="text-gray-500 text-xl">Curated by:</p>
                            <transition name="fade" mode="out-in">
                                <p :key="currentCuratorIndex" class="text-2xl font-semibold">
                                    {{ community.curators[currentCuratorIndex].name }}
                                </p>
                            </transition>
                        </div>
                    </div>
                </div>
                
                <div class="w-1/3 rounded-2xl overflow-hidden">
                    <img 
                        :src="`${imageUrl}${community.thumbImagePath}`"
                        :alt="community.name"
                        class="w-full h-full object-cover">
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
    curator: {
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
        const res = await axios.get(`/communities/${community.value.slug}/shelves/paginate?page=${shelfContainer.value.next_page_url.slice(-1)}`)
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
    transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
