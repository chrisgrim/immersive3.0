<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
            <h1 class="text-2xl font-bold mb-6">Event Reviews</h1>
            
            <!-- Search and Filter Section -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-4">
                    <input 
                        v-model="filters.search"
                        name="review_search"
                        autocomplete="off"
                        placeholder="Search by event name..."
                        class="w-auto min-w-[25rem] px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <button 
                        @click="showCreateModal = true"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                    >
                        Add New Review
                    </button>
                </div>
            </div>
        </div>

        <!-- Scrollable Table Section -->
        <div class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full overflow-hidden">
                <thead class="sticky top-0 bg-white">
                    <tr class="bg-neutral-100">
                        <th class="w-24 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th class="w-48 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Event</th>
                        <th class="w-48 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Reviewer</th>
                        <th class="w-48 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">URL</th>
                        <th class="w-96 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Review</th>
                        <th class="w-24 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                        <th class="w-32 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="w-24 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xl">
                    <tr v-for="review in reviews" :key="review.id">
                        <td class="px-6 py-4">
                            <picture class="block h-16 w-24">
                                <img 
                                    :src="`${imageUrl}${review.event?.thumbImagePath}`"
                                    :alt="review.event?.name"
                                    class="h-16 w-24 object-cover rounded"
                                    @error="handleImageError"
                                >
                            </picture>
                        </td>
                        <td class="px-6 py-4 max-w-[25rem] whitespace-normal break-words">
                            <a 
                                :href="`/events/${review.event?.slug}`" 
                                target="_blank"
                                class="text-blue-600 hover:text-blue-800"
                            >
                                {{ review.event?.name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                v-model="review.reviewer_name"
                                @focus="storeOriginalValue($event)"
                                @blur="updateReview(review, 'reviewer_name', $event)"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none"
                            >
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                v-model="review.url"
                                @focus="storeOriginalValue($event)"
                                @blur="updateReview(review, 'url', $event)"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <textarea 
                                v-model="review.review"
                                @focus="storeOriginalValue($event)"
                                @blur="updateReview(review, 'review', $event)"
                                class="px-2 py-1 w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none"
                                rows="3"
                            ></textarea>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                v-model.number="review.rank"
                                type="number"
                                min="1"
                                max="5"
                                @focus="storeOriginalValue($event)"
                                @blur="updateReview(review, 'rank', $event)"
                                class="px-2 py-1 w-20 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none"
                            >
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ formatDate(review.created_at) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button 
                                @click="confirmDelete(review)"
                                class="text-red-600 hover:text-red-900"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Fixed Footer with Pagination -->
        <div class="flex-none mt-4">
            <Pagination 
                v-if="pagination"
                :pagination="pagination"
                @paginate="handlePageChange"
            />
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                <h3 class="text-lg font-bold mb-4">Confirm Delete</h3>
                <p>Are you sure you want to delete this review? This action cannot be undone.</p>
                <div class="mt-6 flex justify-end space-x-4">
                    <button 
                        @click="showDeleteModal = false"
                        class="px-4 py-2 border rounded hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="deleteReview"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Review Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Add New Review</h2>
                    <p class="text-gray-500 font-normal">Create a new review for an event</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <form @submit.prevent="createReview" class="space-y-6">
                        <div>
                            <p class="text-gray-500 font-normal mb-4">Event</p>
                            <input 
                                v-model="eventSearch"
                                placeholder="Search for event..."
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                @input="searchEvents"
                            >
                            <!-- Event Search Results -->
                            <div v-if="eventSearchResults.length > 0" class="mt-2 border border-neutral-400 rounded-2xl shadow-sm max-h-96 overflow-y-auto">
                                <div 
                                    v-for="event in eventSearchResults" 
                                    :key="event.id"
                                    @click="selectEvent(event)"
                                    class="p-4 hover:bg-ne cursor-pointer border-b last:border-b-0 flex items-center space-x-4"
                                >
                                    <img 
                                        :src="`${imageUrl}${event.thumbImagePath}`"
                                        :alt="event.name"
                                        class="h-20 w-32 object-cover rounded-xl"
                                    >
                                    <div>
                                        <div class="text-xl">{{ event.name }}</div>
                                        <div class="text-gray-500">{{ event.start_date }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Event Preview -->
                        <div v-if="selectedEvent" class="p-4 bg-ne rounded-2xl border border-neutral-400">
                            <div class="flex items-center space-x-4">
                                <img 
                                    :src="`${imageUrl}${selectedEvent.thumbImagePath}`"
                                    :alt="selectedEvent.name"
                                    class="h-20 w-32 object-cover rounded-xl"
                                >
                                <div>
                                    <div class="text-xl">{{ selectedEvent.name }}</div>
                                    <div class="text-gray-500">{{ selectedEvent.start_date }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Reviewer Name</p>
                                <input 
                                    v-model="newReview.reviewer_name"
                                    required
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter reviewer name"
                                >
                            </div>
                            <div>
                                <p class="text-gray-500 font-normal mb-4">URL</p>
                                <input 
                                    v-model="newReview.url"
                                    required
                                    type="url"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter review URL"
                                >
                            </div>
                        </div>

                        <div>
                            <p class="text-gray-500 font-normal mb-4">Review</p>
                            <textarea 
                                v-model="newReview.review"
                                required
                                rows="6"
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                placeholder="Enter review text"
                            ></textarea>
                        </div>

                        <div>
                            <p class="text-gray-500 font-normal mb-4">Rating (1-5)</p>
                            <select 
                                v-model.number="newReview.rank"
                                required
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                            >
                                <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="closeCreateModal"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-ne text-xl"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="createReview"
                            class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                        >
                            Create
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import dayjs from 'dayjs'

const imageUrl = import.meta.env.VITE_IMAGE_URL
const reviews = ref([])
const loading = ref(true)
const pagination = ref(null)
const showDeleteModal = ref(false)
const reviewToDelete = ref(null)
const showCreateModal = ref(false)
const eventSearch = ref('')
const eventSearchResults = ref([])
const selectedEvent = ref(null)
const newReview = ref({
    reviewer_name: '',
    url: '',
    review: '',
    rank: 5
})

const filters = ref({
    search: ''
})

// Debounce function
const debounce = (callback, wait) => {
    let timeout
    return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => callback(...args), wait)
    }
}

// Watch for search changes
watch(
    () => filters.value.search,
    debounce(() => fetchReviews(1), 300)
)

const fetchReviews = async (page = 1) => {
    try {
        loading.value = true
        const response = await axios.get('/api/admin/manage/reviews', {
            params: { 
                page,
                search: filters.value.search
            }
        })
        reviews.value = response.data.data
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total,
            per_page: response.data.per_page
        }
    } catch (error) {
        console.error('Error fetching reviews:', error)
    } finally {
        loading.value = false
    }
}

const storeOriginalValue = (event) => {
    event.target.setAttribute('data-original', event.target.value)
}

const updateReview = async (review, field, event) => {
    const originalValue = event.target.getAttribute('data-original')
    const newValue = event.target.value

    if (originalValue !== newValue) {
        try {
            if (confirm(`Are you sure you want to update this review's ${field}?`)) {
                const response = await axios.patch(`/api/admin/manage/reviews/${review.id}`, {
                    reviewer_name: review.reviewer_name,
                    url: review.url,
                    review: review.review,
                    rank: review.rank
                })
                Object.assign(review, response.data)
                event.target.setAttribute('data-original', newValue)
            } else {
                review[field] = originalValue
                event.target.value = originalValue
            }
        } catch (error) {
            console.error('Update error:', error)
            review[field] = originalValue
            event.target.value = originalValue
            alert(error.response?.data?.message || `Error updating ${field}`)
        }
    }
}

const confirmDelete = (review) => {
    reviewToDelete.value = review
    showDeleteModal.value = true
}

const deleteReview = async () => {
    if (!reviewToDelete.value) return

    try {
        await axios.delete(`/api/admin/manage/reviews/${reviewToDelete.value.id}`)
        await fetchReviews(pagination.value?.current_page)
        showDeleteModal.value = false
        reviewToDelete.value = null
    } catch (error) {
        console.error('Error deleting review:', error)
        alert(error.response?.data?.message || 'Error deleting review')
    }
}

const handlePageChange = (page) => {
    fetchReviews(page)
}

// Debounced event search
const searchEvents = debounce(async () => {
    if (!eventSearch.value) {
        eventSearchResults.value = []
        return
    }
    try {
        const response = await axios.get('/api/admin/manage/events', {
            params: { search: eventSearch.value }
        })
        eventSearchResults.value = response.data.data
    } catch (error) {
        console.error('Error searching events:', error)
    }
}, 300)

const selectEvent = (event) => {
    selectedEvent.value = event
    eventSearch.value = event.name
    eventSearchResults.value = []
}

const closeCreateModal = () => {
    showCreateModal.value = false
    eventSearch.value = ''
    eventSearchResults.value = []
    selectedEvent.value = null
    newReview.value = {
        reviewer_name: '',
        url: '',
        review: '',
        rank: 5
    }
}

const createReview = async () => {
    if (!selectedEvent.value) {
        alert('Please select an event')
        return
    }

    try {
        const reviewData = {
            event: selectedEvent.value,
            reviewername: newReview.value.reviewer_name,
            url: newReview.value.url,
            review: newReview.value.review,
            rank: parseInt(newReview.value.rank)
        }

        await axios.post('/api/admin/manage/reviews', reviewData)
        await fetchReviews()
        closeCreateModal()
    } catch (error) {
        console.error('Error creating review:', error)
        alert(error.response?.data?.message || 'Error creating review')
    }
}

const formatDate = (date) => {
    return dayjs(date).format('MMM D, YYYY')
}

onMounted(() => {
    fetchReviews()
})
</script>

<style scoped>
input {
    background: transparent;
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

/* Add these new styles for consistent input/textarea appearance */
input, textarea {
    width: 100%;
    background: transparent;
}

textarea {
    resize: vertical;
    min-height: 3rem;
}

input:hover, textarea:hover {
    background: #f8f8f8;
}

input:focus, textarea:focus {
    background: white;
}

.loading-spinner {
    @apply animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto;
}
</style>
