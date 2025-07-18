<template>
	<div class="flex justify-center md:justify-end">
		<div class="px-8 lg-air:px-16 2xl-air:px-32 w-full max-w-screen-5xl mt-20">
			<!-- Header Section -->
			<div class="w-full flex flex-col">
				<!-- Organizer Name and Create Button -->
				<div class="w-full flex items-center justify-between mb-20 md:py-8 md:p-0 rounded-2xl">
					<div class="flex flex-col">
						<a :href="`/organizers/${organizer.slug}`" class="group flex flex-row items-center gap-8">
							<div class="flex gap-4">
								<a :href="`/organizers/${organizer.slug}/edit`" class="cursor-pointer">
									<div class="rounded-full bg-gray-100 w-16 h-16 flex items-center justify-center hover:bg-gray-200">
										<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
										</svg>
									</div>
								</a>
							</div>			
							<h2 class="text-3xl md:text-5xl font-medium group-hover:underline break-words hyphens-auto leading-tight">{{organizer.name}}</h2>
						</a>
						
						<!-- Switch Organization Link -->
						<a v-if="user.hasCreatedOrganizers" 
						   href="/teams" 
						   class="text-xl mt-4 text-gray-500 hover:text-black ml-28 md:ml-28">
						   Switch Organization
						</a>
					</div>
				</div>

				<!-- Filter Buttons -->
				<div class="hidden md:flex gap-6 flex-wrap">
					<div @click="createNewEvent" class="cursor-pointer">
						<div class="rounded-full bg-gray-100 h-20 flex items-center justify-center text-5xl font-light hover:bg-gray-200">
							<div class="px-6 py-3 text-lg font-medium flex flex-row items-center gap-2"><span class="text-5xl">+</span> Add Event</div>
						</div>
					</div>
					<button 
						v-for="filter in filters" 
						:key="filter.id"
						@click="currentFilter = filter.id"
						:class="[
							'px-6 py-3 rounded-full border text-lg font-medium transition-all',
							currentFilter === filter.id 
								? 'border-black bg-black text-white' 
								: 'border-gray-300 text-gray-700 hover:border-gray-400'
						]"
					>
						{{ filter.name }} ({{ getFilteredEvents(filter.id).length }})
					</button>
				</div>

				<!-- Mobile dropdown -->
				<div class="flex justify-between items-center md:hidden mb-16">
					<h3 class="text-5xl leading-tight">Your <br>Listings</h3>
					<div class="flex gap-4">
						<div @click="createNewEvent" class="cursor-pointer flex">
							<div class="rounded-full bg-gray-100 flex items-center h-16 justify-center text-4xl font-light hover:bg-gray-200">
								<div class="px-6 text-lg font-medium flex flex-row items-center gap-2"><span class="text-4xl">+</span> Add Event</div>
							</div>
						</div>
						<div @click="isOpen = !isOpen" class="cursor-pointer flex">
							<div class="rounded-full bg-gray-100 w-16 h-16 flex items-center justify-center hover:bg-gray-200">
								<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</div>
						</div>
					</div>
				</div>

				<div class="md:hidden">
					<div class="flex gap-4 items-center">
						<div class="relative flex-1">
							<!-- Dropdown menu -->
							<teleport to="body">
								<div v-if="isOpen" 
									 class="fixed inset-0 bg-black bg-opacity-50 flex items-end justify-center z-50"
									 @click="isOpen = false">
									<div class="bg-white w-full rounded-t-2xl p-8 max-h-[80vh] overflow-y-auto" 
										 @click.stop>
										<!-- Modal Header -->
										<div class="flex justify-between items-center mb-6">
											<h3 class="text-2xl font-medium">Filter Events</h3>
											<button @click="isOpen = false">
												<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
													<path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
												</svg>
											</button>
										</div>
										
										<!-- Filter Options -->
										<div class="space-y-4">
											<button 
												v-for="filter in filters" 
												:key="filter.id"
												@click="selectFilter(filter.id)"
												class="w-full p-4 text-left rounded-xl transition-colors"
												:class="{ 
													'bg-black text-white': currentFilter === filter.id,
													'hover:bg-gray-100': currentFilter !== filter.id
												}"
											>
												<div class="flex justify-between items-center">
													<span class="text-lg">{{ filter.name }}</span>
													<span class="text-sm" :class="{ 'opacity-75': currentFilter === filter.id }">
														{{ getFilteredEvents(filter.id).length }}
													</span>
												</div>
											</button>
										</div>
									</div>
								</div>
							</teleport>
						</div>
					</div>
				</div>
			</div>

			<!-- Event List Headers -->
			<div class="w-full hidden md:block">
				<div class="grid gap-8 py-4 h-36 items-center grid-cols-[8rem_auto] md:grid-cols-[16rem_30%_auto_auto_auto]">
					<h5 class="font-medium">Events</h5>
					<div class="hidden md:block"></div>
					<h5 class="font-medium">Status</h5>
					<h5 class="font-medium text-center">Ticket Clicks</h5>
				</div>
			</div>

			<!-- Event List -->
			<div class="w-full" v-if="organizer && filteredEvents.length">
				<div v-for="event in filteredEvents"
					 :key="event.id"
					 @click="openModal(event)"
					 class="block cursor-pointer">
					<div class="group relative grid grid-cols-2 md:grid-cols-4 gap-8 px-0 md:px-4 p-4 items-center hover:bg-gray-100 rounded-2xl grid-cols-[4rem_auto] md:grid-cols-[16rem_30%_auto_auto_auto]">
						<div class="aspect-[3/4] h-24 overflow-hidden">
							<template v-if="event.images?.length > 0">
								<picture>
									<source :srcset="`${imageUrl}${event.images[0].large_image_path}`" type="image/webp">
									<img :src="`${imageUrl}${event.images[0].large_image_path}`"
										 :alt="`${event.name} Immersive Event`"
										 class="h-full w-full object-cover rounded-2xl">
								</picture>
							</template>
							<template v-else-if="event.thumbImagePath">
								<picture>
									<source :srcset="`${imageUrl}${event.thumbImagePath}`" type="image/webp">
									<img :src="`${imageUrl}${event.thumbImagePath.slice(0, -4)}jpg`"
										 :alt="`${event.name} Immersive Event`"
										 class="h-full w-full object-cover rounded-2xl break-words hyphens-auto">
								</picture>
							</template>
							<template v-else>
								<div class="h-full w-full rounded-2xl bg-gray-300"></div>
							</template>
						</div>
						<div class="">
							<div class="flex items-center gap-2">
								<p class="text-2xl font-medium break-words hyphens-auto">{{ event.name }}</p>
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
						<div class="hidden md:flex justify-center items-center">
							<div v-if="event.total_clicks" class="flex items-center justify-center">
								<div class="flex items-center gap-2 bg-gray-100 px-4 py-2 rounded-full">
									<span class="font-medium">{{ event.total_clicks }}</span>
								</div>
							</div>
							<div v-else class="text-gray-400">-</div>
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

			<div v-else-if="organizer && organizer.events && !filteredEvents.length" 
				 class="py-12">
				<button 
					@click="createNewEvent"
					class="inline-flex items-center px-8 py-4 border border-black rounded-full hover:bg-gray-800 hover:text-white gap-2"
				>
					<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
					</svg>
					Create New Event
				</button>
			</div>
		</div>

		<!-- Modal -->
		<div v-if="selectedEvent" 
			 class="fixed inset-0 bg-black bg-opacity-50 flex md:items-center justify-center z-50"
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

				<!-- For deleted events, show a simplified modal -->
				<template v-if="selectedEvent.is_deleted">
					<div class="text-center py-16">
						<h2 class="text-3xl md:text-4xl font-bold mb-8 break-words hyphens-auto">{{ selectedEvent.name }}</h2>
						<p class="text-xl text-gray-600 mb-12">This event has been deleted.</p>
					</div>
				</template>
				
				<!-- For regular events, show the full modal content -->
				<template v-else>
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
					<h2 class="text-3xl md:text-4xl font-bold mb-8 md:mb-12 text-center break-words hyphens-auto">{{ selectedEvent.name }}</h2>

					<!-- Event Statistics (if published) -->
					<div v-if="isEventPublished(selectedEvent) && selectedEvent.total_clicks" class="mb-8 text-center">
						<div class="inline-flex items-center bg-gray-100 px-6 py-3 rounded-lg">
							<div class="flex flex-col items-center">
								<span class="text-2xl font-bold">{{ selectedEvent.total_clicks }}</span>
								<span class="text-gray-500 text-sm">Ticket Link Clicks</span>
							</div>
						</div>
					</div>

					<!-- Action Buttons -->
					<div class="flex flex-col md:flex-row gap-2 md:gap-6 mb-12 md:mb-0">
						<button 
							v-if="isEventPublished(selectedEvent)"
							@click="viewEvent(selectedEvent)"
							class="w-full text-lg px-6 py-3 bg-black text-white rounded-xl hover:bg-gray-800">
							View Event
						</button>
						<button 
							v-if="selectedEvent.status !== 'r'"
							@click="editEvent(selectedEvent)"
							class="w-full text-lg px-6 py-3 border border-black rounded-xl hover:bg-gray-100">
							Edit Event
						</button>
						<button 
							v-if="selectedEvent.status !== 'r'"
							@click="duplicateEvent(selectedEvent)"
							class="w-full text-lg px-6 py-3 border border-black rounded-xl hover:bg-gray-100">
							Duplicate Event
						</button>
						<button 
							@click="confirmRemoveEvent(selectedEvent)"
							class="w-full text-lg px-6 py-3 border border-red-500 text-red-500 rounded-xl hover:bg-red-50">
							Delete Event
						</button>
					</div>
				</template>
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
					<h3 class="text-2xl md:text-4xl mb-4 break-words hyphens-auto">Thanks for submitting <span class="font-bold">{{ submittedEventName }}</span>!</h3>
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

		<!-- Name Change Modal -->
		<teleport to="body">
			<div v-if="showNameChangeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
				<div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
					<!-- Header -->
					<div class="p-8 pb-6">
						<h2 class="text-2xl font-bold mb-2">Name Change Request</h2>
						<p class="text-gray-500 font-normal">Submit a request to change your event's name</p>
					</div>

					<!-- Content -->
					<div class="p-8 overflow-y-auto flex-1">
						<div class="space-y-6">
							<p class="text-gray-600">
								Changing the event name requires admin approval. Once submitted:
							</p>
							<ul class="text-gray-600 list-disc ml-5">
								<li>The current name will remain until approved</li>
								<li>The name field will be locked until a decision is made</li>
							</ul>
							<div class="mt-8">
								<p class="text-gray-500 font-normal mb-4">New Name</p>
								<p class="text-4xl font-bold">
									{{ pendingNameChange }}
								</p>
							</div>
						</div>
					</div>

					<!-- Footer -->
					<div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
						<div class="flex justify-end space-x-4">
							<button 
								@click="cancelNameChange"
								class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
							>
								Cancel
							</button>
							<button 
								@click="confirmNameChange"
								class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
							>
								Submit Request
							</button>
						</div>
					</div>
				</div>
			</div>
		</teleport>
	</div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import dayjs from 'dayjs';
