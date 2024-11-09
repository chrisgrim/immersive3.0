<template>
    <div class="p-6 bg-white">
        <h1 class="text-2xl font-bold mb-6">Organizers Ready for Review</h1>
        
        <div v-if="loading" class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
        </div>
        
        <div v-else-if="organizers.length === 0" class="text-center text-gray-500">
            No organizers ready for review
        </div>
        
        <div v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <a 
                    v-for="organizer in organizers" 
                    :key="organizer.id" 
                    href="#"
                    class="bg-white hover:opacity-75 transition-opacity duration-200"
                >
                    <div class="aspect-[3/2] w-full">
                        <img 
                            :src="getImageUrl(organizer)"
                            :alt="organizer.name" 
                            class="w-full h-full object-cover rounded-2xl"
                            @error="handleImageError"
                        >
                    </div>
                    <div class="p-4">
                        <h3 class="text-xl font-semibold mb-2">{{ organizer.name }}</h3>
                        <div class="text-xl text-gray-600 space-y-1">
                            <div>Email: {{ organizer.email }}</div>
                            <div>Phone: {{ organizer.phone || 'N/A' }}</div>
                        </div>
                    </div>
                </a>
            </div>

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

const imageUrl = import.meta.env.VITE_IMAGE_URL
const organizers = ref([])
const loading = ref(true)
const pagination = ref(null)

const handlePageChange = (page) => {
    fetchOrganizers(page)
}

const fetchOrganizers = async (page = 1) => {
    try {
        const response = await axios.get('/api/admin/approve/organizers', {
            params: { page }
        })
        organizers.value = response.data.data
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total,
            per_page: response.data.per_page
        }
    } catch (error) {
        console.error('Error fetching organizers:', error)
    } finally {
        loading.value = false
    }
}

const getImageUrl = (organizer) => {
    if (organizer.images && organizer.images.length > 0) {
        return `${imageUrl}${organizer.images[0].large_image_path}`
    }
    return 'https://placehold.co/600x400?text=No+Image'
}

const handleImageError = (e) => {
    e.target.src = 'https://placehold.co/600x400?text=No+Image'
}

onMounted(() => {
    fetchOrganizers()
})
</script> 