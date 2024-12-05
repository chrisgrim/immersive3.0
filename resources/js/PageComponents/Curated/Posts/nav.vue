<template>
    <div class="fixed left-0 top-0 h-screen transition-all duration-300 mt-40"
         :class="[isOpen ? 'w-[400px]' : 'w-12']">
        <!-- Toggle Button -->
        <button @click="toggleSidebar" 
                class="absolute -right-16 top-24 bg-white rounded-full p-2 shadow-md hover:shadow-lg z-50">
            <svg xmlns="http://www.w3.org/2000/svg" 
                 :class="[isOpen ? 'rotate-180' : '']"
                 class="w-24 h-24 transition-transform" 
                 viewBox="0 0 24 24">
                <path d="M15.54 11.29L9.88 5.64a1 1 0 0 0-1.42 0 1 1 0 0 0 0 1.41l4.95 5L8.46 17a1 1 0 0 0 0 1.41 1 1 0 0 0 .71.3 1 1 0 0 0 .71-.3l5.66-5.65a1 1 0 0 0 0-1.47z"/>
            </svg>
        </button>

        <!-- Sidebar Content - Keep all existing content -->
        <div class="h-full bg-white shadow-lg overflow-hidden">
            <div class="sticky top-16 overflow-auto h-[calc(100vh-20rem)] min-h-[50rem]"
                 :class="[isOpen ? 'opacity-100 p-8' : 'opacity-0 p-0']">
                <!-- View Post Button -->
                <div v-if="hasRequiredData" class="mb-8">
                    <a target="_blank" 
                       :href="`/communities/${community.slug}/${inputVal.slug}`" 
                       class="flex items-center gap-2 rounded-lg p-3 justify-end">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                            <path d="M19.5 4.5h-7V6h4.44l-5.97 5.97 1.06 1.06L18 7.06v4.44h1.5v-7Zm-13 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3H17v3a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h3V5.5h-3Z"></path>
                        </svg>
                    </a>
                </div>

                <!-- Status Section -->
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <p>Status:</p>
                        <button 
                            class="px-4 py-2 font-bold"
                            @click="updateStatus">
                            {{ postStatus }}
                        </button>
                    </div>
                </div>

                <!-- Shelf Section -->
                <div class="mb-8">
                    <div>
                        <div v-if="!selectedShelf">
                            <Dropdown
                                :list="shelves"
                                :placeholder="'Select Shelf'"
                                @onSelect="handleShelfSelect" />
                        </div>
                        <DropdownList 
                            v-else
                            :selections="[selectedShelf]"
                            @onSelect="removeShelf" />
                    </div>
                </div>

                <!-- Featured Image Section -->
                <div class="mb-8">
                    <div>
                        <div 
                            v-if="!hasImage"
                            @click="triggerFileInput"
                            class="relative aspect-[16/9] flex items-center justify-center border border-dashed border-gray-300 rounded-2xl cursor-pointer hover:border-black hover:border-2"
                        >
                            <input 
                                type="file" 
                                class="hidden fileInput" 
                                accept="image/*"
                                @change="handleFileChange" 
                            />
                            <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
                        </div>
                        <div v-else class="relative aspect-[16/9]">
                            <img 
                                :src="hasImage ? `${imageUrl}${hasImage}` : null" 
                                class="w-full h-full object-cover rounded-2xl" 
                            />
                            <div 
                                @click="deleteImage" 
                                class="absolute top-2 right-2 cursor-pointer bg-white rounded-full"
                                @mouseenter="hoveredImage = true"
                                @mouseleave="hoveredImage = false"
                            >
                                <component :is="hoveredImage ? RiCloseCircleFill : RiCloseCircleLine" />
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-4">
                            <button 
                                v-if="hasImage"
                                class="bg-black px-4 py-2 rounded-full text-white hover:bg-white hover:text-black"
                                @click="updateType">
                                {{ postType }}
                            </button>
                        </div>
                        <div v-if="showEventSearch && !value.thumbImagePath">
                            <Dropdown
                                :list="options"
                                :placeholder="'Search for event'"
                                @onSelect="handleEventSelect"
                                @input="debounce" />
                        </div>
                    </div>
                </div>

                <!-- Card Order Section -->
                <div class="mb-8">
                    <button 
                        @click="showCardOrder = !showCardOrder"
                        class="flex items-center gap-2 mb-4">
                        <svg 
                            class="w-4 h-4 transition-transform"
                            :class="{ 'rotate-90': showCardOrder }"
                            xmlns="http://www.w3.org/2000/svg" 
                            viewBox="0 0 24 24" 
                            fill="none" 
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Card Order</span>
                    </button>
                    <div v-show="showCardOrder">
                        <draggable
                            v-model="inputVal.cards" 
                            :item-key="card => card.id"
                            @start="onDragStart" 
                            @end="reOrder"
                            :animation="200"
                            handle=".cursor-pointer">
                            <template #item="{ element: card }">
                                <div 
                                    :key="`list${card.id}`"
                                    class="border p-2 w-full truncate cursor-pointer inline-block text-sm mb-2">
                                    <span 
                                        v-if="card.type==='e' && card.event"
                                        class="inline">
                                        <b class="mr-2 text-sm">event block </b>
                                        (<span class="text-sm [&>p]:text-sm [&>h3]:text-sm [&>h4]:text-sm [&>p]:inline [&>p]:m-0">{{ card.event.name }}</span>)
                                    </span>
                                    <span 
                                        v-else-if="card.type==='i'"
                                        class="">image block</span>
                                    <span 
                                        v-else
                                        class="inline"><b class="mr-2">text block</b> 
                                        (<span class="text-sm [&>p]:text-sm [&>h3]:text-sm [&>h4]:text-sm [&>p]:inline [&>p]:m-0" v-html="card.blurb.slice(0,50)" />)</span>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import draggable from 'vuedraggable'
