<template>
	<div class="flex justify-end">
		<div class="px-8 md:px-32 w-full ml-[-2rem] mt-12">
			<div class="w-full flex items-center justify-between">
				<a :href="`/organizers/${organizer.slug}`">
					<h2 class="font-medium hover:underline">{{organizer.name}}</h2>
				</a>
				<div @click="createNewEvent" class="cursor-pointer">
					<div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center text-5xl font-light hover:bg-gray-200">
						+
					</div>
				</div>
			</div>
			<div class="w-full">
			    <div class="grid gap-8 py-4 h-36 items-center grid-cols-[8rem_auto] md:grid-cols-[16rem_30%_auto_auto]">
			       	<h5 class="font-medium">Events</h5>
			       	<div class="hidden md:block"></div>
			       	<h5 class="font-medium">Status</h5>
			    </div>
			</div>
			<div class="w-full" v-if="organizer && organizer.events">
			    <div v-for="event in organizer.events"
			         :key="event.id"
			         @click="openModal(event)"
			         class="block cursor-pointer">
			        <div class="group relative grid grid-cols-2 md:grid-cols-4 gap-8 py-4 items-center hover:bg-gray-100 rounded-2xl grid-cols-[4rem_auto] md:grid-cols-[4rem_30%_auto_auto]">
			            <div>
			                <template v-if="event.images?.length > 0">
			                    <picture>
			                        <source :srcset="`${imageUrl}${event.images[0].large_image_path}`" type="image/webp">
			                        <img :src="`${imageUrl}${event.images[0].large_image_path}`"
			                             :alt="`${event.name} Immersive Event`"
			                             class="h-16 w-full object-cover rounded-2xl">
			                    </picture>
			                </template>
			                <template v-else-if="event.thumbImagePath">
			                    <picture>
			                        <source :srcset="`${imageUrl}${event.thumbImagePath}`" type="image/webp">
			                        <img :src="`${imageUrl}${event.thumbImagePath.slice(0, -4)}jpg`"
			                             :alt="`${event.name} Immersive Event`"
			                             class="h-24 w-full object-cover rounded-2xl">
			                    </picture>
			                </template>
			                <template v-else>
			                    <div class="h-24 w-full rounded-2xl bg-gray-300"></div>
			                </template>
			            </div>
			            <div class="">
			                <div class="flex items-center gap-2">
			                    <p class="text-2xl font-medium">{{ event.name }}</p>
			                    <svg v-if="event.status === 'r'" 
			                         class="h-5 w-5 text-gray-500" 
			                         fill="none" 
			                         viewBox="0 0 24 24" 
			                         stroke="currentColor">
			                        <path stroke-linecap="round" 
			                              stroke-linejoin="round" 
			                              stroke-width="2" 
			                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
			                    </svg>
			                </div>
			                <p class="text-md leading-4 text-gray-500">last edited: {{ cleanDate(event.updated_at) }}</p>
			            </div>
			            <div class="hidden md:flex flex-row items-center">
			            	<div :class="getStatusInfo(event, cleanDate).color" class="w-4 h-4 rounded-full"></div>
            				<p class="text-lg font-medium text-gray-500 ml-4">{{ getStatusInfo(event, cleanDate).progress }}</p>
			            </div>
			            <!-- SVG Icon, visible only on hover -->
			            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
					         class="hidden md:absolute right-6 top-1/2 transform -translate-y-1/2 w-8 h-8 opacity-0 group-hover:opacity-100"
					         stroke-width="3">
					        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
					    </svg>
			        </div>
			    </div>
			</div>
		</div>

		<!-- Modal -->
		<div v-if="selectedEvent" 
		     class="fixed inset-0 bg-black bg-opacity-50 flex md:items-center justify-center z-[2000]"
		     @click="closeModal">
			<div class="bg-white w-full rounded-t-2xl md:rounded-3xl md:p-20 p-8 md:max-w-3xl relative mt-auto md:mt-0 md:mx-4" 
			     @click.stop>
				<!-- Close Button -->
				<div 
					@click="closeModal" 
					class="absolute top-6 right-6 cursor-pointer bg-white"
				>
					<component :is="RiCloseCircleFill" />
				</div>

				<!-- Event Image -->
				<div class="w-full flex justify-center mb-8">
					<div class="w-1/3 max-w-md aspect-[3/4] rounded-2xl overflow-hidden mt-16">
						<template v-if="selectedEvent.images?.length > 0">
							<picture>
								<source :srcset="`${imageUrl}${selectedEvent.images[0].large_image_path}`" type="image/webp">
								<img :src="`${imageUrl}${selectedEvent.images[0].large_image_path}`"
								     :alt="`${selectedEvent.name} Immersive Event`"
								     class="w-full h-full object-cover">
							</picture>
						</template>
						<template v-else-if="selectedEvent.thumbImagePath">
							<picture>
								<source :srcset="`${imageUrl}${selectedEvent.thumbImagePath}`" type="image/webp">
								<img :src="`${imageUrl}${selectedEvent.thumbImagePath.slice(0, -4)}jpg`"
								     :alt="`${selectedEvent.name} Immersive Event`"
								     class="w-full h-full object-cover">
							</picture>
						</template>
						<template v-else>
							<div class="w-full h-full bg-gray-200"></div>
						</template>
					</div>
				</div>

				<!-- Event Name -->
				<h2 class="text-3xl md:text-4xl font-bold mb-8 md:mb-12 text-center">{{ selectedEvent.name }}</h2>

				<!-- Action Buttons -->
				<div class="flex flex-col md:flex-row gap-4 md:gap-6 mb-12 md:mb-0">
					<button 
						v-if="isEventPublished(selectedEvent)"
						@click="viewEvent(selectedEvent)"
						class="w-full px-8 py-4 bg-black text-white rounded-xl hover:bg-gray-800">
						View Event
					</button>
					<button 
						v-if="selectedEvent.status !== 'r'"
						@click="editEvent(selectedEvent)"
						class="w-full px-8 py-4 border border-black rounded-xl hover:bg-gray-100">
						Edit Event
					</button>
					<button 
						@click="confirmRemoveEvent(selectedEvent)"
						class="w-full px-8 py-4 border border-red-500 text-red-500 rounded-xl hover:bg-red-50">
						Delete Event
					</button>
				</div>
			</div>
		</div>

		<!-- Submission Modal -->
		<div v-if="showSubmissionModal" 
		     class="fixed inset-0 bg-black bg-opacity-50 flex md:items-center justify-center z-50"
		     @click="closeSubmissionModal">
			<div class="bg-white w-full rounded-t-2xl md:rounded-3xl md:p-20 p-8 md:max-w-3xl relative mt-auto md:mt-0 md:mx-4" 
			     @click.stop>
				<div class="text-center">
					<div class="mb-8">
						<svg class="mx-auto h-12 md:h-16 w-12 md:w-16 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
					</div>
					<h3 class="text-2xl md:text-3xl font-bold mb-4">Thanks for submitting {{ submittedEventName }}!</h3>
					<p class="text-gray-600 mb-8">Your event will be reviewed in the next few days.</p>
					<button 
						@click="closeSubmissionModal" 
						class="w-full md:w-auto px-8 py-4 bg-black text-white rounded-xl hover:bg-gray-800"
					>
						Close
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import dayjs from 'dayjs';
import { RiCloseCircleFill } from "@remixicon/vue";
import axios from 'axios';

