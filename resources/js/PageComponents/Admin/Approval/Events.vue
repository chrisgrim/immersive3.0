<template>
    <div class="p-6 bg-white">
        <h1 class="text-2xl font-bold mb-6">Events Ready for Review</h1>
        
        <div v-if="loading" class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
        </div>
        
        <div v-else-if="events.length === 0" class="text-center text-gray-500">
            No events ready for review
        </div>
        
        <div v-else>
            <event-grid 
                :items="events"
                :user="user"
                :columns="5"
                :hasClickListener="true"
                @click:item="handleEventSelect"
            />

            <div class="mt-6">
                <Pagination 
                    v-if="pagination"
                    :pagination="pagination"
                    @paginate="handlePageChange"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import EventGrid from '@/GlobalComponents/Grid/event-grid.vue'

const events = ref([])
const loading = ref(true)
const pagination = ref(null)

const emit = defineEmits(['select-event', 'update-counts'])

const handleEventSelect = async (event) => {
    try {
        const response = await axios.get(`/api/admin/events/${event.slug}`)
        emit('select-event', response.data)
    } catch (error) {
        console.error('Error fetching event details:', error)
    }
}

const handlePageChange = (page) => {
    fetchEvents(page)
}

const fetchEvents = async (page = 1) => {
    try {
        const response = await axios.get('/api/admin/approve/events', {
            params: { page }
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

onMounted(() => {
    fetchEvents()
})
</script>
