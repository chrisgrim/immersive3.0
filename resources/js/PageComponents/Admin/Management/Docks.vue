<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
            <h1 class="text-2xl font-bold mb-6">Docks Management</h1>
            
            <!-- Filter and Add Button Section -->
            <div class="flex justify-between items-center mb-6">
                <!-- Location Type Tabs -->
                <div class="flex gap-4">
                    <button 
                        v-for="location in locations"
                        :key="location.name"
                        @click="activeLocation = location.name"
                        :class="[
                            'px-4 py-2 rounded-lg transition-colors capitalize',
                            activeLocation === location.name 
                                ? 'bg-blue-500 text-white' 
                                : 'bg-gray-100 hover:bg-gray-200'
                        ]"
                    >
                        {{ location.name }} Page
                    </button>
                </div>

                <button 
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                >
                    Add New Dock
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!docks.length" class="flex-1 flex items-center justify-center text-gray-500">
            No docks found
        </div>

        <!-- Scrollable Table Section -->
        <div v-else class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="sticky top-0 bg-white shadow-sm">
                    <tr class="bg-neutral-100">
                        <th 
                            v-for="col in cols"
                            :key="col.id"
                            class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider"
                            :class="col.class"
                        >
                            {{ col.field }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="dock in filteredDocks(activeLocation)" :key="dock.id">
                        <td class="px-6 py-4">
                            <select 
                                v-model="dock.location" 
                                class="w-full text-xl border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none px-2 py-1" 
                                @change="onUpdate(dock)"
                            >
                                <option v-for="loc in locations" :key="loc.name" :value="loc.name">
                                    {{ loc.name }}
                                </option>
                            </select>
                        </td>
                        <td class="px-6 py-4">
                            <select 
                                v-model="dock.type"
                                class="w-full text-xl border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none px-2 py-1"
                                @change="onUpdate(dock)"
                            >
                                <option value="f">Four</option>
                                <option value="t">Three</option>
                                <option value="i">Icon</option>
                                <option value="h">Hero</option>
                                <option value="s">Spotlight</option>
                            </select>
                        </td>
                        <td class="px-6 py-4">
                            <input 
                                v-model="dock.name"
                                @blur="onUpdate(dock)"
                                class="w-full text-xl border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none px-2 py-1"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-2">
                                <!-- If there's a shelf assigned -->
                                <div v-if="dock.shelves?.length" class="flex items-center gap-4">
                                    <div class="flex-1">
                                        <div class="font-medium">{{ dock.shelves[0].name }}</div>
                                        <!-- Preview Grid -->
                                        <div v-if="dock.shelves[0].posts?.length" class="flex gap-2 mt-2">
                                            <div 
                                                v-for="post in dock.shelves[0].posts.slice(0, 4)" 
                                                :key="post.id" 
                                                class="aspect-square w-12 rounded-lg overflow-hidden"
                                            >
                                                <template v-if="getPostImage(post)">
                                                    <img 
                                                        :src="getImageUrl(getPostImage(post))" 
                                                        :alt="post.name"
                                                        class="w-full h-full object-cover"
                                                    >
                                                </template>
                                                <template v-else>
                                                    <div 
                                                        class="w-full h-full flex items-center justify-center"
                                                        style="background-color: #c69669"
                                                    >
                                                        <span class="text-xl font-bold text-white">
                                                            {{ post.name?.charAt(0).toUpperCase() || '?' }}
                                                        </span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <button 
                                        @click="openManageContentModal(dock)"
                                        class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-700"
                                    >
                                        <span>Change</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                <!-- If no shelf is assigned -->
                                <button 
                                    v-else
                                    @click="openManageContentModal(dock)"
                                    class="px-3 py-1.5 border border-dashed border-gray-300 hover:border-gray-400 rounded-lg text-sm text-gray-500 hover:text-gray-700"
                                >
                                    Add Content
                                </button>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <input 
                                v-model.number="dock.order"
                                type="number"
                                @blur="onUpdate(dock)"
                                class="w-24 text-xl border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none px-2 py-1"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <button 
                                @click="modalDelete = dock"
                                class="text-red-600 hover:text-red-900"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <teleport to="body">
        <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Add New Dock</h2>
                    <p class="text-gray-500 font-normal">Create a new dock for content placement</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-6">
                        <!-- Name field -->
                        <div>
                            <p class="text-gray-500 font-normal mb-4">Name</p>
                            <input 
                                v-model="newDock.name"
                                type="text"
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                placeholder="Enter dock name"
                            >
                        </div>

                        <!-- Type and Location fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Type</p>
                                <select 
                                    v-model="newDock.type"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                >
                                    <option value="f">Four</option>
                                    <option value="t">Three</option>
                                    <option value="i">Icon</option>
                                    <option value="h">Hero</option>
                                    <option value="s">Spotlight</option>
                                </select>
                            </div>

                            <div>
                                <p class="text-gray-500 font-normal mb-4">Location</p>
                                <select 
                                    v-model="newDock.location"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                >
                                    <option value="none">None</option>
                                    <option value="home">Home</option>
                                    <option value="search">Search</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="showCreateModal = false"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-ne text-xl"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="createDock"
                            class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                        >
                            Create
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>

    <!-- Delete Confirmation Modal -->
    <teleport to="body">
        <div v-if="modalDelete" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white w-full max-w-md mx-4 rounded-2xl p-8">
                <h3 class="text-xl font-bold mb-4">Delete Confirmation</h3>
                <p class="text-gray-600 mb-6">You are deleting the Dock. Please be sure you know what you are doing.</p>
                <div class="flex justify-end space-x-4">
                    <button 
                        @click="modalDelete = null"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-ne"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="onDelete"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </teleport>

    <!-- Manage Content Modal -->
    <teleport to="body">
        <div v-if="contentModal.show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white w-full max-w-4xl mx-4 rounded-2xl flex flex-col" style="max-height: 90vh;">
                <!-- Header -->
                <div class="flex-none px-8 py-6 border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-xl font-bold">Manage Content for "{{ contentModal.dock?.name }}"</h3>
                            <p class="text-sm text-gray-500 mt-1">Select a shelf to associate with this dock</p>
                        </div>
                        <button @click="contentModal.show = false" class="text-gray-400 hover:text-gray-600">
                            <span class="sr-only">Close</span>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Community Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Community</label>
                        <select 
                            v-model="contentModal.selectedCommunityId"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">Select a community</option>
                            <option 
                                v-for="community in availableCommunities" 
                                :key="community.id" 
                                :value="community.id"
                            >
                                {{ community.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Scrollable Content -->
                <div class="flex-1 overflow-y-auto px-8 py-6">
                    <!-- Shelves List -->
                    <div v-if="contentModal.selectedCommunityId" class="space-y-4">
                        <div 
                            v-for="shelf in filteredShelves" 
                            :key="shelf.id" 
                            class="bg-ne rounded-xl p-4 hover:bg-gray-100 transition-colors"
                        >
                            <div class="flex items-center gap-4 mb-3">
                                <input 
                                    type="radio"
                                    :id="'shelf-' + shelf.id"
                                    :checked="isShelfSelected(shelf.id)"
                                    @change="toggleShelf(shelf)"
                                    class="w-5 h-5 border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <label :for="'shelf-' + shelf.id" class="flex-1 cursor-pointer">
                                    <div class="font-medium text-lg">{{ shelf.name }}</div>
                                    <div class="text-sm text-gray-500">{{ shelf.description || 'No description' }}</div>
                                </label>
                            </div>

                            <!-- Preview Grid -->
                            <div v-if="shelf.posts?.length" class="flex gap-4 pl-9">
                                <div 
                                    v-for="post in shelf.posts.slice(0, 4)" 
                                    :key="post.id" 
                                    class="aspect-square w-16 rounded-lg overflow-hidden"
                                >
                                    <template v-if="getPostImage(post)">
                                        <img 
                                            :src="getImageUrl(getPostImage(post))" 
                                            :alt="post.name"
                                            class="w-full h-full object-cover"
                                        >
                                    </template>
                                    <template v-else>
                                        <div 
                                            class="w-full h-full flex items-center justify-center"
                                            style="background-color: #c69669"
                                        >
                                            <span class="text-2xl font-bold text-white">
                                                {{ post.name?.charAt(0).toUpperCase() || '?' }}
                                            </span>
                                        </div>
                                    </template>
                                    <div class="p-1 text-xs truncate">{{ post.name }}</div>
                                </div>
                            </div>
                            <div v-else class="pl-9 text-sm text-gray-500">
                                No posts in this shelf
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center text-gray-500 py-8">
                        Select a community to view its shelves
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex-none px-8 py-6 border-t border-gray-200 bg-ne">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            Changes are saved automatically
                        </div>
                        <button 
                            @click="contentModal.show = false"
                            class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { maxLength, required } from '@vuelidate/validators'
import axios from 'axios'

// Define props
defineProps({
    event: {
        type: Object,
        default: null
    }
})

// Define emits
defineEmits(['selectEvent', 'approved', 'rejected'])

const imageUrl = import.meta.env.VITE_IMAGE_URL

// Reactive state
const docks = ref([])
const loading = ref(true)
const modalDelete = ref(null)
const showCreateModal = ref(false)
const newDock = ref({
    name: '',
    type: 'f',
    location: 'none',
    order: 0
})

const locations = ref([
    { name: 'home' },
    { name: 'search' },
    { name: 'none' },
])

const activeLocation = ref('home')

const cols = ref([
    { id: 0, field: 'Location', class: '' },
    { id: 1, field: 'Type', class: '' },
    { id: 2, field: 'Name', class: '' },
    { id: 3, field: 'Content', class: '' },
    { id: 4, field: 'Order', class: 'w-24' },
    { id: 5, field: 'Actions', class: '' },
])

// Validation rules
const rules = {
    name: { required, maxLength: maxLength(100) },
    type: { required },
    location: { required }
}

// Setup vuelidate
const v$ = useVuelidate(rules, newDock)

// Add these new reactive states
const contentModal = ref({
    show: false,
    dock: null,
    selectedCommunityId: ''
})
const availableShelves = ref([])
const availableCommunities = ref([])

// Add computed property for filtered shelves
const filteredShelves = computed(() => {
    if (!contentModal.value.selectedCommunityId) return []
    return availableShelves.value.filter(shelf => 
        shelf.community_id === contentModal.value.selectedCommunityId
    )
})

// Methods
const fetchDocks = async () => {
    try {
        loading.value = true
        const response = await axios.get('/api/admin/docks')
        docks.value = response.data
        console.log('Fetched docks:', response.data)
    } catch (error) {
        console.error('Error fetching docks:', error)
    } finally {
        loading.value = false
    }
}

const onUpdate = async (dock) => {
    try {
        const response = await axios.post(`/api/admin/docks/${dock.id}`, dock)
        docks.value = response.data
    } catch (error) {
        console.error('Error updating dock:', error)
        alert(error.response?.data?.message || 'Error updating dock')
    }
}

const onDelete = async () => {
    try {
        await axios.delete(`/api/admin/docks/${modalDelete.value.id}`)
        docks.value = docks.value.filter(d => d.id !== modalDelete.value.id)
        modalDelete.value = null
    } catch (error) {
        console.error('Error deleting dock:', error)
        alert(error.response?.data?.message || 'Error deleting dock')
    }
}

const createDock = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    try {
        const response = await axios.post('/api/admin/docks', newDock.value)
        docks.value = response.data
        showCreateModal.value = false
        v$.value.$reset()
        newDock.value = {
            name: '',
            type: 'f',
            location: 'none',
            order: 0
        }
    } catch (error) {
        console.error('Error creating dock:', error)
        alert(error.response?.data?.message || 'Error creating dock')
    }
}

const filteredDocks = (location) => {
    return docks.value?.filter(dock => dock.location === location) || []
}

const hasDocks = (location) => {
    return docks.value?.filter(dock => dock.location === location.name)?.length > 0 || false
}

const openManageContentModal = async (dock) => {
    contentModal.value = {
        show: true,
        dock: dock,
        selectedCommunityId: ''
    }

    await Promise.all([
        fetchAvailableShelves(),
        fetchAvailableCommunities()
    ])

    // If there's a shelf assigned, set its community for filtering
    if (dock.shelves?.length) {
        const shelf = dock.shelves[0]
        const community = availableShelves.value.find(s => s.id === shelf.id)?.community
        if (community) {
            contentModal.value.selectedCommunityId = community.id
        }
    }
}

const fetchAvailableShelves = async () => {
    try {
        const response = await axios.get('/api/admin/docks/available-shelves')
        availableShelves.value = response.data
    } catch (error) {
        console.error('Error fetching shelves:', error)
    }
}

const fetchAvailableCommunities = async () => {
    try {
        const response = await axios.get('/api/admin/docks/available-communities')
        availableCommunities.value = response.data
    } catch (error) {
        console.error('Error fetching communities:', error)
    }
}

const isShelfSelected = (shelfId) => {
    return contentModal.value.dock?.shelves?.some(s => s.id === shelfId) || false
}

const isCommunitySelected = (communityId) => {
    return contentModal.value.dock?.communities?.some(c => c.id === communityId) || false
}

const toggleShelf = async (shelf) => {
    try {
        const isSelected = isShelfSelected(shelf.id)
        const response = await axios.post(`/api/admin/docks/${contentModal.value.dock.id}/shelves`, {
            shelf_id: shelf.id,
            action: isSelected ? 'detach' : 'attach'
        })
        // Update the dock's shelves in the local state
        const dockIndex = docks.value.findIndex(d => d.id === contentModal.value.dock.id)
        if (dockIndex !== -1) {
            docks.value[dockIndex] = response.data
            contentModal.value.dock = response.data
        }
    } catch (error) {
        console.error('Error toggling shelf:', error)
        alert(error.response?.data?.message || 'Error updating shelf association')
    }
}

const toggleCommunity = async (community) => {
    try {
        const isSelected = isCommunitySelected(community.id)
        const response = await axios.post(`/api/admin/docks/${contentModal.value.dock.id}/communities`, {
            community_id: community.id,
            action: isSelected ? 'detach' : 'attach'
        })
        // Update the dock's communities in the local state
        const dockIndex = docks.value.findIndex(d => d.id === contentModal.value.dock.id)
        if (dockIndex !== -1) {
            docks.value[dockIndex] = response.data
            contentModal.value.dock = response.data
        }
    } catch (error) {
        console.error('Error toggling community:', error)
        alert(error.response?.data?.message || 'Error updating community association')
    }
}

const getContentCount = (dock) => {
    if (dock.shelves?.length) {
        return `${dock.shelves.length} Shelves`
    }
    return 'No content'
}

const getImageUrl = (path) => {
    if (!path) return ''
    return `${imageUrl}${path}`
}

const getPostImage = (post) => {
    // First check for post images
    if (post.images && post.images.length > 0) {
        return post.images[0].thumb_image_path || post.images[0].large_image_path
    }
    
    // Then check for featured event image
    if (post.featured_event_image) {
        return post.featured_event_image.thumbImagePath || post.featured_event_image.largeImagePath
    }
    
    // Then check for images in limited cards
    if (post.limited_cards && post.limited_cards.length > 0) {
        for (const card of post.limited_cards) {
            if (card.event) {
                if (card.event.thumbImagePath) {
                    return card.event.thumbImagePath
                }
                if (card.event.largeImagePath) {
                    return card.event.largeImagePath
                }
            }
        }
    }
    
    // Finally check post's own image paths
    if (post.thumbImagePath || post.largeImagePath) {
        return post.thumbImagePath || post.largeImagePath
    }
    
    // Return null if no image is found
    return null
}

// Lifecycle hooks
onMounted(() => {
    fetchDocks()
})
</script>

<style scoped>
thead {
    position: sticky;
    top: 0;
    z-index: 10;
}
</style>