const props = defineProps({
    organizer: Object,
    user: Object,
});


const MAX_UNPUBLISHED_EVENTS = 5;
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const selectedEvent = ref(null);
const showSubmissionModal = ref(false);
const submittedEventName = ref('');

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const submitted = urlParams.get('submitted');
    if (submitted) {
        submittedEventName.value = submitted;
        showSubmissionModal.value = true;
    }
});

// Modal functions
const openModal = (event) => {
    selectedEvent.value = event;
};

const closeModal = () => {
    selectedEvent.value = null;
};

const isEventPublished = (event) => {
    return event.status === 'p' || event.status === 'e';
};

const viewEvent = (event) => {
    window.location.href = `/events/${event.slug}`;
};

const editEvent = (event) => {
    if (event.status === 'r') {
        alert('This event is under review and cannot be edited.');
        return;
    }
    window.location.href = `/hosting/event/${event.slug}/edit`;
};

const confirmRemoveEvent = async (event) => {
    if (confirm('Are you sure you want to remove this event?')) {
        try {
            await axios.delete(`/hosting/event/${event.slug}`);
            closeModal(); // Close the modal
            
            // Remove the event from the organizer's events array
            const index = props.organizer.events.findIndex(e => e.id === event.id);
            if (index > -1) {
                props.organizer.events.splice(index, 1);
            }
        } catch (error) {
            console.error('Error deleting event:', error);
            alert('Failed to delete event. Please try again.');
        }
    }
};

