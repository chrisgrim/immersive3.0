<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Advisories</h1>
                <button 
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-xl"
                >
                    Add New Advisory
                </button>
            </div>

            <!-- Type Selector and Search -->
            <div class="mb-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <select 
                        v-model="selectedType"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl"
                    >
                        <option value="content">Content Advisories</option>
                        <option value="mobility">Mobility Advisories</option>
                        <option value="interactive">Interactive Levels</option>
                        <option value="contact">Contact Levels</option>
                    </select>
                    <input 
                        v-model="filters.search"
                        placeholder="Search by name..."
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl"
                    >
                    <select 
                        v-if="showAdminColumn"
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
        <div v-else-if="advisories.length === 0" class="flex-1 flex items-center justify-center text-gray-500">
            No advisories found
        </div>

        <!-- Advisories Table -->
        <div v-else class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="sticky top-0 bg-white shadow-sm">
                    <tr class="bg-neutral-100">
                        <th class="w-16 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center cursor-pointer" @click="toggleSort('name')">
                                Name
                                <span class="ml-2">{{ sortField === 'name' ? (sortDirection === 'asc' ? '↑' : '↓') : '' }}</span>
                            </div>
                        </th>
                        <th v-if="showDescription" class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="w-32 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center cursor-pointer" @click="toggleSort('rank')">
                                Rank
                                <span class="ml-2">{{ sortField === 'rank' ? (sortDirection === 'asc' ? '↑' : '↓') : '' }}</span>
                            </div>
                        </th>
                        <th v-if="showAdminColumn" class="w-32 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="w-24 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="advisory in advisories" :key="advisory.id">
                        <td class="px-6 py-4 whitespace-nowrap text-xl">{{ advisory.id }}</td>
                        <td class="px-6 py-4">
                            <input 
                                v-model="advisory.name"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(advisory, 'name', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl w-full"
                            >
                        </td>
                        <td v-if="showDescription" class="px-6 py-4">
                            <input 
                                v-model="advisory.description"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(advisory, 'description', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl w-full"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <select 
                                :value="advisory.rank"
                                @change="checkAndUpdateField(advisory, 'rank', $event)"
                                class="px-2 py-1 w-20 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                                <option v-for="n in 6" :key="n-1" :value="n-1">{{ n-1 }}</option>
                            </select>
                        </td>
                        <td v-if="showAdminColumn" class="px-6 py-4">
                            <select 
                                :value="advisory.admin"
                                @change="checkAndUpdateField(advisory, 'admin', $event)"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                                <option :value="0">Guest</option>
                                <option :value="1">Admin</option>
                            </select>
                        </td>
                        <td class="px-6 py-4">
                            <button 
                                @click="deleteAdvisory(advisory)"
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
                        <h2 class="text-2xl font-bold mb-2">Add New Advisory</h2>
                        <p class="text-gray-500 font-normal">Create a new advisory for your events</p>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="p-8 overflow-y-auto flex-1">
                        <div class="space-y-6">
                            <!-- Advisory Type Selector -->
                            <div class="relative">
                                <p class="text-gray-500 font-normal mb-4">Advisory Type</p>
                                <select 
                                    v-model="selectedType"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                >
                                    <option value="content">Content Advisory</option>
                                    <option value="mobility">Mobility Advisory</option>
                                    <option value="interactive">Interactive Level</option>
                                    <option value="contact">Contact Level</option>
                                </select>
                            </div>

                            <!-- Name field -->
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Name</p>
                                <input 
                                    v-model="newAdvisory.name"
                                    type="text"
                                    required
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter advisory name"
                                >
                            </div>

                            <!-- Description field (only for interactive) -->
                            <div v-if="selectedType === 'interactive'">
                                <p class="text-gray-500 font-normal mb-4">Description</p>
                                <textarea 
                                    v-model="newAdvisory.description"
                                    required
                                    rows="3"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter description"
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Rank field -->
                                <div>
                                    <p class="text-gray-500 font-normal mb-4">Rank</p>
                                    <select 
                                        v-model.number="newAdvisory.rank"
                                        class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                    >
                                        <option v-for="n in 6" :key="n-1" :value="n-1">{{ n-1 }}</option>
                                    </select>
                                </div>

                                <!-- Admin field (only for content and mobility) -->
                                <div v-if="showAdminColumn">
                                    <p class="text-gray-500 font-normal mb-4">Type</p>
                                    <select 
                                        v-model="newAdvisory.admin"
                                        class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                    >
                                        <option :value="1">Admin</option>
                                        <option :value="0">Guest</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                        <div class="flex justify-end space-x-4">
                            <button 
                                @click="showCreateModal = false"
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neut text-xl"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="createAdvisory"
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
import { ref, watch, onMounted, computed } from 'vue'
import Pagination from '@/GlobalComponents/pagination.vue'

const advisories = ref([])
const loading = ref(true)
const pagination = ref(null)
const showCreateModal = ref(false)
const selectedType = ref('content')
const filters = ref({
    search: '',
    type: ''
})
const sortField = ref('name')
const sortDirection = ref('asc')
const newAdvisory = ref({
    name: '',
    description: '',
    rank: 0,
    admin: 1
})

// Computed properties for conditional rendering
const showDescription = computed(() => ['interactive'].includes(selectedType.value))
const showAdminColumn = computed(() => ['content', 'mobility'].includes(selectedType.value))

// Update API endpoint to match your routes
const apiEndpoint = '/api/admin/settings/advisories'

const fetchAdvisories = async (page = 1) => {
    try {
        loading.value = true
        
        // Create params object with required fields
        const params = { 
            page,
            search: filters.value.search,
            sort_field: sortField.value,
            sort_direction: sortDirection.value
        }

        // Only add type parameter if it has a value
        if (filters.value.type !== '') {
            params.type = filters.value.type
        }

        console.log('Fetching advisories:', params);

        const response = await axios.get(`${apiEndpoint}/${selectedType.value}`, { params });

        console.log('Response:', response.data);

        advisories.value = response.data.data || [];
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total,
            per_page: response.data.per_page
        };

        console.log('Processed advisories:', advisories.value);
        console.log('Pagination:', pagination.value);

    } catch (error) {
        console.error('Error fetching advisories:', error);
        console.error('Error details:', error.response?.data);
        advisories.value = [];
    } finally {
        loading.value = false;
    }
}

const createAdvisory = async () => {
    try {
        const data = {
            type: selectedType.value,
            name: newAdvisory.value.name,
            rank: newAdvisory.value.rank
        }

        // Add description only for interactive type
        if (selectedType.value === 'interactive') {
            data.description = newAdvisory.value.description
        }

        // Add admin only for content and mobility types
        if (showAdminColumn.value) {
            data.admin = newAdvisory.value.admin
        }

        await axios.post(apiEndpoint, data)
        await fetchAdvisories()
        showCreateModal.value = false
        
        // Reset form
        newAdvisory.value = {
            name: '',
            description: '',
            rank: 0,
            admin: showAdminColumn.value ? 1 : undefined
        }
    } catch (error) {
        console.error('Error creating advisory:', error)
        alert(error.response?.data?.message || 'Error creating advisory')
    }
}

const storeOriginalValue = (event) => {
    event.target.setAttribute('data-original', event.target.value)
}

const checkAndUpdateField = async (advisory, field, event) => {
    const originalValue = event.target.getAttribute('data-original')
    const newValue = event.target.value
    
    if (originalValue !== newValue) {
        try {
            if (confirm(`Are you sure you want to update this advisory's ${field}?`)) {
                await axios.patch(`${apiEndpoint}/${selectedType.value}/${advisory.id}`, {
                    [field]: newValue
                })
                event.target.setAttribute('data-original', newValue)
            } else {
                advisory[field] = originalValue
                event.target.value = originalValue
            }
        } catch (error) {
            advisory[field] = originalValue
            event.target.value = originalValue
            console.error('Error updating advisory:', error)
            alert(error.response?.data?.message || `Error updating ${field}`)
        }
    }
}

const deleteAdvisory = async (advisory) => {
    if (confirm('Are you sure you want to delete this advisory?')) {
        try {
            await axios.delete(`${apiEndpoint}/${selectedType.value}/${advisory.id}`)
            await fetchAdvisories()
        } catch (error) {
            console.error('Error deleting advisory:', error)
        }
    }
}

// Debounce function from Users.vue
const debounce = (callback, wait) => {
    let timeout
    return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => callback(...args), wait)
    }
}

// Watch for changes in filters and selectedType
watch(() => filters.value.search, debounce(() => fetchAdvisories(1), 300))
watch(() => filters.value.type, () => fetchAdvisories(1))
watch(() => selectedType.value, () => {
    // Reset the form
    newAdvisory.value = {
        name: '',
        description: '',
        rank: 0,
        admin: showAdminColumn.value ? 1 : undefined
    }
    // Fetch new advisories for selected type
    fetchAdvisories(1)
})

onMounted(() => {
    fetchAdvisories()
})

const handlePageChange = (page) => {
    fetchAdvisories(page)
}

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'asc'
    }
    fetchAdvisories()
}
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
