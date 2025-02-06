<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
            <h1 class="text-2xl font-bold mb-6">User Management</h1>
            
            <!-- Search and Filter Section -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-4">
                    <input 
                        v-model="filters.search"
                        name="user_search"
                        autocomplete="off"
                        placeholder="Search by name, email, or ID..."
                        class="w-auto min-w-[25rem] px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <select 
                        v-model="filters.type"
                        class="w-auto px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Types</option>
                        <option value="a">Admin</option>
                        <option value="m">Moderator</option>
                        <option value="c">Curator</option>
                        <option value="g">Guest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="users.length === 0" class="flex-1 flex items-center justify-center text-gray-500">
            No users found
        </div>

        <!-- Users Table -->
        <div v-else class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="sticky top-0 bg-white">
                    <tr class="bg-neutral-100">
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Verified</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Organizations</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Delete</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xl">
                    <tr v-for="user in users" :key="user.id">
                        <td class="px-6 py-4 whitespace-nowrap">{{ user.id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <picture class="block h-10 w-10">
                                <source 
                                    v-if="user.images && user.images.length > 0"
                                    :srcset="`${imageUrl}${user.images[0].large_image_path}`"
                                    type="image/webp"
                                >
                                <source 
                                    v-if="user.thumbImagePath"
                                    :srcset="`${imageUrl}${user.thumbImagePath}`"
                                    type="image/webp"
                                >
                                <img 
                                    :src="getImageUrl(user)"
                                    :alt="user.name"
                                    class="h-10 w-10 rounded object-cover"
                                    @error="handleImageError"
                                >
                            </picture>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                v-model="user.name"
                                name="display_name"
                                autocomplete="off"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(user, 'name', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                v-model="user.email"
                                name="user_email"
                                autocomplete="off"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(user, 'email', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button 
                                @click="checkAndToggleVerification(user)"
                                :class="[
                                    'px-3 py-1 rounded-full text-sm font-medium transition-colors',
                                    user.email_verified_at 
                                        ? 'bg-green-100 text-green-800 hover:bg-green-200' 
                                        : 'bg-red-100 text-red-800 hover:bg-red-200'
                                ]"
                            >
                                {{ user.email_verified_at ? 'Yes' : 'No' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <select 
                                v-model="user.type"
                                name="user_type"
                                autocomplete="off"
                                @focus="storeOriginalValue($event)"
                                @change="checkAndUpdateField(user, 'type', $event)"
                                class="border border-gray-300 rounded px-2 py-1 text-xl"
                            >
                                <option value="g">Guest</option>
                                <option value="c">Curator</option>
                                <option value="m">Moderator</option>
                                <option value="a">Admin</option>
                            </select>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="relative inline-block">
                                <button 
                                    @click="toggleOrgList(user)"
                                    class="px-3 py-1 border rounded-md hover:bg-n focus:outline-none"
                                >
                                    {{ user.teams?.length || 0 }} Organizations
                                </button>
                                
                                <div 
                                    v-if="activeOrgList === user.id"
                                    class="absolute left-0 mt-1 w-64 bg-white border rounded-md shadow-lg z-50"
                                >
                                    <div class="p-2 max-h-48 overflow-y-auto">
                                        <div v-if="user.teams?.length">
                                            <template v-for="team in user.teams" :key="`team-${team.id}`">
                                                <div class="py-1 text-sm">
                                                    {{ team.name }} <span class="text-gray-500">({{ team.membership.role }})</span>
                                                </div>
                                            </template>
                                        </div>
                                        <div v-else class="text-sm text-gray-500 py-1">
                                            No organizations
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button 
                                @click="confirmDelete(user)"
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
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                <div class="mt-6 flex justify-end space-x-4">
                    <button 
                        @click="showDeleteModal = false"
                        class="px-4 py-2 border rounded hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="deleteUser"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template> 

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Pagination from '@/GlobalComponents/pagination.vue'

const users = ref([])
const loading = ref(true)
const pagination = ref(null)
const showDeleteModal = ref(false)
const userToDelete = ref(null)
const activeOrgList = ref(null)

const filters = ref({
    search: '',
    type: ''
})

const imageUrl = import.meta.env.VITE_IMAGE_URL

// Debounce function
const debounce = (callback, wait) => {
    let timeout
    return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => callback(...args), wait)
    }
}

// Separate watchers for search and type
watch(() => filters.value.search, debounce(() => fetchUsers(1), 300))

watch(() => filters.value.type, () => {
    fetchUsers(1)
})

// Close org list when clicking outside
const closeOrgList = (e) => {
    if (!e.target.closest('.relative')) {
        activeOrgList.value = null
    }
}

onMounted(() => {
    document.addEventListener('click', closeOrgList)
    fetchUsers()
})

onUnmounted(() => {
    document.removeEventListener('click', closeOrgList)
})

const toggleOrgList = (user) => {
    activeOrgList.value = activeOrgList.value === user.id ? null : user.id
}

const handleImageError = (event) => {
    // Don't retry loading the placeholder if it fails
    if (!event.target.src.includes('placehold.co')) {
        event.target.src = 'https://placehold.co/40x40?text=No+Image'
    }
}

const storeOriginalValue = (event) => {
    event.target.setAttribute('data-original', event.target.value)
}

const checkAndUpdateField = async (user, field, event) => {
    const originalValue = event.target.getAttribute('data-original')
    const newValue = event.target.value
    
    if (originalValue !== newValue) {
        try {
            if (confirm(`Are you sure you want to update this user's ${field}?`)) {
                const response = await axios.patch(`/api/admin/manage/users/${user.id}`, {
                    [field]: newValue
                })
                
                Object.assign(user, response.data)
                event.target.setAttribute('data-original', newValue)
            } else {
                user[field] = originalValue
                event.target.value = originalValue
            }
        } catch (error) {
            user[field] = originalValue
            event.target.value = originalValue
            alert(error.response?.data?.message || `Error updating ${field}`)
        }
    }
}

const checkAndToggleVerification = async (user) => {
    try {
        if (confirm('Are you sure you want to change the verification status?')) {
            const response = await axios.patch(`/api/admin/manage/users/${user.id}`, {
                verified: !user.email_verified_at
            })
            Object.assign(user, response.data)
        }
    } catch (error) {
        console.error('Error toggling verification:', error)
        alert(error.response?.data?.message || 'Error updating verification status')
    }
}

const fetchUsers = async (page = 1) => {
    try {
        loading.value = true
        const response = await axios.get('/api/admin/manage/users', {
            params: { 
                page,
                ...filters.value
            }
        })
        users.value = response.data.data
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total,
            per_page: response.data.per_page
        }
    } catch (error) {
        console.error('Error fetching users:', error)
    } finally {
        loading.value = false
    }
}

const confirmDelete = (user) => {
    userToDelete.value = user
    showDeleteModal.value = true
}

const deleteUser = async () => {
    if (!userToDelete.value) return

    try {
        await axios.delete(`/api/admin/manage/users/${userToDelete.value.id}`)
        await fetchUsers(pagination.value?.current_page)
        showDeleteModal.value = false
        userToDelete.value = null
    } catch (error) {
        console.error('Error deleting user:', error)
        alert(error.response?.data?.message || 'Error deleting user')
    }
}

const handlePageChange = (page) => {
    fetchUsers(page)
}

const getImageUrl = (user) => {
    if (user.images && user.images.length > 0) {
        return `${imageUrl}${user.images[0].large_image_path}`
    }
    if (user.thumbImagePath) {
        return `${imageUrl}${user.thumbImagePath}`
    }
    return 'https://placehold.co/40x40?text=No+Image'
}
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