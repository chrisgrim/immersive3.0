<template>
    <div v-if="loading">
        <p>Loading...</p>
    </div>
    <div v-else>
        <div class="m-auto w-full px-10 my-10 md:my-20 lg-air:px-16 2xl-air:px-32">
            <!-- Updated Header Section -->
            <div class="w-full flex items-center justify-between mb-20 shadow-custom-6 md:shadow-none p-8 md:p-0 rounded-2xl">
                <div class="flex flex-row items-start gap-8 min-w-0">
                    <!-- Community Image -->
                    <div class="w-[50%] flex-shrink-0 md:w-[15rem]">
                        <div class="aspect-[16/9] rounded-2xl overflow-hidden">
                            <template v-if="communityImage">
                                <picture class="w-full h-full">
                                    <source 
                                        :srcset="communityImage"
                                        type="image/webp"
                                    >
                                    <img 
                                        :src="communityImage"
                                        class="w-full h-full object-cover"
                                        :alt="community.name"
                                        @error="handleImageError"
                                    />
                                </picture>
                            </template>
                            <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <span class="text-2xl font-bold text-gray-400">
                                    {{ community.name?.charAt(0).toUpperCase() || '?' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col min-w-0 flex-1 h-full">
                        <a :href="`/communities/${community.slug}`" 
                           class="group text-2xl md:text-5xl font-medium hover:underline break-words hyphens-auto leading-tight">
                            {{ community.name }}
                        </a>
                        
                        <!-- Curator Names -->
                        <div v-if="community.curators?.length" class="w-full mt-4 flex md:flex-row flex-col md:items-center md:gap-4">
                            <p class="text-xl md:text-2xl text-gray-500 flex-shrink-0">Curated by:</p>
                            <transition name="fade" mode="out-in">
                                <p :key="currentCuratorIndex" class="font-medium truncate min-w-0">
                                    {{ community.curators[currentCuratorIndex].name }}
                                </p>
                            </transition>
                        </div>
                    </div>
                </div>
                
                <div class="hidden md:flex gap-4">
                    <a :href="`/communities/${community.slug}/edit`" class="cursor-pointer">
                        <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center hover:bg-gray-200">
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Desktop Shelf Buttons -->
            <div class="hidden md:flex gap-6 flex-wrap mb-12">
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

            <!-- Mobile Header with Add and Menu Buttons -->
            <div class="flex justify-between items-center md:hidden mb-16">
                <h3 class="text-5xl leading-tight">Your <br>Shelves</h3>
                <div class="flex gap-4">
                    <div @click="addShelf" class="cursor-pointer flex">
                        <div class="rounded-full bg-gray-100 w-16 h-16 flex items-center justify-center text-4xl font-light hover:bg-gray-200">
                            +
                        </div>
                    </div>
                    <div @click="isOpen = !isOpen" class="cursor-pointer flex">
                        <div class="rounded-full bg-gray-100 w-16 h-16 flex items-center justify-center hover:bg-gray-200">
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Shelf Selection Modal -->
            <teleport to="body">
                <div v-if="isOpen" 
                     class="fixed inset-0 bg-black bg-opacity-50 flex items-end justify-center z-50"
                     @click="isOpen = false">
                    <div class="bg-white w-full rounded-t-2xl p-8 max-h-[80vh] overflow-y-auto" 
                         @click.stop>
                        <!-- Modal Header -->
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-2xl font-medium">Select Shelf</h3>
                            <button @click="isOpen = false">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Shelf Options -->
                        <draggable
                            v-model="shelves"
                            :draggable="'.drag'"
                            @end="updateShelvesOrder"
                            item-key="id"
                            class="space-y-4">
                            <template #item="{ element }">
                                <button 
                                    class="w-full p-4 text-left rounded-xl transition-colors drag"
                                    :class="{ 
                                        'bg-black text-white': active === element.id,
                                        'hover:bg-gray-100': active !== element.id
                                    }"
                                    @click="selectShelf(element.id)">
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg">{{ element.name }}</span>
                                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </button>
                            </template>
                        </draggable>
                    </div>
                </div>
            </teleport>

            <!-- Shelf Content -->
            <div v-for="shelf in shelves" :key="shelf.id" v-show="active === shelf.id">
                <Shelf 
                    :community="community"
                    :loadshelf="shelf"
                    :start-editing="shelf.id === newShelfId"
                    @delete="deleteShelf" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
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
const shelvesBeforeReorder = ref([])
const isOpen = ref(false)
const newShelfId = ref(null)

// Computed properties
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

const handleImageError = () => {
    console.error('Failed to load community image');
    // You could set a flag here to force the fallback display if needed
};

// Methods
const addShelf = async () => {
    try {
        const res = await axios.post(`/communities/${community.value.slug}/shelves`)
        
        // Find the newly created shelf by comparing with current shelves
        const currentIds = new Set(shelves.value.map(s => s.id))
        const newShelf = res.data.find(shelf => !currentIds.has(shelf.id))
        
        shelves.value = res.data
        
        // Switch to the new shelf and set it for editing
        if (newShelf) {
            active.value = newShelf.id
            newShelfId.value = newShelf.id
            isOpen.value = false // Close the mobile modal if it's open
            
            // Reset newShelfId after a short delay
            setTimeout(() => {
                newShelfId.value = null
            }, 100)
        }
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
        await axios.delete(`/communities/${community.value.slug}/shelves/${shelf.id}`)
        shelves.value = shelves.value.filter(s => s.id !== shelf.id)
        
        // After deletion, set active to the first shelf in the list
        if (shelves.value.length > 0) {
            active.value = shelves.value[0].id
        } else {
            active.value = null
        }
    } catch (err) {
        console.error('Failed to delete shelf:', err)
        alert('Failed to delete shelf')
    }
}

const updateShelvesOrder = async () => {
    try {
        shelvesBeforeReorder.value = [...shelves.value]
        
        const orderedShelves = shelves.value.map((shelf, index) => ({
            id: shelf.id,
            order: index
        }))
        
        console.log('Sending shelf order update:', {
            url: `/communities/${community.value.slug}/shelves/order`,
            data: orderedShelves,
            communitySlug: community.value.slug
        })
        
        const response = await axios.post(`/communities/${community.value.slug}/shelves/order`, orderedShelves)
        console.log('Shelf order update response:', response.data)
    } catch (error) {
        console.error('Failed to update shelf order:', {
            error,
            response: error.response?.data,
            status: error.response?.status,
            data: error.response?.config?.data
        })
        shelves.value = [...shelvesBeforeReorder.value]
    }
}

const rotateCurator = () => {
    if (community.value.curators?.length) {
        currentCuratorIndex.value = (currentCuratorIndex.value + 1) % community.value.curators.length
    }
}

const selectShelf = (id) => {
    active.value = id;
    isOpen.value = false;
};

// Lifecycle hooks
onMounted(() => {
    loading.value = false;
    
    // Get shelf ID from URL params
    const urlParams = new URLSearchParams(window.location.search);
    const shelfId = urlParams.get('shelf');
    
    if (shelfId && shelves.value.length > 0) {
        // Set active shelf to the one from URL
        active.value = parseInt(shelfId);
    } else if (shelves.value.length > 0) {
        // Fallback to first shelf
        active.value = shelves.value[0].id;
    }

    if (community.value.curators?.length > 1) {
        curatorInterval = setInterval(rotateCurator, 3000);
    }
});

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
