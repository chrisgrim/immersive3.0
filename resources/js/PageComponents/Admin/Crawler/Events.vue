<template>
    <div class="p-6 bg-white">
        <h1 class="text-2xl font-bold mb-2">AI crawler</h1>
        <p class="text-gray-500 mb-6">
            Events Worker drafted from links it was given. Check each against its source, fix it in the editor or over MCP,
            then submit it from the editor. Once submitted it moves to the Approval Queue.
        </p>

        <div v-if="loading" class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
        </div>

        <div v-else-if="events.length === 0" class="text-center text-gray-500">
            No drafts waiting
        </div>

        <div v-else>
            <table class="w-full text-left">
                <thead class="text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="py-3 pr-4">Event</th>
                        <th class="py-3 pr-4">Organizer</th>
                        <th class="py-3 pr-4">Source</th>
                        <th class="py-3">Drafted</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Plain links throughout, so a middle-click opens a background tab. -->
                    <tr v-for="event in events" :key="event.id" class="border-b border-gray-100 align-top">
                        <td class="py-3 pr-4">
                            <a :href="`/hosting/event/${event.slug}/edit`" class="text-blue-600 hover:underline">{{ event.name || 'Untitled' }}</a>
                            <span v-if="event.sent_back" class="ml-2 bg-yellow-100 text-yellow-800 text-sm px-2 py-1 rounded-full">Sent back</span>
                        </td>
                        <td class="py-3 pr-4">
                            <template v-if="event.organizer">
                                <a :href="`/organizers/${event.organizer.slug}`" class="hover:underline">{{ event.organizer.name }}</a>
                                <span v-if="event.organizer.new" class="ml-2 bg-gray-100 text-gray-700 text-sm px-2 py-1 rounded-full"
                                      title="Not yet approved. Approving this event approves the organizer too, so check it as well.">New organizer</span>
                            </template>
                        </td>
                        <td class="py-3 pr-4 space-x-3">
                            <a v-if="event.websiteUrl" :href="event.websiteUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Website</a>
                            <a v-if="event.ticketUrl" :href="event.ticketUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Tickets</a>
                        </td>
                        <td class="py-3 text-gray-500">{{ new Date(event.created_at).toLocaleDateString() }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-6">
                <Pagination v-if="pagination" :pagination="pagination" @paginate="fetchEvents" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import Pagination from '@/GlobalComponents/pagination.vue'

const emit = defineEmits(['update-counts'])

const events = ref([])
const loading = ref(true)
const pagination = ref(null)
let page = 1

const fetchEvents = async (to = page) => {
    page = to
    try {
        const response = await axios.get('/api/admin/manage/crawler-events', { params: { page } })
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
        console.error('Error fetching crawler drafts:', error)
    } finally {
        loading.value = false
    }
}

// Back from an editor tab: something may have been submitted or fixed.
const refresh = () => {
    fetchEvents()
    emit('update-counts')
}

onMounted(() => {
    fetchEvents()
    window.addEventListener('focus', refresh)
})

onUnmounted(() => {
    window.removeEventListener('focus', refresh)
})
</script>