const onSubmit = async () => {

};

const eventPassed = (event) => {
    return event.status === 'p' && !event.isShowing;
};

const getStatusInfo = (event, cleanDateFn) => {
    if (eventPassed(event)) {
        return { color: 'bg-slate-200', progress: 'event has no more dates', url: `/create/${event.slug}/shows` };
    }
    const statusInfo = {
        '0': { color: 'bg-orange-400', progress: 'add event title', url: `/create/${event.slug}/title` },
        '1': { color: 'bg-orange-400', progress: 'add physical/remote location', url: `/create/${event.slug}/location` },
        '2': { color: 'bg-orange-400', progress: 'add category', url: `/create/${event.slug}/category` },
        '3': { color: 'bg-orange-400', progress: 'add show dates', url: `/create/${event.slug}/shows` },
        '4': { color: 'bg-orange-400', progress: 'add pricing', url: `/create/${event.slug}/tickets` },
        '5': { color: 'bg-orange-400', progress: 'add show description', url: `/create/${event.slug}/description` },
        '6': { color: 'bg-orange-400', progress: 'add advisories', url: `/create/${event.slug}/advisories` },
        '7': { color: 'bg-orange-400', progress: 'add image', url: `/create/${event.slug}/images` },
        '8': { color: 'bg-yellow-300', progress: 'submit event', url: `/create/${event.slug}/review` },
        'p': { color: 'bg-green-500', progress: 'approved', url: `/events/${event.slug}` },
        'e': { color: 'bg-green-500', progress: `event live at ${cleanDateFn(event.embargo_date)}`, url: `/create/${event.slug}/title` },
        'r': { color: 'bg-black', progress: 'under moderator review', url: null },
        'n': { color: 'bg-red-500', progress: 'revise and resubmit', url: `/create/${event.slug}/title` }
    };
    return statusInfo[event.status] || { color: 'bg-white', progress: '-', url: `/create/${event.slug}/title` };
};

const cleanDate = (data) => dayjs(data).format("MMM DD, YYYY");

const closeSubmissionModal = () => {
    showSubmissionModal.value = false;
    // Clean up the URL
    window.history.replaceState({}, document.title, window.location.pathname);
};


const createNewEvent = async () => {
    // Check if organizer has 5 or more unpublished events
    const unpublishedCount = props.organizer.events.filter(
        event => !['p', 'e'].includes(event.status)
    ).length;

    if (unpublishedCount >= MAX_UNPUBLISHED_EVENTS) {
        alert('You can only have 5 unpublished events at a time. Please publish or delete existing events before creating new ones.');
        return;
    }

    try {
        const response = await axios.post(`/hosting/event/create`, {
            organizer_id: props.organizer.id
        });
        
        // Redirect to the new event's edit page
        window.location.href = `/hosting/event/${response.data.event.slug}/edit`;
    } catch (error) {
        console.error('Error creating event:', error);
        alert('Failed to create new event. Please try again.');
    }
};
</script>

<style scoped>
/* Optional: Add transitions for smooth modal appearance */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>