import { RiCloseCircleFill } from "@remixicon/vue";
import axios from 'axios';

const props = defineProps({
	organizer: Object,
	user: Object,
});

const emit = defineEmits(['eventDeleted']);

const MAX_UNPUBLISHED_EVENTS = 5;
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const selectedEvent = ref(null);
const showSubmissionModal = ref(false);
const submittedEventName = ref('');
const currentFilter = ref('active');
const filters = [
	{ id: 'active', name: 'Active Events' },
	{ id: 'past', name: 'Past Events' },
	{ id: 'archived', name: 'Deleted' }
];

const isOpen = ref(false);

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
	console.log('editEvent called with:', {
		event: event,
		status: event.status,
		notes: event.notes
	});

	if (event.status === 'r') {
		alert('This event is under review and cannot be edited.');
		return;
	}
	
	// Get the status info to determine the correct view
	const statusInfo = getStatusInfo(event, cleanDate);
	console.log('statusInfo:', statusInfo);

	const editUrl = statusInfo.url || `/hosting/event/${event.slug}/edit`;
	console.log('Final editUrl:', editUrl);
	
	window.location.href = editUrl;
};

const confirmRemoveEvent = async (event) => {
	if (confirm('Are you sure you want to remove this event?')) {
		try {
			await axios.delete(`/hosting/event/${event.slug}`);
			closeModal(); // Close the modal
			
			// Emit event to parent component
			emit('eventDeleted', event.id);
			
			// Force a page reload
			window.location.reload();
		} catch (error) {
			console.error('Error deleting event:', error);
			alert('Failed to delete event. Please try again.');
		}
	}
};

