<template>
    <div v-if="loading">
        <p>Loading...</p>
    </div>
    <div v-else>
        <div class="m-auto w-full px-8 my-20 lg-air:px-16 2xl-air:px-32">
            <div class="flex items-center justify-between gap-8">
                <div class="w-2/3">
                    <div class="w-full">
                        <div class="flex items-center gap-4 mb-4">
                            <a 
                                :href="`/communities/${community.slug}`"
                                class="text-6xl font-medium text-black break-words hyphens-auto leading-tight hover:underline"
                            >
                                {{ community.name }}
                            </a>
                        </div>
                        <p class="mb-4 text-2xl break-words hyphens-auto">{{ community.blurb }}</p>
                        
                        <!-- Curator Display -->
                        <div v-if="community.curators?.length" class="mt-8">
                            <p class="text-gray-500 text-xl">Curated by:</p>
                            <transition name="fade" mode="out-in">
                                <p :key="currentCuratorIndex" class="text-2xl font-semibold">
                                    {{ community.curators[currentCuratorIndex].name }}
                                </p>
                            </transition>
                        </div>
                        <div class="flex items-center mt-8">
                            <a 
                                :href="`/communities/${community.slug}/edit`" 
                                class="inline-flex items-center justify-center p-6 rounded-full bg-neutral-100 hover:bg-neutral-300"
                            >
                                <svg 
                                    class="w-6 h-6" 
                                    viewBox="0 0 24 24" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    stroke-width="2" 
                                    stroke-linecap="round" 
                                    stroke-linejoin="round"
                                >
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                </svg>
                            </a>
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

        <!-- Shelves Section -->
        <div class="m-auto w-full py-20 px-8 md:px-32">
            <div class="w-full mb-20">
                <div class="flex gap-6 flex-wrap mb-12">
                    <button 
                        @click="addShelf"
                        class="cursor-pointer rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center text-5xl font-light hover:bg-gray-200">
                        +
                    </button>
                    <draggable
                        v-model="shelves"
                        :draggable="'.drag'"
                        @end="updateShelvesOrder"
                        item-key="id"
                        class="flex gap-6 flex-wrap">
                        <template #item="{ element }">
                            <button 
                                class="px-6 py-3 rounded-full border text-lg font-medium transition-all drag"
                                :class="[
                                    active === element.id 
                                        ? 'border-black bg-black text-white' 
                                        : 'border-gray-300 text-gray-700 hover:border-gray-400'
                                ]"
                                @click="active = element.id">
                                {{ element.name }}
                            </button>
                        </template>
                    </draggable>
                </div>

                <div v-for="shelf in shelves" :key="shelf.id" v-show="active === shelf.id">
                    <Shelf 
                        :community="community"
                        :loadshelf="shelf"
                        @delete="deleteShelf" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import Shelf from '../Shelves/edit.vue'
import draggable from 'vuedraggable'

// Props
const props = defineProps({
    loadcommunity: {
        type: Object,
        required: true
    },
    loadshelves: {
        type: Array,
        default: () => []
    }
})

// Data refs
const shelves = ref(props.loadshelves || [])
const community = ref(props.loadcommunity)
const loading = ref(true)
const active = ref(null)
const curators = ref(props.loadcommunity?.curators?.filter(u => u.id !== props?.loadowner?.id) || [])
const imageUrl = import.meta.env.VITE_IMAGE_URL || ''

const currentCuratorIndex = ref(0)
let curatorInterval

// Methods
const addShelf = async () => {
    try {
        const res = await axios.post(`/shelves/${community.value.slug}`)
        shelves.value = res.data
    } catch (err) {
        console.error(err)
    }
}

const deleteShelf = async (shelf) => {
    if (shelves.value.length <= 1) {
        alert('Communities must have at least one shelf')
        return
    }
    if (shelf.posts.data.length) {
        alert('Cannot delete shelf with posts')
        return
    }
    try {
        await axios.delete(`/shelves/${shelf.id}`)
        shelves.value = shelves.value.filter(s => s.id !== shelf.id)
    } catch (err) {
        console.error('Failed to delete shelf:', err)
        alert('Failed to delete shelf')
    }
}

const updateShelvesOrder = async () => {
    try {
        const orderedShelves = shelves.value.map((shelf, index) => ({
            id: shelf.id,
            order: index
        }))
        
        await axios.put(`/shelves/${community.value.slug}/order`, orderedShelves)
    } catch (error) {
        console.error('Failed to update shelf order:', error)
    }
}

const rotateCurator = () => {
    if (community.value.curators?.length) {
        currentCuratorIndex.value = (currentCuratorIndex.value + 1) % community.value.curators.length
    }
}

// Lifecycle hooks
onMounted(() => {
    loading.value = false
    if (shelves.value.length > 0) {
        active.value = shelves.value[0].id
    }
    if (community.value.curators?.length > 1) {
        curatorInterval = setInterval(rotateCurator, 3000)
    }
})

onUnmounted(() => {
    if (curatorInterval) {
        clearInterval(curatorInterval)
    }
})
</script>

<style scoped>
.drag {
    cursor: move;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
