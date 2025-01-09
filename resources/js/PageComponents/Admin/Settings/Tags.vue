<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Tags</h1>
                <button 
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-xl"
                >
                    Add New Tag
                </button>
            </div>

            <!-- Search and Filter Section -->
            <div class="mb-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input 
                        v-model="filters.search"
                        placeholder="Search by name..."
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl"
                    >
                    <select 
                        v-model="filters.type"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl"
                    >
                        <option value="">All Types</option>
                        <option value="1">Admin</option>
                        <option value="0">Guest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="tags.length === 0" class="flex-1 flex items-center justify-center text-gray-500">
            No tags found
        </div>

        <!-- Tags Table -->
        <div v-else class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="sticky top-0 bg-white shadow-sm">
                    <tr class="bg-neutral-100">
                        <th class="w-16 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center cursor-pointer" @click="toggleSort('name')">
                                Name
                                <span class="ml-2">
                                    {{ sortField === 'name' ? (sortDirection === 'asc' ? '↑' : '↓') : '' }}
                                </span>
                            </div>
                        </th>
                        <th class="w-32 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center cursor-pointer" @click="toggleSort('rank')">
                                Rank
                                <span class="ml-2">
                                    {{ sortField === 'rank' ? (sortDirection === 'asc' ? '↑' : '↓') : '' }}
                                </span>
                            </div>
                        </th>
                        <th class="w-32 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center cursor-pointer" @click="toggleSort('admin')">
                                Type
                                <span class="ml-2">
                                    {{ sortField === 'admin' ? (sortDirection === 'asc' ? '↑' : '↓') : '' }}
                                </span>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center cursor-pointer" @click="toggleSort('created_at')">
                                Created
                                <span class="ml-2">
                                    {{ sortField === 'created_at' ? (sortDirection === 'asc' ? '↑' : '↓') : '' }}
                                </span>
                            </div>
                        </th>
                        <th class="w-48 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                        <th class="w-24 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="tag in tags" :key="tag.id">
                        <td class="px-6 py-4 whitespace-nowrap text-xl">{{ tag.id }}</td>
                        <td class="px-6 py-4">
                            <input 
                                v-model="tag.name"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(tag, 'name', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl w-full"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <input 
                                v-model.number="tag.rank"
                                type="number"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(tag, 'rank', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 w-20 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <select 
                                :value="tag.admin"
                                @change="checkAndUpdateField(tag, 'admin', $event)"
                                class="px-2 py-1 w-full md:w-auto border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                                <option :value="0">Guest</option>
                                <option :value="1">Admin</option>
                            </select>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xl">
                            {{ new Date(tag.created_at).toLocaleDateString() }}
                        </td>
                        <td class="px-6 py-4">
                            <input 
                                type="file"
                                :ref="el => fileInputs[tag.id] = el"
                                class="hidden"
                                accept="image/*"
                                @change="(e) => updateTagImage(tag, e)"
                            >
                            <div 
                                @click="triggerFileInput(tag.id)"
                                class="cursor-pointer hover:opacity-75 transition-opacity"
                            >
                                <img 
                                    v-if="tag.images?.[0]?.thumb_image_path"
                                    :src="`${imageUrl}${tag.images[0].thumb_image_path}`"
                                    :alt="tag.name"
                                    class="h-24 w-24 rounded object-cover"
                                >
                                <div 
                                    v-else
                                    class="h-24 w-24 rounded bg-gray-200 flex items-center justify-center"
                                >
                                    <i class="fas fa-plus text-gray-400"></i>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <button 
                                @click="deleteTag(tag)"
                                class="text-red-600 hover:text-red-900 text-xl"
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

        <!-- Create Modal -->
        <teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
                <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                    <!-- Header -->
                    <div class="p-8 pb-6">
                        <h2 class="text-2xl font-bold mb-2">Add New Tag</h2>
                        <p class="text-gray-500 font-normal">Create a new tag for categorizing events</p>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="p-8 overflow-y-auto flex-1">
                        <div class="space-y-6">
                            <!-- Name field -->
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Name</p>
                                <input 
                                    v-model="newTag.name"
                                    type="text"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter tag name"
                                >
                            </div>

                            <!-- Rank and Type fields in grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-gray-500 font-normal mb-4">Rank</p>
                                    <select 
                                        v-model.number="newTag.rank"
                                        class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                    >
                                        <option v-for="n in 6" :key="n-1" :value="n-1">{{ n-1 }}</option>
                                    </select>
                                </div>

                                <div>
                                    <p class="text-gray-500 font-normal mb-4">Type</p>
                                    <select 
                                        v-model="newTag.admin"
                                        class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                    >
                                        <option :value="1">Admin</option>
                                        <option :value="0">Guest</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Icon Upload -->
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Icon</p>
                                <input 
                                    type="file"
                                    ref="createTagFileInput"
                                    class="hidden"
                                    accept="image/*"
                                    @change="handleCreateTagImage"
                                >
                                <div 
                                    @click="$refs.createTagFileInput.click()"
                                    class="cursor-pointer hover:opacity-75 transition-opacity"
                                >
                                    <img 
                                        v-if="newTagPreviewImage"
                                        :src="newTagPreviewImage"
                                        class="h-32 w-32 rounded-2xl object-cover"
                                    >
                                    <div 
                                        v-else
                                        class="h-32 w-32 rounded-2xl border border-neutral-400 flex flex-col items-center justify-center gap-2"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-gray-400 text-lg text-center">Add Icon</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                        <div class="flex justify-end space-x-4">
                            <button 
                                @click="showCreateModal = false"
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neu text-xl"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="createTag"
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                            >
                                Create
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import Pagination from '@/GlobalComponents/pagination.vue'

const tags = ref([])
const loading = ref(true)
const pagination = ref(null)
const showCreateModal = ref(false)
const filters = ref({
    search: '',
    type: ''
})
const sortField = ref('name')
const sortDirection = ref('asc')
const newTag = ref({
    name: '',
    rank: 0,
    admin: 1
})
const newTagImage = ref(null)
const newTagPreviewImage = ref(null)
const createTagFileInput = ref(null)
const fileInputs = ref({})
const imageUrl = import.meta.env.VITE_IMAGE_URL

// Debounce function
const debounce = (callback, wait) => {
    let timeout
    return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => callback(...args), wait)
    }
}

