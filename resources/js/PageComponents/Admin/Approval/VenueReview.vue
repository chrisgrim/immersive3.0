<template>
    <!-- The organizer review screen, for a venue a team added. -->
    <div class="flex flex-col md:h-[calc(100vh-12rem)] md:max-h-[calc(100vh-10rem)]">
        <div class="flex-none px-8">
            <h2 class="text-2xl font-bold mb-6">Review Venue</h2>
        </div>

        <div class="flex-1 overflow-auto">
            <div class="max-w-screen-xl mx-auto px-8 pt-8">
                <div class="w-full md:w-[61rem] mx-auto pb-24 space-y-8">
                    <!-- Who added it, first so it is seen at a glance. -->
                    <div v-if="props.venue?.added_by" class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-4">Added by:</h3>
                        <h4 class="text-4xl font-medium">{{ props.venue.added_by.organizer?.name }}</h4>
                        <p v-if="props.venue.added_by.user" class="text-gray-600 text-xl">{{ props.venue.added_by.user.name }} · {{ props.venue.added_by.user.email }}</p>
                    </div>

                    <!-- Photo, name and description -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <div class="mb-8">
                            <div class="w-48 h-48 mx-auto overflow-hidden rounded-full bg-gray-200 flex items-center justify-center">
                                <img v-if="props.venue?.photos?.length" :src="imageUrl + props.venue.photos[0].url" :alt="props.venue.name" class="w-full h-full object-cover" />
                                <svg v-else class="w-1/3 h-1/3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5" stroke-width="2"/></svg>
                            </div>
                        </div>
                        <h3 class="text-5xl leading-tight font-semibold mb-2 break-words hyphens-auto text-center">{{ props.venue?.name }}</h3>
                        <p v-if="props.venue?.room" class="text-gray-500 text-xl text-center">{{ props.venue.room }}</p>
                        <div v-if="props.venue?.description" class="mt-8">
                            <p class="whitespace-pre-line text-gray-600 text-xl text-center">{{ props.venue.description }}</p>
                        </div>
                    </div>

                    <!-- Where it is -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-6">Location</h3>
                        <p class="text-2xl">{{ props.venue?.street || props.venue?.home || 'No address' }}</p>
                        <p class="text-gray-600 text-xl">{{ [props.venue?.city, [props.venue?.region, props.venue?.postal_code].filter(Boolean).join(' '), props.venue?.country].filter(Boolean).join(', ') }}</p>
                        <div v-if="props.venue?.latitude && props.venue?.longitude" class="mt-6">
                            <VenueMap :venue="props.venue" box-class="h-[28rem] rounded-3xl" />
                        </div>
                        <p v-if="props.venue?.directions" class="mt-6 whitespace-pre-line text-gray-600 text-xl">{{ props.venue.directions }}</p>
                    </div>

                    <!-- Contact, as an organizer review shows its links -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-6">Social Links & Contact</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div v-for="media in socialMediaList"
                                 :key="media.model"
                                 :class="props.venue?.[media.model] ? 'border-black text-black shadow-focus-black' : 'border-[#e5e7eb] text-gray-400'"
                                 class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-all duration-200">
                                <div class="flex items-start justify-start w-full h-16 rounded-2xl">
                                    <component :is="media.icon" class="w-12 h-12" :class="props.venue?.[media.model] ? 'text-black' : 'text-[#adadad]'" />
                                </div>
                                <div class="overflow-hidden w-full">
                                    <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap" :class="props.venue?.[media.model] ? 'text-black' : 'text-[#adadad]'">
                                        {{ props.venue?.[media.model] || media.placeholder }}
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-none flex border-t border-gray-200 bg-white justify-end items-center md:h-32">
            <div class="w-full px-6 py-6 md:px-8 flex flex-col md:flex-row gap-4 md:justify-end md:items-center">
                <a :href="`/venues/${props.venue?.slug}/edit`" target="_blank" class="px-6 py-3 rounded-lg transition-colors border border-black bg-white text-black hover:bg-gray-100">
                    <div class="flex items-center justify-center gap-2">Edit Venue</div>
                </a>

                <!-- Ask a question: goes to whoever runs the team that added it. The venue stays waiting. -->
                <button
                    @click="showQuestionModal = true"
                    :disabled="processing || !props.venue?.added_by"
                    class="px-6 py-3 rounded-lg transition-colors border border-black"
                    :class="processing || !props.venue?.added_by ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-white text-black hover:bg-gray-100'"
                >
                    <div class="flex items-center justify-center gap-2">
                        <LoadingSpinner v-if="processing && isAsking" />
                        {{ processing && isAsking ? 'Sending...' : 'Ask a question' }}
                    </div>
                </button>

                <button
                    @click="onApprove"
                    :disabled="processing"
                    class="px-6 py-3 rounded-lg transition-colors"
                    :class="processing ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-black text-white hover:bg-gray-800'"
                >
                    <div class="flex items-center justify-center gap-2">
                        <LoadingSpinner v-if="processing && isApproving" />
                        {{ processing && isApproving ? 'Approving...' : 'Approve' }}
                    </div>
                </button>
            </div>
        </div>

        <teleport to="body">
            <div v-if="showQuestionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
                <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh]">
                    <div class="p-8 pb-6">
                        <h2 class="text-2xl font-bold mb-2">Ask a question</h2>
                        <p class="text-gray-500 font-normal">It goes to whoever runs the team that added this venue, by message. The venue keeps waiting here. Nothing is deleted.</p>
                    </div>
                    <div class="p-8 pt-0 overflow-y-auto flex-1">
                        <p class="text-gray-500 font-normal mb-4">Your question</p>
                        <textarea
                            v-model="question"
                            class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                            placeholder="For example: Is this the venue's real name? Is the address right?"
                            rows="4"
                        ></textarea>
                        <p v-if="error" class="text-red-600 mt-4">{{ error }}</p>
                    </div>
                    <div class="p-8 border-t border-neutral-400">
                        <div class="flex justify-end space-x-4">
                            <button @click="closeQuestion" class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl">Cancel</button>
                            <button
                                @click="sendQuestion"
                                :disabled="processing || !question.trim()"
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <div class="flex items-center gap-2">
                                    <LoadingSpinner v-if="processing && isAsking" />
                                    {{ processing && isAsking ? 'Sending...' : 'Send question' }}
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
import { ref } from 'vue';
import axios from 'axios';
import { RiMailLine, RiPhoneLine, RiTwitterLine, RiInstagramLine, RiFacebookBoxLine, RiGlobalLine, RiTiktokLine } from '@remixicon/vue';
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue';
import VenueMap from '../../EventShow/venue-map.vue';

