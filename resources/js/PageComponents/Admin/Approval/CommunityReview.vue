<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none px-8">
            <h2 class="text-2xl font-bold mb-6">Review Community</h2>
        </div>
        <!-- Scrollable Content Section -->
        <div class="flex-1 overflow-auto">
            <div class="max-w-screen-xl mx-auto px-8 pt-8">
                <div class="w-full md:w-[61rem] mx-auto pb-24 space-y-8">
                    <div v-if="props.community?.images?.length && props.community.images[0]?.large_image_path" class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-6">Community Image</h3>
                        <div class="aspect-[3/1] w-full rounded-2xl overflow-hidden bg-neutral-100">
                            <picture>
                                <source 
                                    :srcset="`${imageUrl}${props.community.images[0].large_image_path}`" 
                                    type="image/webp"
                                >
                                <img 
                                    :src="`${imageUrl}${props.community.images[0].large_image_path}`" 
                                    :alt="props.community.name"
                                    class="w-full h-full object-cover"
                                />
                            </picture>
                        </div>
                    </div>

                    <!-- Basic Info Section -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-5xl leading-tight font-semibold mb-6 break-words hyphens-auto">
                            {{ props.community?.name }}
                        </h3>
                        <div v-if="props.community?.description" class="mt-8">
                            <p class="whitespace-pre-line text-gray-600 text-xl">{{ props.community?.description }}</p>
                        </div>
                    </div>

                    <!-- Links Section -->
                    <div v-if="props.community?.website" class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-6">Links</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a :href="props.community.website"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="flex items-center gap-4 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black group">
                                <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                    <component :is="RiGlobalLine" class="w-6 h-6 text-neutral-700" />
                                </div>
                                <span class="truncate">{{ props.community.website }}</span>
                            </a>
                        </div>
                    </div>

                    <!-- Add this after the Links Section -->
                    <div v-if="props.community?.curators?.length" class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-6">Curators</h3>
                        <div class="space-y-4">
                            <a 
                                v-for="curator in props.community.curators" 
                                :key="curator.id"
                                target="_blank"
                                :href="`/users/${curator.id}`"
                                class="flex items-center gap-4 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black group"
                            >
                                <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 overflow-hidden">
                                    <img 
                                        v-if="curator.largeImagePath"
                                        :src="`${imageUrl}${curator.largeImagePath}`"
                                        :alt="curator.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <div 
                                        v-else 
                                        class="w-full h-full flex items-center justify-center bg-neutral-200"
                                        :style="{ backgroundColor: curator.hexColor }"
                                    >
                                        <span class="text-white text-xl font-medium">
                                            {{ curator.name.charAt(0) }}
                                        </span>
                                    </div>
                                </div>
                                <span class="text-xl">{{ curator.name }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fixed Footer Section -->
        <div class="flex border-t border-gray-200 bg-white h-32 justify-end items-center">
            <div class="px-8 py-6 flex gap-4">
                <!-- Edit Button -->
                <a 
                    :href="`/communities/${props.community?.slug}/edit`"
                    target="_blank"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors border border-black': true,
                        'bg-white text-black hover:bg-gray-100': true
                    }"
                >
                    <div class="flex items-center gap-2">
                        Edit Community
                    </div>
                </a>

                <!-- Reject Button -->
                <button 
                    @click="onReject"
                    :disabled="processing"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors border border-black': true,
                        'bg-white text-black hover:bg-gray-100': !processing,
                        'bg-gray-300 text-gray-500 cursor-not-allowed': processing
                    }"
                >
                    <div class="flex items-center gap-2">
                        <LoadingSpinner v-if="processing && isRejecting" />
                        {{ processing && isRejecting ? 'Rejecting...' : 'Reject' }}
                    </div>
                </button>

                <!-- Approve Button -->
                <button 
                    @click="onApprove"
                    :disabled="processing"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors': true,
                        'bg-black text-white hover:bg-gray-800': !processing,
                        'bg-gray-300 text-gray-500 cursor-not-allowed': processing
                    }"
                >
                    <div class="flex items-center gap-2">
                        <LoadingSpinner v-if="processing && isApproving" />
                        {{ processing && isApproving ? 'Approving...' : 'Approve' }}
                    </div>
                </button>
            </div>
        </div>

        <!-- Reject Modal -->
        <teleport to="body">
            <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
                <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh]">
                    <!-- Header -->
                    <div class="p-8 pb-6">
                        <h2 class="text-2xl font-bold mb-2">Reject Community</h2>
                        <p class="text-gray-500 font-normal">Please provide a reason for rejecting this community</p>
                    </div>

                    <!-- Content -->
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
                    <div class="p-8 border-t border-neutral-400">
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
                                <div class="flex items-center gap-2">
                                    <LoadingSpinner v-if="processing && isRejecting" />
                                    {{ processing && isRejecting ? 'Rejecting...' : 'Reject' }}
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { RiGlobalLine } from '@remixicon/vue'
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue';

const props = defineProps({
    community: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['community-approved', 'community-rejected'])
const rejectionReason = ref('')
const processing = ref(false)
const isRejecting = ref(false)
const isApproving = ref(false)
const showRejectModal = ref(false)
const imageUrl = import.meta.env.VITE_IMAGE_URL

const onReject = () => {
    showRejectModal.value = true
}

const closeRejectModal = () => {
    showRejectModal.value = false
    rejectionReason.value = ''
}

const confirmReject = async () => {
    if (!rejectionReason.value.trim()) return
    
    try {
        processing.value = true
        isRejecting.value = true
        await axios.post(`/api/admin/approve/communities/${props.community.slug}/reject`, {
            reason: rejectionReason.value
        })
        closeRejectModal()
        window.location.href = '/admin/dashboard?view=approve-communities'
    } catch (error) {
        console.error('Error rejecting community:', error)
    } finally {
        processing.value = false
        isRejecting.value = false
    }
}

const onApprove = async () => {
    try {
        processing.value = true
        isApproving.value = true
        await axios.post(`/api/admin/approve/communities/${props.community.slug}/approve`)
        window.location.href = '/admin/dashboard?view=approve-communities'
    } catch (error) {
        console.error('Error approving community:', error)
    } finally {
        processing.value = false
        isApproving.value = false
    }
}

const getImageUrl = (url, format) => {
    if (!url) return null
    return url.replace(/\.[^.]+$/, `.${format}`)
}

// Component API
defineExpose({
    isValid: async () => true,
    submitData: () => ({})
})
</script>

<style>
.shadow-custom-1 {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05), 0 5px 10px rgba(0, 0, 0, 0.05);
}
</style>
