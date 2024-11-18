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

                        <!-- Right Side -->
                        <div v-if="event.images?.length === 2" class="aspect-[3/2]">
                            <img :src="imageUrl + event.images[1].large_image_path"
                                 :alt="`${event.name} Immersive Event - Image 2`"
                                 class="w-full h-full object-cover"
                            />
                        </div>
                        <div v-else-if="event.images?.length === 3" class="grid gap-2">
                            <div v-for="(image, key) in event.images.slice(1)" 
                                 :key="image.id"
                                 class="aspect-[3/2]">
                                <img :src="imageUrl + image.large_image_path"
                                     :alt="`${event.name} Immersive Event - Image ${key + 2}`"
                                     class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                        <div v-else-if="event.images?.length === 4" class="grid gap-2">
                            <div v-for="(image, key) in event.images.slice(2)" 
                                 :key="image.id"
                                 class="aspect-[3/2]">
                                <img :src="imageUrl + image.large_image_path"
                                     :alt="`${event.name} Immersive Event - Image ${key + 3}`"
                                     class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                        <div v-else-if="event.images?.length === 5" class="grid grid-cols-2 gap-2">
                            <div v-for="(image, key) in event.images.slice(1, 5)" 
                                 :key="image.id">
                                <img :src="imageUrl + image.large_image_path"
                                     :alt="`${event.name} Immersive Event - Image ${key + 2}`"
                                     class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event Details -->
                <div class="w-full mt-8">
                    <h2 class="text-4xl">Name:</h2>
                    <p class="2xl">{{ event.name }}</p>
                    <p class="text-gray-500 font-normal">{{ event.tag_line }}</p>
                    
                    <!-- Description -->
                    <div class="mt-8">
                        <p class="font-semibold">Description</p>
                        <p class="text-gray-500 font-normal whitespace-pre-line">{{ event.description }}</p>
                    </div>

                    <!-- Embargo Date -->
                    <div v-if="event.embargo_date" class="mt-8">
                        <p class="font-semibold">Goes Live</p>
                        <p class="text-gray-500 font-normal">
                            {{ formatEmbargoDate(event.embargo_date) }}
                        </p>
                    </div>
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

            <!-- Categories and Genres Section -->
            <div class="mt-12 border-t pt-8">
                <h2 class="text-4xl">Categories & Genres:</h2>
                <div class="grid grid-cols-2 gap-8 mt-4">
                    <div>
                        <p class="font-semibold">Category</p>
                        <p class="text-gray-500 font-normal">{{ event.category?.name }}</p>
                    </div>
                    <div>
                        <p class="font-semibold">Genres</p>
                        <div class="flex flex-wrap gap-4 mt-1">
                            <div v-for="genre in event.genres" 
                                 :key="genre.id" 
                                 class="text-gray-500 font-normal border border-gray-300 rounded-lg px-4 py-2">
                                {{ genre.name }}
                            </div>
                            <p v-if="!event.genres?.length" class="text-gray-500 font-normal">
                                No genres set
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shows & Tickets Section -->
            <div class="mt-12 border-t pt-8">
                <h2 class="text-4xl">Shows:</h2>
                <div class="grid grid-cols-2 gap-8 mt-12">
                    <div>
                        <p class="font-semibold">Date Range</p>
                        <p class="text-gray-500 font-normal">{{ formatDateRange(event.shows) }}</p>
                    </div>
                    <div>
                        <p class="font-semibold">Number of Shows</p>
                        <p class="text-gray-500 font-normal">
                            {{ event.showtype === 'a' ? 'Always available' : `${event.shows?.length || 0} shows` }}
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold">Tickets</p>
                        <div class="flex flex-row gap-2">
                            <template v-if="event.shows?.length && event.shows[0].tickets?.length">
                                <p v-for="ticket in event.shows[0].tickets" 
                                   :key="ticket.id" 
                                   class="text-gray-500 font-normal border border-gray-300 p-4 rounded-lg">
                                    {{ ticket.name }}<br> 
                                    ${{ ticket.ticket_price }}<br>
                                    <span class="text-1xl">{{ ticket.description }}</span>
                                </p>
                            </template>
                            <p v-else class="text-gray-500 font-normal">
                                No tickets set
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advisories Section -->
            <div class="mt-12 border-t pt-8">
                <h2 class="text-4xl">Advisories:</h2>
                <div class="grid grid-cols-2 gap-8 mt-4">
                    <!-- Content Advisories -->
                    <div>
                        <p class="font-semibold">Content Advisories</p>
                        <ul class="list-disc ml-4 mt-2 text-gray-500 font-normal">
                            <li v-for="advisory in event.content_advisories" 
                                :key="advisory.id">
                                {{ advisory.name }}
                            </li>
                        </ul>
                        <div v-if="event.advisories?.sexual" class="mt-4">
                            <p class="font-semibold">Sexual Content Description:</p>
                            <p class="text-gray-500 font-normal">{{ event.advisories.sexualDescription }}</p>
                        </div>
                    </div>

                    <!-- Mobility Advisories -->
                    <div>
                        <p class="font-semibold">Mobility Advisories</p>
                        <ul class="list-disc ml-4 mt-2 text-gray-500 font-normal">
                            <li v-for="advisory in event.mobility_advisories" 
                                :key="advisory.id">
                                {{ advisory.name }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-8 mt-4">
                    <!-- Interaction Advisories -->
                    <div class="mt-8">
                        <p class="font-semibold">Interaction Level</p>
                        <p class="text-gray-500 font-normal">{{ event.interactive_level.name }}</p>
                    </div>

                    <!-- Audience Role -->
                    <div class="mt-8">
                        <p class="font-semibold">Audience Role</p>
                        <p class="text-gray-500 font-normal">{{ event.advisories.audience }}</p>
                    </div>
                </div>
            </div>

            <!-- Video Section -->
            <div v-if="event.video" class="mt-12 border-t pt-8">
                <h2 class="text-4xl">Video:</h2>
                <div class="relative aspect-video w-full max-w-3xl mt-4">
                    <iframe
                        :src="`https://www.youtube.com/embed/${event.video}`"
                        class="absolute top-0 left-0 w-full h-full rounded-xl"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                </div>
            </div>

            <!-- Organizer Section -->
            <div class="mt-12 border-t pt-8">
                <h2 class="text-4xl">Organizer:</h2>
                <div class="flex items-start gap-6 mt-4">
                    <div class="flex-shrink-0">
                        <img v-if="event.organizer?.images?.[0]?.thumb_image_path" 
                             :src="imageUrl + event.organizer.images[0].thumb_image_path"
                             :alt="event.organizer?.name"
                             class="w-24 h-24 rounded-full object-cover"
                        />
                        <div v-else 
                             class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400 text-2xl">
                                {{ event.organizer?.name?.charAt(0)?.toUpperCase() || 'O' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-2xl font-semibold">{{ event.organizer?.name }}</h3>
                        <p class="text-gray-500 font-normal mt-2 whitespace-pre-line">
                            {{ event.organizer?.description }}
                        </p>
                    </div>
                </div>
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

const formatEmbargoDate = (date) => {
    return moment(date).format('MMM D, YYYY');
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
