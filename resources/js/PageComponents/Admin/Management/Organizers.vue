<template>
    <div class="flex flex-col h-full w-full">
        <!-- Fixed Header Section -->
        <div class="flex-none overflow-hidden">
            <h1 class="text-2xl font-bold mb-6">Organizer Management</h1>
            
            <!-- Search Section -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-4">
                    <input 
                        v-model="filters.search"
                        name="organizer_search"
                        autocomplete="off"
                        placeholder="Search by name, email, or ID..."
                        class="w-auto min-w-[25rem] px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <select 
                        v-model="filters.sort"
                        class="w-auto px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="organizers.length === 0" class="text-center text-gray-500">
            No organizers found
        </div>

        <!-- Organizers Table -->
        <div v-else class="w-full overflow-auto border border-neutral-200">
            <table class="w-full overflow-hidden">
                <thead class="sticky top-0 bg-white">
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Members</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delete</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xl">
                    <tr v-for="organizer in organizers" :key="organizer.id">
                        <td class="px-6 py-4 whitespace-nowrap">{{ organizer.id }}</td>
                        <td class="px-6 py-4 max-w-[25rem] whitespace-normal break-words">
                            {{ organizer.name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ organizer.email }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="relative inline-block" v-click-outside="handleOwnerClickOutside">
                                <button 
                                    @click.stop="toggleOwnerSearch(organizer)"
                                    class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                                >
                                    {{ organizer.owner?.name }}
                                </button>
                                
                                <div 
                                    v-if="activeOwnerSearch === organizer.id"
                                    class="absolute left-0 mt-1 w-64 bg-white border rounded-md shadow-lg z-50"
                                    @click.stop
                                >
                                    <!-- Search Owner -->
                                    <div class="p-2">
                                        <input
                                            v-model="ownerSearch"
                                            placeholder="Search users..."
                                            class="w-full px-2 py-1 border rounded text-sm"
                                        >
                                        <!-- Search Results -->
                                        <div v-if="ownerSearchResults.length > 0" 
                                             class="absolute left-0 right-0 mt-1 bg-white border rounded-md shadow-lg z-50">
                                            <div v-for="user in ownerSearchResults" 
                                                 :key="user.id" 
                                                 @click="updateOwner(organizer, user)"
                                                 class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm">
                                                {{ user.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="relative inline-block" v-click-outside="handleMembersClickOutside">
                                <button 
                                    @click.stop="toggleMembersList(organizer)"
                                    class="px-3 py-1 border rounded-md hover:bg-gray-50 focus:outline-none"
                                >
                                    {{ organizer.users?.length || 0 }} Members
                                </button>
                                
                                <div 
                                    v-if="activeMembersList === organizer.id"
                                    class="absolute w-64 bg-white border rounded-md shadow-lg z-50"
                                    style="left: 0;"
                                    @click.stop
                                >
                                    <!-- Search New Members -->
                                    <div class="p-2 border-b">
                                        <input
                                            v-model="newMemberSearch"
                                            placeholder="Search users..."
                                            class="w-full px-2 py-1 border rounded text-sm"
                                            @focus="showMemberSearchResults = true"
                                        >
                                        <!-- Search Results -->
                                        <div v-if="showMemberSearchResults && memberSearchResults.length > 0" 
                                             class="absolute left-0 right-0 mt-1 bg-white border rounded-md shadow-lg z-50">
                                            <div v-for="user in memberSearchResults" 
                                                 :key="user.id" 
                                                 @click="addMember(organizer, user)"
                                                 class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm">
                                                {{ user.name }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Current Members -->
                                    <div class="max-h-48 overflow-y-auto">
                                        <div v-for="user in organizer.users" 
                                             :key="user.id" 
                                             class="py-1 px-2 flex justify-between items-center">
                                            <span class="text-sm">{{ user.name }}</span>
                                            <button 
                                                @click="removeMember(organizer, user)"
                                                class="text-red-600 hover:text-red-800 text-sm"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button 
                                @click="confirmDelete(organizer)"
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
        <div class="flex-none mt-6">
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
                <p>Are you sure you want to delete this organizer? This action cannot be undone.</p>
                <div class="mt-6 flex justify-end space-x-4">
                    <button 
                        @click="showDeleteModal = false"
                        class="px-4 py-2 border rounded hover:bg-gray-100"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="deleteOrganizer"
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
import { ref, onMounted, watch } from 'vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective'

const vClickOutside = ClickOutsideDirective

const organizers = ref([])
const loading = ref(true)
const pagination = ref(null)
const showDeleteModal = ref(false)
const organizerToDelete = ref(null)
const activeMembersList = ref(null)
const newMemberSearch = ref('')
const memberSearchResults = ref([])
const showMemberSearchResults = ref(false)
const activeOwnerSearch = ref(null)
const ownerSearch = ref('')
const ownerSearchResults = ref([])

const filters = ref({
    search: '',
    sort: 'newest'
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
    [
        () => filters.value.search,
        () => filters.value.sort
    ],
    debounce(() => fetchOrganizers(1), 300)
)

// Watch for new member search
watch(newMemberSearch, debounce(async () => {
    if (!newMemberSearch.value) {
        memberSearchResults.value = []
        return
    }
    try {
        const response = await axios.get('/api/admin/manage/users', {
            params: { search: newMemberSearch.value }
        })
        memberSearchResults.value = response.data.data
    } catch (error) {
        console.error('Error searching users:', error)
    }
}, 300))

onMounted(() => {
    fetchOrganizers()
})

const toggleMembersList = (organizer) => {
    activeMembersList.value = activeMembersList.value === organizer.id ? null : organizer.id
    newMemberSearch.value = ''
    memberSearchResults.value = []
}

const addMember = async (organizer, user) => {
    try {
        const response = await axios.patch(`/api/admin/manage/organizers/${organizer.slug}`, {
            action: 'add_member',
            user_id: user.id
        })
        Object.assign(organizer, response.data)
        newMemberSearch.value = ''
        memberSearchResults.value = []
        showMemberSearchResults.value = false
    } catch (error) {
        alert(error.response?.data?.message || 'Error adding member')
    }
}

const removeMember = async (organizer, user) => {
    try {
        const response = await axios.patch(`/api/admin/manage/organizers/${organizer.slug}`, {
            action: 'remove_member',
            user_id: user.id
        })
        Object.assign(organizer, response.data)
    } catch (error) {
        alert(error.response?.data?.message || 'Error removing member')
    }
}

const fetchOrganizers = async (page = 1) => {
    try {
        loading.value = true
        const response = await axios.get('/api/admin/manage/organizers', {
            params: { 
                page,
                ...filters.value
            }
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

const confirmDelete = (organizer) => {
    organizerToDelete.value = organizer
    showDeleteModal.value = true
}

const deleteOrganizer = async () => {
    if (!organizerToDelete.value) return

    try {
        await axios.delete(`/api/admin/manage/organizers/${organizerToDelete.value.slug}`)
        await fetchOrganizers(pagination.value?.current_page)
        showDeleteModal.value = false
        organizerToDelete.value = null
    } catch (error) {
        console.error('Error deleting organizer:', error)
        alert(error.response?.data?.message || 'Error deleting organizer')
    }
}

const handlePageChange = (page) => {
    fetchOrganizers(page)
}

const toggleOwnerSearch = (organizer) => {
    activeOwnerSearch.value = activeOwnerSearch.value === organizer.id ? null : organizer.id
    ownerSearch.value = ''
    ownerSearchResults.value = []
}

const updateOwner = async (organizer, user) => {
    try {
        if (confirm(`Are you sure you want to change the owner to ${user.name}?`)) {
            const response = await axios.patch(`/api/admin/manage/organizers/${organizer.slug}`, {
                action: 'update_owner',
                user_id: user.id
            })
            Object.assign(organizer, response.data)
            ownerSearch.value = ''
            ownerSearchResults.value = []
            activeOwnerSearch.value = null
        }
    } catch (error) {
        console.error('Error updating owner:', error)
        alert(error.response?.data?.message || 'Error updating owner')
    }
}

// Add this watch for owner search
watch(ownerSearch, debounce(async () => {
    if (!ownerSearch.value) {
        ownerSearchResults.value = []
        return
    }
    try {
        const response = await axios.get('/api/admin/manage/users', {
            params: { search: ownerSearch.value }
        })
        ownerSearchResults.value = response.data.data
    } catch (error) {
        console.error('Error searching users:', error)
    }
}, 300))

// Add these handler functions
const handleOwnerClickOutside = () => {
    activeOwnerSearch.value = null
    ownerSearchResults.value = []
}

const handleMembersClickOutside = () => {
    activeMembersList.value = null
    showMemberSearchResults.value = false
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