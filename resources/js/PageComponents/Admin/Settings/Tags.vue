<template>
    <div class="p-6 bg-white">
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

        <!-- Loading State -->
        <div v-if="loading" class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="tags.length === 0" class="text-center text-gray-500">
            No tags found
        </div>

        <!-- Tags Table -->
        <div v-else>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
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
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                                <option :value="0">Guest</option>
                                <option :value="1">Admin</option>
                            </select>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xl">
                            {{ new Date(tag.created_at).toLocaleDateString() }}
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

            <div class="mt-6">
                <Pagination 
                    v-if="pagination"
                    :pagination="pagination"
                    @paginate="handlePageChange"
                />
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-xl font-bold mb-4">Add New Tag</h3>
                <form @submit.prevent="createTag" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input 
                            v-model="newTag.name"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button 
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                        >
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

const createTag = async () => {
    try {
        await axios.post('/api/admin/settings/genres', newTag.value)
        await fetchTags(1)
        showCreateModal.value = false
        newTag.value = {
            name: '',
            rank: 0,
            admin: 1
        }
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