const eventPassed = (event) => {
	return event.status === 'p' && !event.isShowing;
};

const getStatusInfo = (event, cleanDateFn) => {
	console.log('getStatusInfo called with:', {
		event: event,
		status: event.status,
		notes: event.notes
	});

	if (eventPassed(event)) {
		return { color: 'bg-slate-200', progress: 'event has no more dates', url: `/hosting/event/${event.slug}/edit` };
	}

	// Map status codes to steps and their info
	const statusInfo = {
		'0': { color: 'bg-orange-400', progress: 'add event type', view: 'EventType' },
		'1': { color: 'bg-orange-400', progress: 'add category', view: 'Category' },
		'2': { color: 'bg-orange-400', progress: 'add genres', view: 'Genres' },
		'3': { color: 'bg-orange-400', progress: 'add physical/remote location', view: event.attendance_type_id === 1 || event.hasLocation ? 'Location' : 'Remote' },
		'4': { color: 'bg-orange-400', progress: 'add description', view: 'Description' },
		'5': { color: 'bg-orange-400', progress: 'add event name', view: 'Name' },
		'6': { color: 'bg-orange-400', progress: 'add show dates', view: 'Dates' },
		'7': { color: 'bg-orange-400', progress: 'add pricing', view: 'Tickets' },
		'8': { color: 'bg-orange-400', progress: 'add images', view: 'Images' },
		'9': { color: 'bg-orange-400', progress: 'add contact advisories', view: 'Advisories' },
		'A': { color: 'bg-orange-400', progress: 'add content advisories', view: 'Content' },
		'B': { color: 'bg-orange-400', progress: 'add mobility advisories', view: 'Mobility' },
		'C': { color: 'bg-yellow-300', progress: 'submit event', view: 'Review' },
		'p': { color: 'bg-green-500', progress: 'approved', url: `/hosting/event/${event.slug}/edit` },
		'e': { color: 'bg-green-500', progress: `event live at ${cleanDateFn(event.embargo_date)}`, url: `/hosting/event/${event.slug}/edit` },
		'r': { color: 'bg-black', progress: 'under moderator review', url: null },
		'n': { color: 'bg-red-500', progress: 'revise and resubmit', url: `/hosting/event/${event.slug}/edit` },
	};

	const info = statusInfo[event.status] || { color: 'bg-white', progress: '-', view: 'EventType' };
	console.log('Selected status info:', info);
	
	// If there's a direct URL, use it, otherwise construct the edit URL with query parameter
	if (info.url) {
		console.log('Using direct URL:', info.url);
		return info;
	}

	const result = {
		...info,
		url: `/hosting/event/${event.slug}/edit?view=${info.view}`
	};
	console.log('Constructed URL result:', result);
	return result;
};

