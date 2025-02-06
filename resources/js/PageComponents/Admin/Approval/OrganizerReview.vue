<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none px-8">
            <h2 class="text-2xl font-bold mb-6">Review Organizer</h2>
        </div>

        <!-- Scrollable Content Section -->
        <div class="flex-1 overflow-auto">
            <div class="max-w-screen-xl mx-auto px-8 pt-8">
                <div class="w-full md:w-[61rem] mx-auto pb-24 space-y-8">
                    <!-- Profile and Basic Info Section -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <div v-if="props.organizer?.images?.length" class="mb-8">
                            <div class="w-48 h-48 mx-auto overflow-hidden rounded-full">
                                <img 
                                    :src="imageUrl + props.organizer.images[0].large_image_path"
                                    :alt="props.organizer.name"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                        <h3 class="text-5xl leading-tight font-semibold mb-6 break-words hyphens-auto text-center">
                            {{ props.organizer?.name }}
                        </h3>
                        <div v-if="props.organizer?.description" class="mt-8">
                            <p class="whitespace-pre-line text-gray-600 text-xl text-center">{{ props.organizer?.description }}</p>
                        </div>
                    </div>

                    <!-- Social Links Section -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-6">Social Links & Contact</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div v-for="media in socialMediaList" 
                                 :key="media.name"
                                 :class="{
                                     'border-[#e5e7eb] text-gray-400': !props.organizer[media.model],
                                     'border-black text-black shadow-focus-black': props.organizer[media.model],
                                 }"
                                 class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-all duration-200">
                                <div class="flex items-start justify-start w-full h-16 rounded-2xl">
                                    <component
                                        :is="media.icon"
                                        class="w-12 h-12"
                                        :class="{
                                            'text-[#adadad]': !props.organizer[media.model],
                                            'text-black': props.organizer[media.model],
                                        }"
                                    />
                                </div>
                                <div class="overflow-hidden w-full">
                                    <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap"
                                        :class="{
                                            'text-[#adadad]': !props.organizer[media.model],
                                            'text-black': props.organizer[media.model],
                                        }">
                                        {{ props.organizer[media.model] || media.placeholder }}
                                    </h4>
                                </div>
                            </div>
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
                    :href="`/organizers/${props.organizer?.slug}/edit`"
                    target="_blank"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors border border-black': true,
                        'bg-white text-black hover:bg-gray-100': true
                    }"
                >
                    <div class="flex items-center gap-2">
                        Edit Organizer
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
                        <svg 
                            v-if="processing && isRejecting"
                            class="animate-spin h-5 w-5" 
                            xmlns="http://www.w3.org/2000/svg" 
                            fill="none" 
                            viewBox="0 0 24 24"
                        >
                            <circle 
                                class="opacity-25" 
                                cx="12" 
                                cy="12" 
                                r="10" 
                                stroke="currentColor" 
                                stroke-width="4"
                            />
                            <path 
                                class="opacity-75" 
                                fill="currentColor" 
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
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
                        <svg 
                            v-if="processing && isApproving"
                            class="animate-spin h-5 w-5" 
                            xmlns="http://www.w3.org/2000/svg" 
                            fill="none" 
                            viewBox="0 0 24 24"
                        >
                            <circle 
                                class="opacity-25" 
                                cx="12" 
                                cy="12" 
                                r="10" 
                                stroke="currentColor" 
                                stroke-width="4"
                            />
                            <path 
                                class="opacity-75" 
                                fill="currentColor" 
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
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
                        <h2 class="text-2xl font-bold mb-2">Reject Organizer</h2>
                        <p class="text-gray-500 font-normal">Please provide a reason for rejecting this organizer</p>
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
import { ref, watch } from 'vue';
import { 
    RiMailLine,
    RiSearchLine,
    RiTwitterLine,
    RiInstagramLine,
    RiFacebookBoxLine,
    RiPatreonLine,
    RiGlobalLine
} from '@remixicon/vue';

const props = defineProps({
    organizer: {
        type: Object,
        required: true
    }
});

const socialMediaList = [
    {
        name: 'website',
        model: 'website',
        icon: RiGlobalLine,
        placeholder: 'Website URL'
    },
    {
        name: 'email',
        model: 'email',
        icon: RiMailLine,
        placeholder: 'Email Address'
    },
    {
        name: 'instagram',
        model: 'instagramHandle',
        icon: RiInstagramLine,
        placeholder: 'Instagram Handle'
    },
    {
        name: 'twitter',
        model: 'twitterHandle',
        icon: RiTwitterLine,
        placeholder: 'Twitter Handle'
    },
    {
        name: 'facebook',
        model: 'facebookHandle',
        icon: RiFacebookBoxLine,
        placeholder: 'Facebook URL'
    },
    {
        name: 'patreon',
        model: 'patreon',
        icon: RiPatreonLine,
        placeholder: 'Patreon URL'
    }
];

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const showRejectModal = ref(false);
const rejectionReason = ref('');
const processing = ref(false);
const isRejecting = ref(false);
const isApproving = ref(false);

const onReject = () => {
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    rejectionReason.value = '';
};

const confirmReject = async () => {
    if (!rejectionReason.value.trim()) return;
    
    try {
        processing.value = true;
        isRejecting.value = true;
        await axios.post(`/api/admin/approve/organizers/${props.organizer.slug}/reject`, {
            reason: rejectionReason.value
        });
        
        closeRejectModal();
        window.location.href = '/admin/dashboard?view=approve-organizers';
    } catch (error) {
        console.error('Error rejecting organizer:', error);
    } finally {
        processing.value = false;
        isRejecting.value = false;
    }
};

const onApprove = async () => {
    try {
        processing.value = true;
        isApproving.value = true;
        await axios.post(`/api/admin/approve/organizers/${props.organizer.slug}/approve`);
        window.location.href = '/admin/dashboard?view=approve-organizers';
    } catch (error) {
        console.error('Error approving organizer:', error);
    } finally {
        processing.value = false;
        isApproving.value = false;
    }
};

// Component API
defineExpose({
    isValid: async () => true, // Review page is always valid
    submitData: () => ({}) // No data to submit from review page
});

watch(() => props.organizer, (newValue) => {
    console.log('Organizer props in OrganizerReview:', newValue);
}, { immediate: true, deep: true });
</script>

<style>
.shadow-focus-black {
    box-shadow: 0 0 0 1.5px black, 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

.shadow-focus-error {
    box-shadow: 0 0 0 1.5px #ef4444, 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}
</style>
