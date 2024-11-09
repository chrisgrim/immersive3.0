<template>
    <div class="p-6 bg-white">
        <h1 class="text-2xl font-bold mb-6">Event Management</h1>
        
        <!-- Search and Filter Section -->
        <div class="mb-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input 
                    v-model="filters.search"
                    name="event_search"
                    autocomplete="off"
                    placeholder="Search by name or ID..."
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                <select 
                    v-model="filters.sort"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
                <select 
                    v-model="filters.status"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="published">Published Events</option>
                    <option value="in_progress">In Progress</option>
                    <option value="deleted">Deleted Events</option>
                </select>
            </div>
        </div>

        <!-- Events Table -->
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organization</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Left</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-xl">
                <tr v-for="event in events" :key="event.id">
                    <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-gray-50">
                        {{ event.id }}
                    </td>
                    <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-gray-50">
                        <img 
                            :src="getImageUrl(event)"
                            :alt="event.name"
                            class="h-10 w-10 rounded object-cover"
                            @error="handleImageError"
                        >
                    </td>
                    <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-gray-50">
                        {{ event.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ event.organizer?.name }}
                    </td>
                    <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-gray-50">
                        {{ formatLocation(event) }}
                    </td>
                    <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-gray-50">
                        {{ event.category?.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div 
                            :class="{
                                'bg-red-500 text-white font-bold': remainingDays(event.closingDate) < 12 && remainingDays(event.closingDate) !== 'Ended' && remainingDays(event.closingDate) !== 'N/A',
                                'bg-gray-200': remainingDays(event.closingDate) === 'Ended' || remainingDays(event.closingDate) === 'N/A'
                            }"
                            class="rounded-full w-12 h-12 flex items-center justify-center"
                        >
                            {{ remainingDays(event.closingDate) }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <template v-if="filters.status === 'in_progress'">
                            <a 
                                :href="`/hosting/event/${event.slug}/edit`"
                                class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                            >
                                Edit Event
                            </a>
                        </template>
                        <template v-else-if="filters.status === 'deleted'">
                            <button 
                                @click="resurrectEvent(event)"
                                class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600"
                            >
                                Restore Event
                            </button>
                        </template>
                        <template v-else>
                            {{ event.clicks?.length || 0 }} clicks
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="mt-6">
            <Pagination 
                v-if="pagination"
                :pagination="pagination"
                @paginate="handlePageChange"
            />
        </div>

        <!-- Action Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-xl font-bold mb-4">{{ selectedEvent?.name }}</h3>
                <div class="space-y-4">
                    <a 
                        :href="`/hosting/event/${selectedEvent?.slug}/edit`"
                        class="block w-full text-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                    >
                        Edit Event
                    </a>
                    <a 
                        :href="`/events/${selectedEvent?.slug}`"
                        class="block w-full text-center px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600"
                    >
                        View Event
                    </a>
                    <button 
                        @click="showModal = false"
                        class="block w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import dayjs from 'dayjs'

const imageUrl = import.meta.env.VITE_IMAGE_URL
const events = ref([])
const loading = ref(true)
const pagination = ref(null)
const filters = ref({
    search: '',
    sort: 'newest',
    status: 'published'
})

const showModal = ref(false)
const selectedEvent = ref(null)

const showActionModal = (event) => {
    selectedEvent.value = event
    showModal.value = true
}

const resurrectEvent = async (event) => {
    if (!confirm('Are you sure you want to restore this event?')) return

    try {
        await axios.patch(`/api/admin/manage/events/${event.id}`, {
            restore: true
        })
        // Refresh the events list
        fetchEvents(pagination.value?.current_page)
    } catch (error) {
        console.error('Error restoring event:', error)
        alert('Failed to restore event')
    }
}

// Add debounce function
const debounce = (callback, wait) => {
    let timeout
    return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => callback(...args), wait)
    }
}

// Watch for filter changes
watch(
    [() => filters.value.search, () => filters.value.sort, () => filters.value.status],
    debounce(() => fetchEvents(1), 300)
)

const remainingDays = (date) => {
    if (!date) return 'N/A'
    const daysLeft = dayjs(date).diff(dayjs(), 'day')
    if (daysLeft < 0) return 'Ended'
    return daysLeft
}

const formatLocation = (event) => {
    if (!event.hasLocation) {
        return 'Available anywhere'
    }

    const location = event.location
    if (!location) return 'Location not specified'

    const parts = []
    if (location.city) parts.push(location.city)
    if (location.region) parts.push(location.region)

    return parts.length > 0 ? parts.join(', ') : 'Location not specified'
}

const getImageUrl = (event) => {
    if (event.images && event.images.length > 0) {
        return `${imageUrl}${event.images[0].large_image_path}`
    }
    return 'https://placehold.co/40x40?text=No+Image'
}

const handleImageError = (e) => {
    e.target.src = 'https://placehold.co/40x40?text=No+Image'
}

const storeOriginalValue = (event) => {
    event.target.setAttribute('data-original', event.target.value)
}

const calculateRemainingDays = (endDate) => {
    if (!endDate) return 'N/A'
    const end = new Date(endDate)
    const now = new Date()
    const diffTime = end - now
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    return diffDays > 0 ? `${diffDays} days` : 'Ended'
}

const checkAndUpdateField = async (event, field, e) => {
    const originalValue = e.target.getAttribute('data-original')
    const newValue = e.target.value
    
    if (originalValue !== newValue) {
        try {
            if (confirm(`Are you sure you want to update this event's ${field}?`)) {
                const response = await axios.patch(`/api/admin/manage/events/${event.slug}`, {
                    [field]: newValue
                })
                
                Object.assign(event, response.data)
                e.target.setAttribute('data-original', newValue)
            } else {
                event[field] = originalValue
                e.target.value = originalValue
            }
        } catch (error) {
            event[field] = originalValue
            e.target.value = originalValue
            alert(error.response?.data?.message || `Error updating ${field}`)
        }
    }
}

const updateEventStatus = async (event) => {
    try {
        const response = await axios.patch(`/api/admin/manage/events/${event.slug}`, {
            status: event.status
        })
        Object.assign(event, response.data)
    } catch (error) {
        alert(error.response?.data?.message || 'Error updating status')
    }
}

const fetchEvents = async (page = 1) => {
    try {
        loading.value = true
        const response = await axios.get('/api/admin/manage/events', {
            params: { 
                page,
                ...filters.value
            }
        })
        events.value = response.data.data
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total,
            per_page: response.data.per_page
        }
    } catch (error) {
        console.error('Error fetching events:', error)
    } finally {
        loading.value = false
    }
}

const handlePageChange = (page) => {
    fetchEvents(page)
}

onMounted(() => {
    fetchEvents()
})
</script>

<style scoped>
input {
    background: transparent;
    width: 100%;
}

input:hover {
    background: #f8f8f8;
}

input:focus {
    background: white;
}
</style> 