const cleanDate = (data) => dayjs(data).format("MMM DD, YYYY");

const closeSubmissionModal = () => {
	showSubmissionModal.value = false;
	// Clean up the URL
	window.history.replaceState({}, document.title, window.location.pathname);
};


const createNewEvent = async () => {
	// Check if organizer has 5 or more unpublished events (bypass for admins)
	const unpublishedCount = props.organizer.events.filter(
		event => !['p', 'e'].includes(event.status)
	).length;

	if (unpublishedCount >= MAX_UNPUBLISHED_EVENTS && !props.user.isAdmin) {
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

// Helper function to check if an event is past
const isPastEvent = (event) => {
	// Don't show deleted events in past
	if (event.is_deleted) return false;
	
	// Check if the event is published and either:
	// 1. Has no more show dates (isShowing is false)
	// 2. Or its end_date is in the past
	return event.status === 'p' && (
		!event.isShowing || 
		(event.end_date && new Date(event.end_date) < new Date())
	);
};

// Helper function to check if an event is active
const isActive = (event) => {
	// Don't show deleted events in active
	if (event.is_deleted) return false;
	
	// Currently playing events
	const isCurrentlyPlaying = event.status === 'p' && event.isShowing;
	// Embargoed events
	const isEmbargoed = event.status === 'e';
	// In progress events (drafts, under review, etc.)
	const isInProgress = !['p', 'e'].includes(event.status);
	// Make sure it's not a past event
	const notPast = !isPastEvent(event);
	
	return (isCurrentlyPlaying || isEmbargoed || isInProgress) && notPast;
};

// Function to get filtered events based on current filter
const getFilteredEvents = (filterId) => {
	if (!props.organizer?.events) return [];
	
	return props.organizer.events.filter(event => {
		switch (filterId) {
			case 'active':
				return isActive(event);
			case 'past':
				return isPastEvent(event);
			case 'archived':
				return event.is_deleted;
			default:
				return true;
		}
	});
};

// Computed property for filtered events
const filteredEvents = computed(() => {
	return getFilteredEvents(currentFilter.value);
});

const showNameChangeModal = ref(false);
const pendingNameChange = ref('');
const originalName = ref('');

const handleNameInput = () => {
	$v.value.event.name.$touch();
	if (event.name?.length > 100) {
		event.name = event.name.slice(0, 100);
	}

	// If event is published or embargoed, show modal
	if (['p', 'e'].includes(event.status)) {
		pendingNameChange.value = event.name;
		originalName.value = event.name;
		showNameChangeModal.value = true;
	}
};

const confirmNameChange = async () => {
	try {
		// Make API call to submit name change request
		await axios.post(`/events/${event.slug}/name-change`, {
			requested_name: pendingNameChange.value,
			current_name: originalName.value
		});

		showNameChangeModal.value = false;
		// Optionally show success message
		alert('Name change request submitted successfully');
	} catch (error) {
		console.error('Error submitting name change:', error);
		alert('Failed to submit name change request. Please try again.');
	}
};

const cancelNameChange = () => {
	showNameChangeModal.value = false;
	event.name = originalName.value;
	$v.value.event.name.$reset();
};

const selectFilter = (id) => {
	currentFilter.value = id;
	isOpen.value = false;
};

const duplicateEvent = async (event) => {
	if (event.status === 'r') {
		alert('This event is under moderator review and cannot be duplicated.');
		return;
	}
	
	if (confirm('Are you sure you want to duplicate this event?')) {
		try {
			const response = await axios.post(`/api/events/${event.slug}/duplicate`);
			closeModal(); // Close the modal first
			window.location.href = `/hosting/event/${response.data.event.slug}/edit`;
		} catch (error) {
			if (error.response?.status === 422) {
				alert(error.response.data.message);
			} else {
				console.error('Error duplicating event:', error);
				alert('Failed to duplicate event. Please try again.');
			}
		}
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