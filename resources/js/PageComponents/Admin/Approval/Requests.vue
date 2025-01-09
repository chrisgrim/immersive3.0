<template>
    <div class="space-y-8">
        <h1 class="text-4xl font-medium">Name Change Requests</h1>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <svg class="animate-spin h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <!-- No Requests Message -->
        <div v-else-if="!requests.length" class="text-center py-12">
            <p class="text-gray-500 text-lg">No pending name change requests</p>
        </div>

        <!-- Requests List -->
        <div v-else class="space-y-4">
            <div v-for="request in requests" :key="request.id" 
                class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <div class="flex justify-between items-start">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-500">{{ request.type }}</span>
                            <span class="text-gray-300">•</span>
                            <span class="text-sm text-gray-500">{{ formatDate(request.created_at) }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-medium text-gray-900">{{ request.current_name }}</span>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                            <span class="text-lg font-medium text-gray-900">{{ request.requested_name }}</span>
                        </div>
                        <div v-if="request.reason" class="text-gray-600">
                            {{ request.reason }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Requested by {{ request.user.name }} ({{ request.user.email }})
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button 
                            @click="handleApprove(request)"
                            :disabled="processing === request.id"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md disabled:opacity-50"
                        >
                            Approve
                        </button>
                        <button 
                            @click="handleReject(request)"
                            :disabled="processing === request.id"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50"
                        >
                            Reject
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Message -->
        <div v-if="showToast" 
            class="fixed bottom-4 right-4 bg-gray-800 text-white px-6 py-3 rounded-lg shadow-lg">
            {{ toastMessage }}
        </div>

        <!-- Rejection Modal -->
        <teleport to="body">
            <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
                <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                    <!-- Header -->
                    <div class="p-8 pb-6">
                        <h2 class="text-2xl font-bold mb-2">Reject Name Change</h2>
                        <p class="text-gray-500 font-normal">Please provide a reason for rejecting this request</p>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="p-8 pt-0 overflow-y-auto flex-1">
                        <div class="space-y-6">
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Reason</p>
                                <textarea 
                                    v-model="rejectionReason"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter reason for rejection..."
                                    rows="4"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                        <div class="flex justify-end space-x-4">
                            <button 
                                @click="closeRejectModal"
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="confirmReject"
                                :disabled="!rejectionReason.trim()"
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const requests = ref([])
const loading = ref(true)
const processing = ref(null)
const showToast = ref(false)
const toastMessage = ref('')
const showRejectModal = ref(false)
const rejectionReason = ref('')
const pendingRejectRequest = ref(null)

const showToastMessage = (message) => {
    toastMessage.value = message
    showToast.value = true
    setTimeout(() => {
        showToast.value = false
    }, 3000)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

const fetchRequests = async () => {
    try {
        loading.value = true
        const response = await axios.get('/api/admin/approve/requests')
        requests.value = response.data.requests
    } catch (error) {
        console.error('Error fetching requests:', error)
        showToastMessage('Failed to load requests')
    } finally {
        loading.value = false
    }
}

const handleApprove = async (request) => {
    try {
        processing.value = request.id
        const response = await axios.post(`/api/admin/approve/requests/${request.id}/approve`)
        showToastMessage('Request approved successfully')
        
        // Remove the request from the list
        requests.value = requests.value.filter(r => r.id !== request.id)
        
        if (response.data.requiresRefresh) {
            // Handle any necessary UI updates
        }
    } catch (error) {
        console.error('Error approving request:', error)
        showToastMessage('Failed to approve request')
    } finally {
        processing.value = null
    }
}

const handleReject = (request) => {
    pendingRejectRequest.value = request
    showRejectModal.value = true
}

const closeRejectModal = () => {
    showRejectModal.value = false
    rejectionReason.value = ''
    pendingRejectRequest.value = null
}

const confirmReject = async () => {
    if (!rejectionReason.value.trim()) return

    try {
        processing.value = pendingRejectRequest.value.id
        await axios.post(`/api/admin/approve/requests/${pendingRejectRequest.value.id}/reject`, {
            reason: rejectionReason.value
        })
        showToastMessage('Request rejected successfully')
        
        // Remove the request from the list
        requests.value = requests.value.filter(r => r.id !== pendingRejectRequest.value.id)
        closeRejectModal()
    } catch (error) {
        console.error('Error rejecting request:', error)
        showToastMessage('Failed to reject request')
    } finally {
        processing.value = null
    }
}

onMounted(() => {
    fetchRequests()
})
</script>