import Dropdown from '@/GlobalComponents/dropdown.vue'
import DropdownList from '@/GlobalComponents/dropdown-list.vue'
import { RiImageCircleLine, RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";

const props = defineProps({
    value: {
        type: Object,
        required: true
    },
    shelves: {
        type: Array,
        required: true
    },
    community: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['input', 'update', 'addEventFeaturedImage', 'addImage', 'deleteImage', 'reOrder'])

// State
const searchInput = ref(null)
const options = ref([])
const updated = ref(false)
let timeout = null
const showEventSearch = ref(false)
const showCardOrder = ref(false)
const hoveredImage = ref(false)
const isDragging = ref(false)

// Constants for image validation
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/webp'];

// Add this line to get the image URL from environment
const imageUrl = import.meta.env.VITE_IMAGE_URL;

// Create a deep copy of the initial value
const inputVal = ref(JSON.parse(JSON.stringify(props.value)))

// Watch for prop changes
watch(() => props.value, (newVal) => {
    if (JSON.stringify(inputVal.value) !== JSON.stringify(newVal)) {
        inputVal.value = JSON.parse(JSON.stringify(newVal))
    }
}, { deep: true })

const postStatus = computed(() => 
    inputVal.value.status === 'p' ? 'Live' : 'Draft'
)

const postType = computed(() => 
    inputVal.value.type === 'h' ? 'Hidden' : 'Visible'
)

const hasImage = computed(() => 
    inputVal.value?.event_id 
        ? inputVal.value.featured_event_image?.thumbImagePath
        : inputVal.value?.thumbImagePath
)

// Add computed for selected shelf
const selectedShelf = computed(() => {
    if (!inputVal.value?.shelf_id) return null
    const shelf = props.shelves.find(s => s.id === Number(inputVal.value.shelf_id))
    return shelf || null
})

// Methods
const debounce = (query) => {
    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(() => {
        generateSearchList(query)
    }, 500)
}

const generateSearchList = async (query) => {
    try {
        const res = await axios.get('/api/search/nav/events', { 
            params: { keywords: query } 
        })
        options.value = res.data.map(item => ({
            id: item.model.id,
            name: item.model.name
        }))
    } catch (error) {
        console.error('Failed to fetch search results:', error)
        options.value = []
    }
}

const eventImage = () => {
    emit('addEventFeaturedImage', searchInput.value)
}

const update = async () => {
    console.log('Emitting update with:', inputVal.value)
    emit('update')
}

const addImage = (image) => {
    searchInput.value = null
    emit('addImage', image)
}

const deleteImage = () => {
    searchInput.value = null
    emit('deleteImage', { deleteImage: true })
}

const reOrder = async () => {
    try {
        console.log('Reordering cards')
        isDragging.value = false
        
        // Map the new order
        const updatedCards = inputVal.value.cards.map((card, index) => ({
            ...card,
            order: index
        }))
        
        // Create the updated post
        const updatedPost = {
            ...inputVal.value,
            cards: updatedCards
        }
        
        // Update local state
        inputVal.value = updatedPost
        
        // Emit update with the new post data
        emit('update', updatedPost)
    } catch (error) {
        console.error('Error reordering cards:', error)
        // Revert the order if there's an error
        inputVal.value = JSON.parse(JSON.stringify(props.value))
    }
}

const updateStatus = () => {
    inputVal.value.status = inputVal.value.status === 'd' ? 'p' : 'd'
    update()
}

const updateType = () => {
    inputVal.value.type = inputVal.value.type === 's' ? 'h' : 's'
    update()
}

// Add methods for handling shelf selection
const handleShelfSelect = async (shelf) => {
    try {
        console.log('Selecting shelf:', shelf)
        const updatedPost = {
            ...inputVal.value,
            shelf_id: Number(shelf.id)
        }
        inputVal.value = updatedPost
        emit('update', updatedPost)
    } catch (error) {
        console.error('Error updating shelf:', error)
    }
}

const removeShelf = async () => {
    try {
        console.log('Removing shelf')
        const updatedPost = {
            ...inputVal.value,
            shelf_id: null
        }
        inputVal.value = updatedPost
        emit('update', updatedPost)
    } catch (error) {
        console.error('Error removing shelf:', error)
    }
}

const handleEventSelect = (event) => {
    searchInput.value = event
    eventImage()
    showEventSearch.value = false
}

// Image handling methods
const validateFile = (file) => {
    if (!ALLOWED_TYPES.includes(file.type)) {
        alert(`File is not a supported image type. Please use JPEG, PNG, GIF, SVG, or WebP.`);
        return false;
    }
    
    if (file.size > MAX_FILE_SIZE) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        alert(`File (${sizeMB}MB) exceeds the 2MB size limit.`);
        return false;
    }
    
    return true;
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file && validateFile(file)) {
        emit('addImage', file);
    }
};

const triggerFileInput = (event) => {
    event.currentTarget.querySelector('.fileInput')?.click();
};

// Add error checking for required properties
const hasRequiredData = computed(() => {
    return inputVal.value && 
           props.community && 
           props.community.slug && 
           inputVal.value.slug
})

// Update the watch to be more specific
watch(() => props.value.shelf_id, (newShelfId) => {
    if (inputVal.value.shelf_id !== newShelfId) {
        inputVal.value = {
            ...inputVal.value,
            shelf_id: newShelfId
        }
    }
}, { immediate: true })

// Add method to handle drag start
const onDragStart = () => {
    isDragging.value = true
}

// Add new ref for sidebar state
const isOpen = ref(true) // Start open by default

// Add toggle function
const toggleSidebar = () => {
    isOpen.value = !isOpen.value
}
</script>

<style scoped>
.opacity-0 {
    transition: opacity 0.2s;
}
.opacity-100 {
    transition: opacity 0.2s 0.1s;
}
</style>