// Watch for filter changes
watch(() => filters.value.search, debounce(() => fetchTags(1), 300))
watch(() => filters.value.type, () => fetchTags(1))

const fetchTags = async (page = 1) => {
    try {
        loading.value = true
        const response = await axios.get('/api/admin/settings/genres', {
            params: { 
                page,
                search: filters.value.search,
                type: filters.value.type,
                sort_field: sortField.value,
                sort_direction: sortDirection.value
            }
        })
        console.log('API Response:', response.data)
        tags.value = response.data.data
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total,
            per_page: response.data.per_page
        }
        console.log('Tags:', tags.value)
        console.log('Pagination:', pagination.value)
    } catch (error) {
        console.error('Error fetching genres:', error)
    } finally {
        loading.value = false
    }
}

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'asc'
    }
    fetchTags()
}

const storeOriginalValue = (event) => {
    event.target.setAttribute('data-original', event.target.value)
}

const checkAndUpdateField = async (tag, field, event) => {
    const originalValue = event.target.getAttribute('data-original')
    const newValue = field === 'admin' ? Number(event.target.value) : event.target.value
    
    if (String(originalValue) !== String(newValue)) {
        try {
            if (confirm(`Are you sure you want to update this tag's ${field}?`)) {
                await axios.patch(`/api/admin/settings/genres/${tag.id}`, {
                    [field]: newValue
                })
                await fetchTags(pagination.value?.current_page)
            } else {
                tag[field] = originalValue
                event.target.value = originalValue
            }
        } catch (error) {
            console.error('Update error:', error)
            tag[field] = originalValue
            event.target.value = originalValue
            alert(error.response?.data?.message || `Error updating ${field}`)
        }
    }
}

const handleCreateTagImage = (event) => {
    const file = event.target.files[0]
    if (file) {
        newTagImage.value = file
        newTagPreviewImage.value = URL.createObjectURL(file)
    }
}

const createTag = async () => {
    try {
        const formData = new FormData()
        formData.append('name', newTag.value.name)
        formData.append('rank', newTag.value.rank)
        formData.append('admin', newTag.value.admin)
        
        if (newTagImage.value) {
            formData.append('image', newTagImage.value)
        }

        await axios.post('/api/admin/settings/genres', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        
        await fetchTags(1)
        showCreateModal.value = false
        newTag.value = {
            name: '',
            rank: 0,
            admin: 1
        }
        newTagImage.value = null
        newTagPreviewImage.value = null
    } catch (error) {
        console.error('Error creating tag:', error)
        alert(error.response?.data?.message || 'Error creating tag')
    }
}

const deleteTag = async (tag) => {
    if (!confirm('Are you sure you want to delete this tag?')) return
    
    try {
        await axios.delete(`/api/admin/settings/genres/${tag.id}`)
        await fetchTags(pagination.value?.current_page)
    } catch (error) {
        console.error('Error deleting tag:', error)
        alert(error.response?.data?.message || 'Error deleting tag')
    }
}

const handlePageChange = (page) => {
    fetchTags(page)
}

const triggerFileInput = (tagId) => {
    if (fileInputs.value[tagId]) {
        fileInputs.value[tagId].click()
    }
}

const updateTagImage = async (tag, event) => {
    const file = event.target.files[0]
    if (!file) return

    try {
        const formData = new FormData()
        formData.append('image', file)
        formData.append('name', tag.name)
        formData.append('rank', tag.rank)
        formData.append('admin', tag.admin)

        const response = await axios.post(
            `/api/admin/settings/genres/${tag.id}`, 
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        )
        Object.assign(tag, response.data)
    } catch (error) {
        console.error('Error updating image:', error)
        alert(error.response?.data?.message || 'Error updating image')
    }
}

onMounted(() => {
    fetchTags()
})
</script>

<style scoped>
input, select {
    background: transparent;
}

input:hover, select:hover {
    background: #f8f8f8;
}

input:focus, select:focus {
    background: white;
}
</style>
