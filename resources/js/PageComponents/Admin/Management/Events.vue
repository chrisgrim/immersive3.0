<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
            <h1 class="text-2xl font-bold mb-6">Event Management</h1>
            
            <!-- Search and Filter Section -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-4">
                    <input 
                        v-model="filters.search"
                        name="event_search"
                        autocomplete="off"
                        placeholder="Search by name or ID..."
                        class="w-auto min-w-[25rem] px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <select 
                        v-model="filters.sort"
                        class="w-auto px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                    </select>
                    <select 
                        v-model="filters.status"
                        class="w-auto px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="published">Published Events</option>
                        <option value="in_progress">In Progress</option>
                        <option value="deleted">Deleted Events</option>
                    </select>
                    <button 
                        @click="toggleEndingSoon"
                        :class="[
                            'px-4 py-2 rounded-lg transition-colors',
                            filters.endingSoon ? 'bg-blue-500 text-white' : 'bg-gray-100 hover:bg-gray-200'
                        ]"
                    >
                        Ending Soon
                    </button>
                </div>
            </div>
        </div>

        <!-- Scrollable Table Section -->
        <div class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full overflow-hidden">
                <thead class="sticky top-0 bg-white shadow-sm">
                    <tr class="bg-neutral-100">
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[4rem]">ID</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[4rem]">Image</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[18rem]">Name</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[18rem]">Organization</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Days Left</th>
                        <th class="px-2 py-3 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">Clicks</th>
                        <th class="px-2 py-3 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">Curated</th>
                        <th class="px-2 py-3 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">Social</th>
                        <th class="px-2 py-3 text-center text-sm font-medium text-gray-500 uppercase tracking-wider">Newsltr</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xl">
                    <tr v-for="event in events" :key="event.id">
                        <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-ne">
                            {{ event.id }}
                        </td>
                        <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-ne">
                            <picture class="block h-10 w-10">
                                <source 
                                    v-if="event.images && event.images.length > 0"
                                    :srcset="`${imageUrl}${event.images[0].large_image_path}`"
                                    type="image/webp"
                                >
                                <source 
                                    v-if="event.thumbImagePath"
                                    :srcset="`${imageUrl}${event.thumbImagePath}`"
                                    type="image/webp"
                                >
                                <img 
                                    :src="getImageUrl(event)"
                                    :alt="event.name"
                                    class="h-10 w-10 rounded object-cover"
                                    @error="handleImageError"
                                >
                            </picture>
                        </td>
                        <td class="px-6 py-4 max-w-[25rem] whitespace-normal break-words hyphens-auto">
                            <a 
                                :href="`/events/${event.slug}`"
                                class="text-blue-600 hover:text-blue-800 hover:underline"
                            >
                                {{ event.name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 max-w-[25rem] whitespace-normal break-words hyphens-auto">
                            <button 
                                @click="toggleOrganizerChange(event)"
                                class="text-left hover:underline"
                            >
                                {{ event.organizer?.name || 'Assign Organizer' }}
                            </button>
                        </td>
                        <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-ne">
                            {{ formatLocation(event) }}
                        </td>
                        <td @click="showActionModal(event)" class="px-6 py-4 whitespace-nowrap cursor-pointer hover:bg-ne">
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
                        <td class="px-2 py-4 whitespace-nowrap flex justify-center items-center">
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
                                {{ event.total_clicks || 0 }}
                            </template>
                        </td>
                        <td class="px-2 py-4 whitespace-nowrap text-center">
                            <button 
                                @click="toggleEventCheck(event, 'curated')"
                                :class="[
                                    'w-8 h-8 rounded-full',
                                    event.curated_check?.curated === true 
                                        ? 'bg-green-500 hover:bg-green-600' 
                                        : event.curated_check?.curated === false
                                            ? 'bg-red-500 hover:bg-red-600'
                                            : 'bg-gray-200 hover:bg-gray-300'
                                ]"
                                :title="getButtonTitle(event, 'curated')"
                            >
                                <svg 
                                    v-if="event.curated_check?.curated === true"
                                    class="w-5 h-5 text-white mx-auto" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg 
                                    v-else-if="event.curated_check?.curated === false"
                                    class="w-5 h-5 text-white mx-auto" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </td>
                        <td class="px-2 py-4 whitespace-nowrap text-center">
                            <button 
                                @click="toggleEventCheck(event, 'social')"
                                :class="[
                                    'w-8 h-8 rounded-full',
                                    event.curated_check?.social === true
                                        ? 'bg-green-500 hover:bg-green-600' 
                                        : event.curated_check?.social === false
                                            ? 'bg-red-500 hover:bg-red-600'
                                            : 'bg-gray-200 hover:bg-gray-300'
                                ]"
                                :title="getButtonTitle(event, 'social')"
                            >
                                <svg 
                                    v-if="event.curated_check?.social === true"
                                    class="w-5 h-5 text-white mx-auto" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg 
                                    v-else-if="event.curated_check?.social === false"
                                    class="w-5 h-5 text-white mx-auto" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </td>
                        <td class="px-2 py-4 whitespace-nowrap text-center">
                            <button 
                                @click="toggleEventCheck(event, 'newsletter')"
                                :class="[
                                    'w-8 h-8 rounded-full',
                                    event.curated_check?.newsletter === true
                                        ? 'bg-green-500 hover:bg-green-600' 
                                        : event.curated_check?.newsletter === false
                                            ? 'bg-red-500 hover:bg-red-600'
                                            : 'bg-gray-200 hover:bg-gray-300'
                                ]"
                                :title="getButtonTitle(event, 'newsletter')"
                            >
                                <svg 
                                    v-if="event.curated_check?.newsletter === true"
                                    class="w-5 h-5 text-white mx-auto" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg 
                                    v-else-if="event.curated_check?.newsletter === false"
                                    class="w-5 h-5 text-white mx-auto" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Fixed Footer Section -->
        <div class="flex-none mt-4">
            <Pagination 
                v-if="pagination"
                :pagination="pagination"
                @paginate="handlePageChange"
            />
        </div>

        <!-- Action Modal -->
        <div 
            v-if="showModal" 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click="showModal = false"
        >
            <div 
                class="bg-white rounded-lg p-6 max-w-md w-full relative"
                @click.stop
            >
                <button 
                    @click="showModal = false"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="text-center mb-6">
                    <picture class="block w-48 mx-auto mt-12">
                        <source 
                            v-if="selectedEvent?.images && selectedEvent.images.length > 0"
                            :srcset="`${imageUrl}${selectedEvent.images[0].large_image_path}`"
                            type="image/webp"
                        >
                        <source 
                            v-if="selectedEvent?.thumbImagePath"
                            :srcset="`${imageUrl}${selectedEvent.thumbImagePath}`"
                            type="image/webp"
                        >
                        <img 
                            :src="getImageUrl(selectedEvent)"
                            :alt="selectedEvent?.name"
                            class="w-48 h-64 rounded-lg object-cover mx-auto"
                            @error="handleImageError"
                        >
                    </picture>
                    <h3 class="text-xl font-bold mt-4">{{ selectedEvent?.name }}</h3>
                </div>

                <div class="space-y-4">
                    <a 
                        :href="`/hosting/event/${selectedEvent?.slug}/edit`"
                        class="block w-full text-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                    >
                        Edit Event
                    </a>
                    <a 
                        :href="`/events/${selectedEvent?.slug}`"
                        class="block w-full text-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-600"
                    >
                        View Event
                    </a>
                </div>
            </div>
        </div>

        <!-- Organizer Selection Modal -->
        <div v-if="showOrganizerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Change Organizer</h2>
                    <p class="text-gray-500 font-normal">Select a new organizer for "{{ selectedOrganizerEvent?.name }}"</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-6">
                        <div>
                            <p class="text-gray-500 font-normal mb-4">Search Organizers</p>
                            <input 
                                v-model="organizerSearch"
                                placeholder="Search by name..."
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                            >
                        </div>

                        <!-- Search Results -->
                        <div v-if="organizerSearchResults.length > 0" class="space-y-2">
                            <div 
                                v-for="result in organizerSearchResults" 
                                :key="result.model.id"
                                @click="updateEventOrganizer(selectedOrganizerEvent, result.model)"
                                class="p-4 border border-neutral-400 rounded-2xl hover:bg-gray-100 cursor-pointer flex items-center"
                            >
                                <div class="text-xl">{{ result.model.name }}</div>
                            </div>
                        </div>
                        <div v-else-if="organizerSearch && organizerSearch.length >= 2" class="p-4 text-center text-gray-500">
                            No organizers found matching "{{ organizerSearch }}".
                        </div>
                        <div v-else-if="organizerSearch && organizerSearch.length < 2" class="p-4 text-center text-gray-500">
                            Please enter at least 2 characters to search.
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="closeOrganizerModal"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-gray-100 text-xl"
                        >
                            Cancel
                        </button>
                    </div>
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
    status: 'published',
    endingSoon: false
})

