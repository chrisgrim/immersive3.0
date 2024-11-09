<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2>Review Event</h2>

            <!-- Main Content -->
            <div class="gap-8 mt-8">
                <!-- Images Section -->
                <div class="grid gap-4">
                    <!-- Single Image -->
                    <div v-if="event.images?.length === 1" 
                         class="aspect-[3/2] w-full overflow-hidden md:rounded-xl">
                        <img :src="imageUrl + event.images[0].large_image_path"
                             :alt="`${event.name} Immersive Event - Main Image`"
                             class="w-full h-full object-cover"
                        />
                    </div>

                    <!-- Multiple Images -->
                    <div v-else 
                         class="grid gap-2 md:rounded-xl overflow-hidden grid-cols-2"
                         :class="{
                            'grid-cols-2': event.images?.length === 2,
                            'grid-cols-[2fr_1fr]': event.images?.length === 3,
                            'grid-cols-2': event.images?.length >= 4
                         }">
                        <!-- Left Side -->
                        <div v-if="event.images?.length >= 4" class="grid gap-2 overflow-hidden">
                            <div class="aspect-[3/2]">
                                <img :src="imageUrl + event.images[0].large_image_path"
                                     :alt="`${event.name} Immersive Event - Main Image`"
                                     class="w-full h-full object-cover"
                                />
                            </div>
                            <div v-if="event.images?.length === 4" class="aspect-[3/2]">
                                <img :src="imageUrl + event.images[1].large_image_path"
                                     :alt="`${event.name} Immersive Event - Image 2`"
                                     class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                        <div v-else class="aspect-[3/2]">
                            <img :src="imageUrl + event.images[0].large_image_path"
                                 :alt="`${event.name} Immersive Event - Main Image`"
                                 class="w-full h-full object-cover"
                            />
                        </div>

                        <!-- Right Side Images -->
                        <!-- ... (rest of the image layout code) ... -->
                    </div>
                </div>

                <!-- Event Details -->
                <div class="w-full mt-8">
                    <h2 class="text-4xl">Name:</h2>
                    <p class="2xl">{{ event.name }}</p>
                    <p class="text-gray-500 font-normal">{{ event.tag_line }}</p>
                </div>
            </div>

            <!-- Location Section -->
            <div class="mt-12 border-t pt-8">
                <div v-if="event.hasLocation">
                    <h2 class="text-4xl">Location:</h2>
                    <p class="text-gray-500 font-normal">{{ event.location.venue }}</p>
                    <p class="text-gray-500 font-normal">{{ event.location.street }}</p>
                    <p class="text-gray-500 font-normal">{{ event.location.city }}, {{ event.location.region }} {{ event.location.postal_code }}</p>
                </div>
                <div v-else>
                    <h2 class="text-4xl">Remote:</h2>
                    <div class="flex flex-wrap gap-4 mt-1">
                        <div v-for="location in event.remotelocations" 
                             :key="location.id" 
                             class="text-gray-500 font-normal border border-gray-300 rounded-lg px-4 py-2">
                            {{ location.name }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shows & Tickets Section -->
            <div class="mt-12 border-t pt-8">
                <h2 class="text-4xl mb-4">Shows & Tickets:</h2>
                <div v-if="event.shows?.length" class="space-y-4">
                    <div v-for="show in event.shows" :key="show.id" class="border p-4 rounded-lg">
                        <p class="font-semibold">{{ moment(show.date).format('MMMM D, YYYY') }}</p>
                        <p>{{ moment(show.time, 'HH:mm:ss').format('h:mm A') }}</p>
                        <div v-if="show.tickets?.length" class="mt-2">
                            <p class="font-semibold">Tickets:</p>
                            <ul class="list-disc list-inside">
                                <li v-for="ticket in show.tickets" :key="ticket.id">
                                    {{ ticket.name }} - ${{ ticket.price }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories and Genres Section -->
            <div class="mt-12 border-t pt-8">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <h2 class="text-2xl font-semibold mb-4">Category:</h2>
                        <p class="text-gray-600">{{ event.category?.name }}</p>
                    </div>
                    <div v-if="event.genres?.length">
                        <h2 class="text-2xl font-semibold mb-4">Genres:</h2>
                        <div class="flex flex-wrap gap-2">
                            <span 
                                v-for="genre in event.genres" 
                                :key="genre.id"
                                class="px-3 py-1 bg-gray-100 rounded-full text-sm"
                            >
                                {{ genre.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advisories Section -->
            <div class="mt-12 border-t pt-8">
                <h2 class="text-4xl mb-6">Advisories:</h2>
                
                <!-- Age Limits -->
                <div class="mb-8">
                    <h3 class="text-2xl font-semibold mb-2">Age Requirements:</h3>
                    <p class="text-gray-600">{{ event.age_limits?.name || 'Not specified' }}</p>
                </div>

                <!-- Content Advisories -->
                <div v-if="event.contentAdvisories?.length" class="mb-8">
                    <h3 class="text-2xl font-semibold mb-2">Content Advisories:</h3>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-for="advisory in event.contentAdvisories" 
                            :key="advisory.id"
                            class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm"
                        >
                            {{ advisory.name }}
                        </span>
                    </div>
                </div>

                <!-- Mobility Advisories -->
                <div v-if="event.mobilityAdvisories?.length" class="mb-8">
                    <h3 class="text-2xl font-semibold mb-2">Mobility Advisories:</h3>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-for="advisory in event.mobilityAdvisories" 
                            :key="advisory.id"
                            class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm"
                        >
                            {{ advisory.name }}
                        </span>
                    </div>
                </div>

                <!-- Contact Levels -->
                <div v-if="event.contactLevels?.length" class="mb-8">
                    <h3 class="text-2xl font-semibold mb-2">Contact Levels:</h3>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-for="level in event.contactLevels" 
                            :key="level.id"
                            class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm"
                        >
                            {{ level.name }}
                        </span>
                    </div>
                </div>

                <!-- Interactive Level -->
                <div class="mb-8">
                    <h3 class="text-2xl font-semibold mb-2">Interactive Level:</h3>
                    <p class="text-gray-600">{{ event.interactive_level?.name || 'Not specified' }}</p>
                </div>

                <!-- General Advisories -->
                <div v-if="event.advisories" class="mb-8">
                    <h3 class="text-2xl font-semibold mb-2">Additional Information:</h3>
                    <div class="space-y-4">
                        <div v-if="event.advisories.audience">
                            <p class="font-semibold">Audience Role:</p>
                            <p class="text-gray-600">{{ event.advisories.audience }}</p>
                        </div>
                        <div v-if="event.advisories.content">
                            <p class="font-semibold">Content Notes:</p>
                            <p class="text-gray-600">{{ event.advisories.content }}</p>
                        </div>
                        <div v-if="event.advisories.mobility">
                            <p class="font-semibold">Mobility Notes:</p>
                            <p class="text-gray-600">{{ event.advisories.mobility }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Organizer Section -->
            <div class="mt-12 border-t pt-8">
                <h2 class="text-4xl mb-4">Organizer:</h2>
                <div v-if="event.organizer" class="text-gray-600">
                    <p class="text-2xl font-semibold">{{ event.organizer.name }}</p>
                    <p class="mt-2">{{ event.organizer.description }}</p>
                </div>
            </div>

            <!-- Video Section -->
            <div v-if="event.video" class="mt-12 border-t pt-8">
                <!-- ... (video section code) ... -->
            </div>

            <!-- Approval Actions -->
            <div class="sticky bottom-0 bg-white border-t mt-8 p-4 flex justify-end gap-4">
                <button 
                    @click="onReject"
                    class="px-6 py-2 border border-red-500 text-red-500 rounded-lg hover:bg-red-50"
                >
                    Reject
                </button>
                <button 
                    @click="onApprove"
                    class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600"
                >
                    Approve
                </button>
            </div>
        </div>

        <!-- Rejection Modal -->
        <div v-if="showRejectionModal" 
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-xl font-semibold mb-4">Rejection Reason</h3>
                <textarea
                    v-model="rejectionReason"
                    class="w-full h-32 border rounded-lg p-2 mb-4"
                    placeholder="Please provide a reason for rejection..."
                    :class="{ 'border-red-500': rejectionError }"
                ></textarea>
                <p v-if="rejectionError" class="text-red-500 text-sm mb-4">
                    {{ rejectionError }}
                </p>
                <div class="flex justify-end gap-4">
                    <button 
                        @click="showRejectionModal = false"
                        class="px-4 py-2 border rounded-lg"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="submitRejection"
                        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                    >
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref } from 'vue';
import moment from 'moment-timezone';

const props = defineProps({
    event: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['approved', 'rejected']);
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const showRejectionModal = ref(false);
const rejectionReason = ref('');
const rejectionError = ref('');

const formatDateRange = (shows) => {
    if (!shows?.length) return 'No dates set';
    
    const dates = shows.map(show => moment(show.date));
    const firstDate = moment.min(dates).format('MMM D, YYYY');
    const lastDate = moment.max(dates).format('MMM D, YYYY');
    
    return firstDate === lastDate ? firstDate : `${firstDate} - ${lastDate}`;
};

const onApprove = async () => {
    try {
        await axios.post(`/api/admin/approve/events/${props.event.slug}/approve`);
        emit('approved');
    } catch (error) {
        console.error('Error approving event:', error);
    }
};

const onReject = () => {
    showRejectionModal.value = true;
};

const submitRejection = async () => {
    if (!rejectionReason.value.trim()) {
        rejectionError.value = 'Please provide a reason for rejection';
        return;
    }

    try {
        await axios.post(`/api/admin/approve/events/${props.event.slug}/reject`, {
            reason: rejectionReason.value
        });
        showRejectionModal.value = false;
        rejectionReason.value = '';
        rejectionError.value = '';
        emit('rejected');
    } catch (error) {
        console.error('Error rejecting event:', error);
        rejectionError.value = 'Failed to reject event. Please try again.';
    }
};
</script>
