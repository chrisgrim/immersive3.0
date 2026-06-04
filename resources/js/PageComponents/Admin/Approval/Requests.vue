<template>
    <div class="space-y-8">
        <h1 class="text-4xl font-medium">Requests</h1>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <LoadingSpinner class="h-8 w-8 text-gray-400" />
        </div>

        <!-- No Requests Message -->
        <div v-else-if="!requests.length" class="text-center py-12">
            <p class="text-gray-500 text-lg">No pending requests</p>
        </div>

        <!-- Requests List -->
        <div v-else class="space-y-4">
            <div v-for="request in requests" :key="requestKey(request)"
                class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <div class="flex justify-between items-start">
                    <div class="space-y-2">
                        <!-- Type + date -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-500">{{ requestLabel(request) }}</span>
                            <span class="text-gray-300">•</span>
                            <span class="text-sm text-gray-500">{{ formatDate(request.created_at) }}</span>
                        </div>

                        <!-- Name change request body -->
                        <template v-if="request.kind === 'name_change'">
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
                        </template>

                        <!-- Ownership claim request body -->
                        <template v-else>
                            <div class="flex items-center gap-3">
                                <a v-if="request.organizer"
                                   :href="`/organizers/${request.organizer.slug}`"
                                   target="_blank"
                                   class="text-lg font-medium text-blue-600 hover:text-blue-800 underline">
                                    {{ request.organizer.name }}
                                </a>
                                <span v-else class="text-lg font-medium text-gray-400">[organization removed]</span>
                            </div>
                            <div v-if="request.entered_by" class="text-sm text-gray-500">
                                Entered internally by {{ request.entered_by.name }} ({{ request.entered_by.email }})
                            </div>
                            <div v-if="request.message" class="text-gray-600">
                                “{{ request.message }}”
                            </div>
                        </template>

                        <!-- Requester (both types) -->
                        <div class="text-sm text-gray-500">
                            Requested by {{ request.user?.name }} ({{ request.user?.email }})
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button
                            @click="handleApprove(request)"
                            :disabled="processing === requestKey(request)"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md disabled:opacity-50 flex items-center gap-2"
                        >
                            <LoadingSpinner v-if="processing === requestKey(request) && processingAction === 'approve'" />
                            Approve
                        </button>
                        <button
                            @click="handleReject(request)"
                            :disabled="processing === requestKey(request)"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50 flex items-center gap-2"
                        >
                            <LoadingSpinner v-if="processing === requestKey(request) && processingAction === 'reject'" />
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
                        <h2 class="text-2xl font-bold mb-2">Reject Request</h2>
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
                                :disabled="!rejectionReason.trim() || processing !== null"
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                <LoadingSpinner v-if="processing !== null" />
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
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue'

// The Requests queue is a catch-all: name-change requests and ownership claims are both
// "requests" here. They keep separate backends/endpoints; this component merges them into
// one list and dispatches approve/reject to the right endpoint by `kind`.
const requests = ref([])
const loading = ref(true)
const processing = ref(null) // holds the composite key of the request being processed
const processingAction = ref(null)
const showToast = ref(false)
const toastMessage = ref('')
const showRejectModal = ref(false)
const rejectionReason = ref('')
const pendingRejectRequest = ref(null)

const emit = defineEmits(['update-counts'])

const requestKey = (request) => `${request.kind}-${request.id}`

const requestLabel = (request) => request.kind === 'ownership_claim'
    ? 'Ownership Claim'
    : `Name Change${request.type ? ' · ' + request.type : ''}`

const endpoints = (request) => request.kind === 'ownership_claim'
    ? {
        approve: `/api/admin/approve/claims/${request.id}/approve`,
        reject: `/api/admin/approve/claims/${request.id}/reject`,
    }
    : {
        approve: `/api/admin/approve/requests/${request.id}/approve`,
        reject: `/api/admin/approve/requests/${request.id}/reject`,
    }

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
        const [nameChangeRes, claimRes] = await Promise.all([
            axios.get('/api/admin/approve/requests'),
            axios.get('/api/admin/approve/claims'),
        ])
        const nameChanges = (nameChangeRes.data.requests || []).map(r => ({ ...r, kind: 'name_change' }))
        const claims = (claimRes.data.claims || []).map(c => ({ ...c, kind: 'ownership_claim' }))
        requests.value = [...nameChanges, ...claims]
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    } catch (error) {
        console.error('Error fetching requests:', error)
        showToastMessage('Failed to load requests')
    } finally {
        loading.value = false
    }
}

const handleApprove = async (request) => {
    try {
        processing.value = requestKey(request)
        processingAction.value = 'approve'
        await axios.post(endpoints(request).approve)
        showToastMessage('Request approved successfully')
        emit('update-counts')
        // Approving an ownership claim auto-rejects sibling claims for the same org server-side,
        // so resync the whole queue rather than only dropping the clicked row.
        if (request.kind === 'ownership_claim') {
            await fetchRequests()
        } else {
            requests.value = requests.value.filter(r => requestKey(r) !== requestKey(request))
        }
    } catch (error) {
        console.error('Error approving request:', error)
        showToastMessage(error.response?.data?.message || 'Failed to approve request')
        // May have been processed elsewhere / no longer valid — refresh to reflect reality.
        await fetchRequests()
    } finally {
        processing.value = null
        processingAction.value = null
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
    const request = pendingRejectRequest.value
    if (!request || !rejectionReason.value.trim()) return

    try {
        processing.value = requestKey(request)
        processingAction.value = 'reject'
        await axios.post(endpoints(request).reject, { reason: rejectionReason.value })
        showToastMessage('Request rejected successfully')
        requests.value = requests.value.filter(r => requestKey(r) !== requestKey(request))
        emit('update-counts')
        closeRejectModal()
    } catch (error) {
        console.error('Error rejecting request:', error)
        showToastMessage(error.response?.data?.message || 'Failed to reject request')
        await fetchRequests()
        closeRejectModal()
    } finally {
        processing.value = null
        processingAction.value = null
    }
}

onMounted(() => {
    fetchRequests()
})
</script>
