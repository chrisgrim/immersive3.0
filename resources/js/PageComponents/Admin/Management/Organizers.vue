<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
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
        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="organizers.length === 0" class="flex-1 flex items-center justify-center text-gray-500">
            No organizers found
        </div>

        <!-- Organizers Table -->
        <div v-else class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="sticky top-0 bg-white shadow-sm">
                    <tr class="bg-neutral-100">
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[18rem]">Name</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Members</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xl">
                    <tr v-for="organizer in organizers" :key="organizer.id">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a
                                :href="`/organizers/${organizer.slug}`"
                                target="_blank"
                                rel="noopener"
                                class="text-blue-600 hover:underline"
                            >
                                {{ organizer.id }}
                            </a>
                        </td>
                        <td class="px-6 py-4 max-w-[25rem] whitespace-normal break-words hyphens-auto min-w-[18rem]">
                            <a
                                :href="`/organizers/${organizer.slug}`"
                                target="_blank"
                                rel="noopener"
                                class="text-blue-600 hover:underline"
                            >
                                {{ organizer.name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ organizer.email }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button 
                                @click="toggleOwnerSearch(organizer)"
                                class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            >
                                {{ organizer.owner?.name }}
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button 
                                @click="toggleMembersList(organizer)"
                                class="px-3 py-1 border rounded-md hover:bg- focus:outline-none"
                            >
                                {{ organizer.users?.length || 0 }} Members
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap relative">
                            <button
                                @click.stop="toggleActionsMenu(organizer)"
                                class="inline-flex items-center justify-center h-10 w-10 rounded-full border border-gray-300 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl leading-none"
                                aria-label="Actions"
                                aria-haspopup="menu"
                                :aria-expanded="openActionsMenuId === organizer.id">
                                ⋯
                            </button>
                            <div
                                v-if="openActionsMenuId === organizer.id"
                                v-click-outside="closeActionsMenu"
                                role="menu"
                                class="absolute right-6 mt-2 w-56 flex flex-col bg-white border border-gray-200 rounded-md shadow-lg z-20 overflow-hidden">
                                <button
                                    @click="openMoveEvents(organizer)"
                                    role="menuitem"
                                    class="block w-full text-left px-4 py-3 hover:bg-gray-50">
                                    Move Events…
                                </button>
                                <button
                                    @click="confirmDelete(organizer); closeActionsMenu()"
                                    role="menuitem"
                                    class="block w-full text-left px-4 py-3 text-red-600 hover:bg-gray-50 border-t border-gray-200">
                                    Delete
                                </button>
                            </div>
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

        <!-- Owner Selection Modal -->
        <div v-if="showOwnerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Change Owner</h2>
                    <p class="text-gray-500 font-normal">Select a new owner for {{ selectedOrganizer?.name }}</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-6">
                        <div>
                            <p class="text-gray-500 font-normal mb-4">Search Users</p>
                            <input 
                                v-model="ownerSearch"
                                placeholder="Search by name or email..."
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                            >
                        </div>

                        <!-- Search Results -->
                        <div v-if="ownerSearchResults.length > 0" class="space-y-2">
                            <div 
                                v-for="user in ownerSearchResults" 
                                :key="user.id"
                                @click="updateOwner(selectedOrganizer, user)"
                                class="p-4 border border-neutral-400 rounded-2xl hover:bg- cursor-pointer"
                            >
                                <div class="text-xl">{{ user.name }}</div>
                                <div class="text-gray-500">{{ user.email }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="closeOwnerModal"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg- text-xl"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Management Modal -->
        <div v-if="showMembersModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Manage Members</h2>
                    <p class="text-gray-500 font-normal">Add or remove members for {{ selectedOrganizer?.name }}</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-6">
                        <!-- Add New Members -->
                        <div>
                            <p class="text-gray-500 font-normal mb-4">Add New Member</p>
                            <input 
                                v-model="newMemberSearch"
                                placeholder="Search by name or email..."
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                            >
                        </div>

                        <!-- Search Results -->
                        <div v-if="memberSearchResults.length > 0" class="space-y-2">
                            <div 
                                v-for="user in memberSearchResults" 
                                :key="user.id"
                                @click="addMember(selectedOrganizer, user)"
                                class="p-4 border border-neutral-400 rounded-2xl hover:bg- cursor-pointer"
                            >
                                <div class="text-xl">{{ user.name }}</div>
                                <div class="text-gray-500">{{ user.email }}</div>
                            </div>
                        </div>

                        <!-- Current Members -->
                        <div v-if="selectedOrganizer?.users?.length">
                            <p class="text-gray-500 font-normal mb-4">Current Members</p>
                            <div class="space-y-2">
                                <div 
                                    v-for="user in selectedOrganizer.users" 
                                    :key="user.id"
                                    class="p-4 border border-neutral-400 rounded-2xl flex justify-between items-center"
                                >
                                    <div>
                                        <div class="text-xl">{{ user.name }}</div>
                                        <div class="text-gray-500">{{ user.email }}</div>
                                    </div>
                                    <button 
                                        @click="removeMember(selectedOrganizer, user)"
                                        class="text-red-600 hover:text-red-800 text-xl"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button
                            @click="closeMembersModal"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg- text-xl"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Move Events Modal -->
        <div v-if="showMoveEventsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-3xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-4">
                    <h2 class="text-2xl font-bold mb-1">Move Events</h2>
                    <p class="text-gray-500 font-normal">Move all events from <strong>{{ moveSource?.name }}</strong> to another organizer.</p>
                </div>

                <!-- Scrollable content -->
                <div class="p-8 pt-2 overflow-y-auto flex-1">
                    <!-- Step 1: Pick destination -->
                    <div v-if="!moveDestination">
                        <p class="text-gray-500 font-normal mb-3">Search for the destination organizer</p>
                        <input
                            v-model="moveDestinationSearch"
                            placeholder="Search by name, email, or ID…"
                            class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4">

                        <div v-if="moveDestinationResults.length" class="mt-4 space-y-2">
                            <button
                                v-for="org in moveDestinationResults"
                                :key="org.id"
                                @click="selectMoveDestination(org)"
                                :disabled="org.id === moveSource?.id"
                                class="w-full text-left p-4 border border-neutral-400 rounded-2xl hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                <div class="text-xl">{{ org.name }}</div>
                                <div class="text-gray-500 text-base">
                                    /{{ org.slug }} · {{ org.email || '—' }}
                                    <span v-if="org.id === moveSource?.id" class="text-orange-600 ml-2">(same as source)</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Confirmation -->
                    <div v-else>
                        <p class="text-gray-500 font-normal mb-4">Review and confirm</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Source -->
                            <div class="border-2 border-orange-300 bg-orange-50 rounded-2xl p-5">
                                <div class="text-orange-700 text-sm font-semibold uppercase tracking-wider mb-2">Moving from</div>
                                <div class="text-xl font-bold mb-1">{{ moveSource?.name }}</div>
                                <div class="text-base text-gray-600 mb-3">/{{ moveSource?.slug }}</div>
                                <a
                                    :href="`/organizers/${moveSource?.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-600 hover:underline text-base">
                                    View public page ↗
                                </a>
                            </div>

                            <!-- Destination -->
                            <div class="border-2 border-green-300 bg-green-50 rounded-2xl p-5">
                                <div class="text-green-700 text-sm font-semibold uppercase tracking-wider mb-2">Moving to</div>
                                <div class="text-xl font-bold mb-1">{{ moveDestination?.name }}</div>
                                <div class="text-base text-gray-600 mb-3">/{{ moveDestination?.slug }}</div>
                                <a
                                    :href="`/organizers/${moveDestination?.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-600 hover:underline text-base">
                                    View public page ↗
                                </a>
                            </div>
                        </div>

                        <!-- Slug swap option -->
                        <label class="flex items-start gap-3 p-4 border border-neutral-300 rounded-2xl cursor-pointer hover:bg-gray-50">
                            <input
                                type="checkbox"
                                v-model="moveSwapSlug"
                                class="mt-1 h-5 w-5">
                            <div>
                                <div class="text-lg font-semibold">Also move the source's URL to the destination</div>
                                <div class="text-base text-gray-600 mt-1">
                                    Destination URL: <code>/{{ moveDestination?.slug }}</code> → <code>/{{ moveSource?.slug }}</code><br>
                                    Source URL: <code>/{{ moveSource?.slug }}</code> → <code>/{{ moveSource?.slug }}-old</code>
                                </div>
                            </div>
                        </label>

                        <p class="text-gray-500 text-base mt-6">
                            The source organizer will NOT be deleted. Its members and contact info stay where they are. After the move, you can delete the source manually from the actions menu.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div v-if="moveError" class="text-red-600 mb-4">{{ moveError }}</div>
                    <div class="flex justify-end gap-4">
                        <button
                            v-if="moveDestination"
                            @click="moveDestination = null; moveError = ''"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-gray-100 text-xl">
                            Back
                        </button>
                        <button
                            @click="closeMoveEvents"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-gray-100 text-xl">
                            Cancel
                        </button>
                        <button
                            v-if="moveDestination"
                            @click="submitMoveEvents"
                            :disabled="moveSubmitting"
                            class="px-6 py-3 bg-red-600 text-white rounded-2xl hover:bg-red-700 disabled:opacity-50 text-xl">
                            {{ moveSubmitting ? 'Moving…' : 'Move events' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Toast -->
        <div v-if="successToast" class="fixed bottom-8 right-8 bg-green-600 text-white px-6 py-4 rounded-2xl shadow-xl z-50 max-w-md">
            {{ successToast }}
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
const showOwnerModal = ref(false)
const showMembersModal = ref(false)
const selectedOrganizer = ref(null)

// Actions menu (… dropdown per row)
const openActionsMenuId = ref(null)

// Move Events modal state
const showMoveEventsModal = ref(false)
const moveSource = ref(null)
const moveDestination = ref(null)
const moveDestinationSearch = ref('')
const moveDestinationResults = ref([])
const moveSwapSlug = ref(false)
const moveSubmitting = ref(false)
const moveError = ref('')
const successToast = ref('')

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
    selectedOrganizer.value = organizer
    showMembersModal.value = true
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
    selectedOrganizer.value = organizer
    showOwnerModal.value = true
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
            closeOwnerModal()
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

const closeOwnerModal = () => {
    showOwnerModal.value = false
    selectedOrganizer.value = null
    ownerSearch.value = ''
    ownerSearchResults.value = []
}

const closeMembersModal = () => {
    showMembersModal.value = false
    selectedOrganizer.value = null
    newMemberSearch.value = ''
    memberSearchResults.value = []
}

// ----- Actions menu (… dropdown) -----

const toggleActionsMenu = (organizer) => {
    openActionsMenuId.value = openActionsMenuId.value === organizer.id ? null : organizer.id
}

const closeActionsMenu = () => {
    openActionsMenuId.value = null
}

// ----- Move Events flow -----

const openMoveEvents = (organizer) => {
    closeActionsMenu()
    moveSource.value = organizer
    moveDestination.value = null
    moveDestinationSearch.value = ''
    moveDestinationResults.value = []
    moveSwapSlug.value = false
    moveError.value = ''
    showMoveEventsModal.value = true
}

const closeMoveEvents = () => {
    showMoveEventsModal.value = false
    moveSource.value = null
    moveDestination.value = null
    moveDestinationSearch.value = ''
    moveDestinationResults.value = []
    moveSwapSlug.value = false
    moveError.value = ''
}

watch(moveDestinationSearch, debounce(async () => {
    if (!moveDestinationSearch.value || moveDestinationSearch.value.length < 2) {
        moveDestinationResults.value = []
        return
    }
    try {
        const response = await axios.get('/api/admin/manage/organizers', {
            params: { search: moveDestinationSearch.value }
        })
        moveDestinationResults.value = response.data.data
    } catch (error) {
        console.error('Error searching organizers:', error)
    }
}, 300))

const selectMoveDestination = (organizer) => {
    moveDestination.value = organizer
}

const submitMoveEvents = async () => {
    if (!moveSource.value || !moveDestination.value) return

    moveSubmitting.value = true
    moveError.value = ''

    try {
        const response = await axios.post(
            `/api/admin/manage/organizers/${moveSource.value.slug}/move-events`,
            {
                destination_organizer_id: moveDestination.value.id,
                swap_slug: moveSwapSlug.value,
            }
        )

        // Update both rows in place — no full refresh needed.
        const { source, destination, moved_count } = response.data
        const srcIdx = organizers.value.findIndex(o => o.id === source.id)
        if (srcIdx !== -1) organizers.value[srcIdx] = source
        const destIdx = organizers.value.findIndex(o => o.id === destination.id)
        if (destIdx !== -1) organizers.value[destIdx] = destination

        successToast.value = `Moved ${moved_count} event(s) from "${source.name}" to "${destination.name}".`
        setTimeout(() => { successToast.value = '' }, 6000)
        closeMoveEvents()
    } catch (error) {
        moveError.value = error.response?.data?.message || 'Failed to move events.'
    } finally {
        moveSubmitting.value = false
    }
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