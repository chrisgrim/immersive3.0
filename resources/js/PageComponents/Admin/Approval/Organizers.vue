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
                <div 
                    v-for="organizer in organizers" 
                    :key="organizer.id" 
                    @click="!loading && handleOrganizerSelect(organizer)"
                    :class="[
                        'bg-white transition-opacity duration-200 cursor-pointer flex flex-col items-center',
                        loading ? 'opacity-50 cursor-wait' : 'hover:opacity-75'
                    ]"
                >
                    <div class="aspect-square w-1/2 mb-4">
                        <picture>
                            <source 
                                :srcset="getWebpUrl(organizer)" 
                                type="image/webp"
                            >
                            <img 
                                :src="getImageUrl(organizer)"
                                :alt="organizer.name" 
                                class="w-full h-full object-cover rounded-full"
                                @error="handleImageError"
                            >
                        </picture>
                    </div>
                    <div class="text-center">
                        <h3 class="mt-2 mb-4 text-2xl text-black font-medium leading-tight line-clamp-2 break-words hyphens-auto">{{ organizer.name }}</h3>
                    </div>
                </div>
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
const loading = ref(false)
const pagination = ref(null)

const emit = defineEmits(['select-organizer', 'update-counts'])

const handleOrganizerSelect = async (organizer) => {
    if (loading.value) return
    
    try {
        loading.value = true        
        const slug = organizer.slug || organizer.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        const url = `/api/admin/organizers/${slug}`;
        
        const response = await axios.get(url);
        
        emit('select-organizer', response.data);
    } catch (error) {
        console.error('Error details:', {
            status: error.response?.status,
            statusText: error.response?.statusText,
            data: error.response?.data,
            url: error.config?.url
        });
    } finally {
        loading.value = false
    }
}

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

const getWebpUrl = (organizer) => {
    if (organizer.images && organizer.images.length > 0) {
        // Assuming the webp path is similar but with .webp extension
        const imagePath = organizer.images[0].large_image_path
        return `${imageUrl}${imagePath.replace(/\.[^/.]+$/, '.webp')}`
    }
    return 'https://placehold.co/600x400.webp?text=No+Image'
}

const getImageUrl = (organizer) => {
    if (organizer.images && organizer.images.length > 0) {
        return `${imageUrl}${organizer.images[0].large_image_path}`
    }
    return 'https://placehold.co/600x400.jpg?text=No+Image'
}

const handleImageError = (e) => {
    e.target.src = 'https://placehold.co/600x400.jpg?text=No+Image'
}

onMounted(() => {
    fetchOrganizers()
})
</script> 