const props = defineProps({
    venue: { type: Object, required: true },
});

const socialMediaList = [
    { model: 'website', icon: RiGlobalLine, placeholder: 'Website URL' },
    { model: 'email', icon: RiMailLine, placeholder: 'Email Address' },
    { model: 'phone', icon: RiPhoneLine, placeholder: 'Phone' },
    { model: 'instagramHandle', icon: RiInstagramLine, placeholder: 'Instagram Handle' },
    { model: 'facebookHandle', icon: RiFacebookBoxLine, placeholder: 'Facebook URL' },
    { model: 'tiktokHandle', icon: RiTiktokLine, placeholder: 'TikTok Handle' },
    { model: 'twitterHandle', icon: RiTwitterLine, placeholder: 'Twitter Handle' },
];

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const showQuestionModal = ref(false);
const question = ref('');
const error = ref('');
const processing = ref(false);
const isAsking = ref(false);
const isApproving = ref(false);

const closeQuestion = () => {
    showQuestionModal.value = false;
    question.value = '';
    error.value = '';
};

const sendQuestion = async () => {
    if (processing.value || !question.value.trim()) return;
    try {
        processing.value = true;
        isAsking.value = true;
        await axios.post(`/api/admin/venues/${props.venue.slug}/question`, { question: question.value });
        closeQuestion();
        window.location.href = '/admin/dashboard?view=approve-venues';
    } catch (failure) {
        error.value = failure.response?.data?.message || 'That did not go through.';
        processing.value = false;
        isAsking.value = false;
    }
};

const onApprove = async () => {
    try {
        processing.value = true;
        isApproving.value = true;
        await axios.post(`/api/admin/venues/${props.venue.slug}/approve`);
        window.location.href = '/admin/dashboard?view=approve-venues';
    } catch (failure) {
        console.error('Error approving venue:', failure);
        processing.value = false;
        isApproving.value = false;
    }
};
</script>