const showModal = ref(false)
const selectedEvent = ref(null)

// Organizer change functionality
const showOrganizerModal = ref(false)
const selectedOrganizerEvent = ref(null)
const organizerSearch = ref('')
const organizerSearchResults = ref([])

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
    [
        () => filters.value.search, 
        () => filters.value.sort, 
        () => filters.value.status,
        () => filters.value.endingSoon
    ],
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
    if (event.thumbImagePath) {
        return `${imageUrl}${event.thumbImagePath}`
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

const toggleEndingSoon = () => {
    filters.value.endingSoon = !filters.value.endingSoon;
    fetchEvents(1);
}

const fetchEvents = async (page = 1) => {
    try {
        loading.value = true
        const params = { 
            page,
            search: filters.value.search,
            sort: filters.value.sort,
            status: filters.value.status
        }

        // Add ending soon parameters if toggled
        if (filters.value.endingSoon) {
            params.ending_soon = true;
            params.days_threshold = 10;
            params.show_requirement = true; // New parameter to check for show requirements
        }

        const response = await axios.get('/api/admin/manage/events', {
            params: params
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

const getButtonTitle = (event, type) => {
    if (!event.curated_check) return 'Not set';
    
    const value = event.curated_check[type];
    if (value === true) return `Remove from ${type}`;
    if (value === false) return `Explicitly excluded from ${type}`;
    return `Add to ${type}`; // null case
}

const toggleEventCheck = async (event, type) => {
    try {
        const response = await axios.patch(`/api/admin/manage/events/${event.slug}/toggle-check`, {
            type: type
        });
        
        // If the event doesn't have a curated_check object yet, create one
        if (!event.curated_check) {
            event.curated_check = {
                curated: null,
                social: null,
                newsletter: null
            };
        }
        
        // Update with the response value from the server
        if (response.data && response.data.check) {
            event.curated_check[type] = response.data.check[type];
        } else {
            // Cycle through states: null -> false -> true -> null
            if (event.curated_check[type] === null) {
                event.curated_check[type] = false;
            } else if (event.curated_check[type] === false) {
                event.curated_check[type] = true;
            } else {
                event.curated_check[type] = null;
            }
        }
    } catch (error) {
        console.error(`Error toggling ${type}:`, error);
        alert(error.response?.data?.message || `Error updating ${type} status`);
    }
}

const toggleOrganizerChange = (event) => {
    selectedOrganizerEvent.value = event
    showOrganizerModal.value = true
    organizerSearch.value = ''
    organizerSearchResults.value = []
}

const closeOrganizerModal = () => {
    showOrganizerModal.value = false
    selectedOrganizerEvent.value = null
    organizerSearch.value = ''
    organizerSearchResults.value = []
}

const updateEventOrganizer = async (event, organizer) => {
    try {
        if (confirm(`Are you sure you want to change the organizer to "${organizer.name}"?`)) {
            const response = await axios.patch(`/api/admin/manage/events/${event.id}`, {
                action: 'update_organizer',
                organizer_id: organizer.id
            })
            
            // Update the event with the new data from the response
            Object.assign(event, response.data)
            closeOrganizerModal()
        }
    } catch (error) {
        console.error('Error updating organizer:', error)
        alert(error.response?.data?.message || `Error updating organizer: ${error.message}`)
    }
}

// Watch for organizer search changes
watch(organizerSearch, debounce(async () => {
    if (!organizerSearch.value) {
        organizerSearchResults.value = []
        return
    }
    try {
        const response = await axios.get('/api/search/nav/organizers', {
            params: { 
                keywords: organizerSearch.value,
                limit: 6
            }
        })
        console.log('Organizer search response:', response.data)
        organizerSearchResults.value = response.data || []
    } catch (error) {
        console.error('Error searching organizers:', error)
    }
}, 300))

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

/* Add sticky header styles */
thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: white;
}

/* Ensure borders remain visible on sticky header */
th {
    position: relative;
}

th::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    border-bottom: 1px solid #e5e7eb;
}
</style> 