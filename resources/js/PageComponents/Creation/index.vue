<template>
	<div class="flex justify-center md:justify-end">
		<div class="px-8 lg-air:px-16 2xl-air:px-32 w-full max-w-screen-5xl mt-8 md:mt-20 pb-40 md:pb-0">
			<!-- Header Section -->
			<div class="w-full flex flex-col">
				<!-- Organizer Name and Create Button -->
				<!-- One row: the organization, the pill tabs in the middle, New event on the right. -->
				<div class="w-full flex flex-col lg:flex-row lg:items-center gap-8 mb-16 md:py-8 md:p-0 rounded-2xl">
					<!-- On desktop the organization lives in the top bar instead. -->
					<div v-if="!orgInNav" class="flex flex-col lg:flex-1 min-w-0">
						<a :href="`/organizers/${organizer.slug}`" class="group flex flex-row items-center gap-8">
							<div class="flex gap-4">
								<a :href="`/organizers/${organizer.slug}/edit`" class="cursor-pointer">
									<div class="rounded-full bg-gray-100 w-20 h-20 md:w-16 md:h-16 flex items-center justify-center hover:bg-gray-200">
										<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
										</svg>
									</div>
								</a>
							</div>			
							<h2 class="text-4xl md:text-5xl font-medium group-hover:underline break-words hyphens-auto leading-tight">{{organizer.name}}</h2>
						</a>
						
						<!-- Switch Organization Link -->
						<a v-if="user.hasCreatedOrganizers" 
						   href="/teams" 
						   class="text-xl md:mt-4 text-gray-500 hover:text-black ml-28 md:ml-28">
						   Switch Organization
						</a>
					</div>
				<!-- Airbnb's pill tabs: Events, then Show Runs when there are some. -->
				<div class="contents">
					<!-- Only events: a plain title, no single lonely pill. -->
					<h2 v-if="tabs.length === 1" class="self-start text-4xl md:text-5xl font-semibold text-[#222222]">Events</h2>
					<nav v-else class="flex self-start bg-[#f7f7f7] rounded-full p-[0.4rem] overflow-x-auto max-w-full [scrollbar-width:none]">
						<button
							v-for="item in tabs"
							:key="item.id"
							type="button"
							@click="setTab(item.id)"
							class="px-8 py-3 rounded-full text-xl font-medium whitespace-nowrap transition-all"
							:class="tab === item.id ? 'bg-white text-black shadow-[0_2px_8px_rgba(0,0,0,0.12)]' : 'text-neutral-500 hover:text-black'"
						>
							{{ item.name }}
						</button>
					</nav>
				</div>

				</div>

				<!-- Show runs: long rows, laid out like the events list below. -->
				<div v-if="tab === 'shows'" class="w-full mb-20">
					<div class="hidden md:grid gap-8 py-4 h-36 items-center md:grid-cols-[16rem_30%_auto_auto_auto]">
						<h5 class="font-medium">Show runs</h5>
						<div></div>
						<h5 class="font-medium">Status</h5>
						<h5 class="font-medium text-center">Runs</h5>
					</div>
					<a
						v-for="item in showCards"
						:key="item.key"
						:href="item.href"
						class="group relative grid gap-8 px-0 md:px-4 p-4 items-center hover:bg-gray-100 rounded-2xl grid-cols-[4rem_auto] md:grid-cols-[16rem_30%_auto_auto_auto]"
					>
						<div class="h-24 overflow-hidden rounded-2xl bg-gray-300" :class="item.shape">
							<img v-if="item.image" :src="`${imageUrl}${item.image}`" :alt="item.name" class="h-full w-full object-cover" />
						</div>
						<div>
							<p class="text-3xl md:text-2xl font-medium break-words hyphens-auto">{{ item.name }}</p>
							<p v-if="item.note" class="text-lg md:text-md leading-4 text-gray-500">{{ item.note }}</p>
							<div class="flex md:hidden flex-row items-center mt-2">
								<div :class="item.statusColor" class="w-4 h-4 rounded-full"></div>
								<p class="text-lg font-medium text-gray-500 ml-4">{{ item.status }}</p>
							</div>
						</div>
						<div class="hidden md:flex flex-row items-center">
							<div :class="item.statusColor" class="w-4 h-4 rounded-full"></div>
							<p class="text-lg font-medium text-gray-500 ml-4">{{ item.status }}</p>
						</div>
						<div class="hidden md:flex justify-center items-center">
							<div v-if="item.count" class="bg-gray-100 px-4 py-2 rounded-full font-medium">{{ item.count }}</div>
							<div v-else class="text-gray-400">-</div>
						</div>
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
							 class="hidden md:block md:absolute right-6 top-1/2 transform -translate-y-1/2 w-8 h-8 opacity-0 group-hover:opacity-100"
							 stroke-width="3">
							<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
						</svg>
					</a>
				</div>

				<!-- Events: the list as before, with its filters -->
				<div v-if="tab === 'events'" class="hidden md:flex gap-4 flex-wrap mb-4">
					<button 
						v-for="filter in filters" 
						:key="filter.id"
						@click="selectFilter(filter.id)"
						:class="[
							'px-6 py-3 rounded-full border text-lg font-medium transition-all',
							currentFilter === filter.id 
								? 'border-black bg-black text-white' 
								: 'border-gray-300 text-gray-700 hover:border-gray-400'
						]"
					>
						{{ filter.name }} ({{ getFilteredEvents(filter.id).length }})
					</button>
					<!-- New event: a grey round plus after the filters; hovering opens it to show its words. -->
					<button
						type="button"
						@click="createNewEvent"
						:disabled="creatingEvent"
						aria-label="New event"
						class="group inline-flex items-center h-[4.4rem] min-w-[4.4rem] px-[1.1rem] rounded-full bg-[#f2f2f2] text-[#222222] hover:bg-[#ebebeb] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
					>
						<svg class="w-[2.2rem] h-[2.2rem] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.6" d="M12 4v16M4 12h16"/></svg>
						<span
							class="overflow-hidden whitespace-nowrap text-lg font-medium transition-all duration-200 max-w-0 group-hover:max-w-[14rem] group-hover:pl-3 group-hover:pr-2 group-focus-visible:max-w-[14rem] group-focus-visible:pl-3 group-focus-visible:pr-2"
							:class="creatingEvent ? '!max-w-[14rem] pl-3 pr-2' : ''"
						>{{ creatingEvent ? 'Creating…' : 'New event' }}</span>
					</button>
				</div>

				<!-- Mobile dropdown -->
				<div v-if="tab === 'events'" class="flex justify-between items-center md:hidden mb-16">
					<h3 class="text-4xl md:text-5xl font-black leading-tight">Your Listings</h3>
					<div class="flex gap-4">
						<div @click="createNewEvent" :aria-busy="creatingEvent" class="flex" :class="creatingEvent ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'">
							<div class="rounded-full bg-gray-100 flex items-center w-16 h-16 md:w-auto justify-center text-4xl font-light hover:bg-gray-200">
								<div class="px-0 md:px-6 text-1xl font-medium flex flex-row items-center gap-2"><span class="text-4xl">+</span> <span class="hidden md:inline">{{ creatingEvent ? 'Creating…' : 'Add Event' }}</span></div>
							</div>
						</div>
						<div @click="isOpen = !isOpen" class="cursor-pointer flex">
							<div class="rounded-full bg-gray-100 w-16 h-16 md:w-12 md:h-12 flex items-center justify-center hover:bg-gray-200">
								<svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
									 class="fixed inset-0 bg-black bg-opacity-50 flex items-end justify-center z-[401]"
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
			<div v-if="tab === 'events'" class="w-full hidden md:block">
				<div class="grid gap-8 py-4 h-36 items-center grid-cols-[8rem_auto] md:grid-cols-[16rem_30%_auto_auto_auto]">
					<h5 class="font-medium">Events</h5>
					<div class="hidden md:block"></div>
					<h5 class="font-medium">Status</h5>
					<h5 class="font-medium text-center">Ticket Clicks</h5>
				</div>
			</div>

			<!-- Event List: each part of a row opens its own step (picture, name, dates);
			     the dots open the old pop-up (duplicate, delete), the arrow the live page.
			     A deleted event only opens the pop-up, where it can be restored. -->
			<div class="w-full" v-if="tab === 'events' && organizer && filteredEvents.length">
				<div v-for="event in filteredEvents" :key="event.id">
					<div class="relative grid gap-8 px-0 md:px-4 p-4 items-center rounded-2xl grid-cols-[4rem_auto_auto] md:grid-cols-[16rem_30%_auto_auto_auto]">
						<button type="button" class="aspect-[3/4] h-24 overflow-hidden rounded-2xl hover:opacity-80 transition-opacity" :aria-label="`Edit the picture of ${event.name || 'this event'}`" @click="rowAction(event, 'Images')">
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
						</button>
						<div class="min-w-0">
							<button type="button" class="flex items-center gap-2 text-left hover:underline" @click="rowAction(event, 'Name')">
								<span class="text-3xl md:text-2xl font-medium break-words hyphens-auto">{{ event.name || 'No name yet' }}</span>
								<svg v-if="event.status === 'r'" 
									 class="h-5 w-5 text-gray-500 flex-shrink-0" 
									 fill="none" 
									 viewBox="0 0 24 24" 
									 stroke="currentColor">
									<path stroke-linecap="round" 
										  stroke-linejoin="round" 
										  stroke-width="2" 
										  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
								</svg>
							</button>
							<p class="text-lg md:text-md leading-4 text-gray-500">last edited: {{ cleanDate(event.updated_at) }}</p>
							<button type="button" class="flex md:hidden flex-row items-center mt-2 hover:underline" @click="rowAction(event, 'status')">
								<div :class="getStatusInfo(event, cleanDate).color" class="w-4 h-4 rounded-full"></div>
								<p class="text-lg font-medium text-gray-500 ml-4">{{ getStatusInfo(event, cleanDate).progress }}</p>
							</button>
						</div>
						<button type="button" class="hidden md:flex flex-row items-center justify-self-start hover:underline" @click="rowAction(event, 'status')">
							<div :class="getStatusInfo(event, cleanDate).color" class="w-4 h-4 rounded-full"></div>
							<p class="text-lg font-medium text-gray-500 ml-4">{{ getStatusInfo(event, cleanDate).progress }}</p>
						</button>
						<div class="hidden md:flex justify-center items-center">
							<div v-if="event.total_clicks" class="flex items-center justify-center">
								<div class="flex items-center gap-2 bg-gray-100 px-4 py-2 rounded-full">
									<span class="font-medium">{{ event.total_clicks }}</span>
								</div>
							</div>
							<div v-else class="text-gray-400">-</div>
						</div>
						<div class="flex items-center justify-end gap-2">
							<button type="button" aria-label="More: duplicate or delete" class="w-12 h-12 rounded-full flex items-center justify-center hover:bg-gray-100" @click="openModal(event)">
								<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
							</button>
							<a v-if="isEventPublished(event) && !event.is_deleted" :href="event.public_url || `/events/${event.slug}`" aria-label="See it on the site" class="w-12 h-12 rounded-full flex items-center justify-center hover:bg-gray-100">
								<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
							</a>
							<span v-else class="w-12 h-12" aria-hidden="true"></span>
						</div>
					</div>
				</div>
			</div>

			<div v-else-if="tab === 'events' && organizer && organizer.events && !filteredEvents.length" class="py-12">
				<p class="text-xl text-neutral-500">No events here yet.</p>
			</div>
		</div>

		<!-- Modal -->
		<div v-if="selectedEvent" 
			 class="fixed inset-0 bg-black bg-opacity-50 flex md:items-center justify-center z-[401]"
			 @click="closeModal">
			<div class="bg-white w-full rounded-t-2xl md:rounded-3xl md:p-20 p-8 md:max-w-3xl relative mt-auto md:mt-0 md:mx-4 max-h-[90vh] supports-[height:100dvh]:max-h-[90dvh] overflow-y-auto" 
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
							@click="duplicateEvent(selectedEvent)"
							:disabled="duplicatingEvent || deletingEvent"
							class="w-full text-lg px-6 py-3 border border-black rounded-xl hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
							{{ duplicatingEvent ? 'Duplicating…' : 'Duplicate Event' }}
						</button>
						<button 
							@click="confirmRemoveEvent(selectedEvent)"
							:disabled="deletingEvent || duplicatingEvent"
							class="w-full text-lg px-6 py-3 border border-red-500 text-red-500 rounded-xl hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed">
							{{ deletingEvent ? 'Deleting…' : 'Delete Event' }}
						</button>
					</div>
				</template>
			</div>
		</div>

		<!-- Submission Modal -->
		<div v-if="showSubmissionModal" 
			 class="fixed inset-0 bg-black bg-opacity-50 flex md:items-center justify-center z-[401]"
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

		<!-- Locked Event Modal — a published run that ended more than 90 days ago (Event::EDIT_WINDOW_DAYS) -->
		<teleport to="body">
			<div v-if="lockedEvent"
				 class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-[401]"
				 @click="closeLockedModal">
				<div class="bg-white w-full md:max-w-[56rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl relative max-h-[92vh] overflow-y-auto px-[2.4rem] pt-[5.2rem] pb-[3.2rem] md:px-[4rem] md:pt-[5.6rem] md:pb-[4rem]"
					 role="dialog"
					 aria-modal="true"
					 aria-labelledby="locked-event-title"
					 @click.stop>
					<button
						@click="closeLockedModal"
						aria-label="Close"
						class="absolute top-[1.6rem] right-[1.6rem] w-[4rem] h-[4rem] rounded-full flex items-center justify-center hover:bg-gray-100">
						<svg class="w-[2rem] h-[2rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
						</svg>
					</button>

					<div class="text-center">
						<p class="text-[1.6rem] font-medium text-gray-500">{{ lockedEventEnded }}</p>

						<div class="flex justify-center my-[2.8rem]">
							<img v-if="lockedEventImage"
								 :src="lockedEventImage"
								 :alt="lockedEvent.name || ''"
								 class="h-[15rem] w-[11.25rem] object-cover rounded-[1.2rem] shadow-lg">
							<div v-else class="h-[15rem] w-[11.25rem] rounded-[1.2rem] bg-gray-100 flex items-center justify-center">
								<svg class="w-[4.8rem] h-[4.8rem] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
									<rect x="3" y="5" width="18" height="16" rx="2" />
									<path stroke-linecap="round" d="M3 10h18M8 3v4M16 3v4" />
									<path stroke-linecap="round" stroke-linejoin="round" d="M12 13v3l2 1" />
								</svg>
							</div>
						</div>

						<h3 id="locked-event-title" class="text-[2.6rem] md:text-[3rem] leading-[1.15] tracking-tight font-bold break-words hyphens-auto">
							Trying to edit an event that is over 90 days old?
						</h3>

						<p class="mt-[1.6rem] text-[1.6rem] text-gray-500 leading-[1.5] max-w-[42rem] mx-auto">
							If you wish to add new dates to your event, please duplicate it. The copy asks to join the same project, so you only need the project's title.
						</p>

						<button
							@click="duplicateLockedEvent"
							:disabled="duplicatingLocked"
							class="mt-[3.2rem] w-full md:w-auto h-[4.8rem] px-[3.2rem] bg-black text-white text-[1.6rem] font-semibold rounded-[1.2rem] hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
							{{ duplicatingLocked ? 'Duplicating…' : 'Duplicate event' }}
						</button>

						<p class="mt-[3.2rem] text-[1.1rem] text-gray-500 leading-[1.6]">
							This helps us preserve the historical record of past events. Need help? Contact us at: <a href="mailto:support@everythingimmersive.com" class="underline hover:text-black">support@everythingimmersive.com</a>
						</p>
					</div>
				</div>
			</div>
		</teleport>

	</div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import dayjs from 'dayjs';
import { RiCloseCircleFill } from "@remixicon/vue";
import axios from 'axios';

const props = defineProps({
	organizer: Object,
	orgInNav: { type: Boolean, default: false },
	user: Object,
});

const emit = defineEmits(['eventDeleted']);

// Keep in step with Event::MAX_UNPUBLISHED_EVENTS (app/Models/Event.php) — this
// is only a pre-check for a nicer message; the server enforces the real cap.
const MAX_UNPUBLISHED_EVENTS = 10;
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const selectedEvent = ref(null);
const showSubmissionModal = ref(false);
const submittedEventName = ref('');
// Active / Past / Deleted is remembered in this browser, so coming back lands on the same list.
const FILTER_KEY = 'hosting.eventFilter';
const savedFilter = (() => {
	try {
		return localStorage.getItem(FILTER_KEY);
	} catch {
		return null;
	}
})();
const currentFilter = ref(['active', 'past', 'archived'].includes(savedFilter) ? savedFilter : 'active');
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

	// /hosting/event/{slug}/edit sends a locked event here, from wherever
	// the Edit link was (the public event page, an inbox link, a bookmark),
	// so the explanation lives in one place. The event may belong to one of
	// the user's OTHER organizers and so be absent from this list; the modal
	// only needs the slug to offer the duplicate.
	const locked = urlParams.get('locked');
	if (locked) {
		lockedEvent.value = props.organizer?.events?.find((e) => e.slug === locked) || { slug: locked };
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
	window.location.href = event.public_url || `/events/${event.slug}`;
};

const editEvent = (event) => {

	if (event.status === 'r' && !props.user?.isModerator) {
		alert('This event is under review and cannot be edited.');
		return;
	}

	if (event.isEditLocked) {
		openLockedModal(event);
		return;
	}

	// Get the status info to determine the correct view
	const statusInfo = getStatusInfo(event, cleanDate);

	const editUrl = statusInfo.url || `/hosting/event/${event.slug}/edit`;
	
	window.location.href = editUrl;
};

// A row's parts each open their own step. Deleted events only open the pop-up
// (to restore); the same guards as Edit apply (under review, 90-day lock).
const rowAction = (event, view) => {
	if (event.is_deleted) {
		openModal(event);
		return;
	}
	if (event.status === 'r' && !props.user?.isModerator) {
		alert('This event is under review and cannot be edited.');
		return;
	}
	if (event.isEditLocked) {
		openLockedModal(event);
		return;
	}
	if (view === 'status') {
		// The status says what is next (a missing step, or new dates): go there.
		const info = getStatusInfo(event, cleanDate);
		window.location.href = info.url || `/hosting/event/${event.slug}/edit?view=${info.view || 'Dates'}`;
		return;
	}
	window.location.href = `/hosting/event/${event.slug}/edit?view=${view}`;
};

const confirmRemoveEvent = async (event) => {
	if (event.isEditLocked) {
		openLockedModal(event);
		return;
	}

	if (deletingEvent.value) return;

	if (confirm('Are you sure you want to remove this event?')) {
		// Stays on after success: the page reloads, so the button can't be pressed twice meanwhile.
		deletingEvent.value = true;
		try {
			await axios.delete(`/hosting/event/${event.slug}`);
			closeModal(); // Close the modal
			
			// Emit event to parent component
			emit('eventDeleted', event.id);
			
			// Force a page reload
			window.location.reload();
		} catch (error) {
			deletingEvent.value = false;
			console.error('Error deleting event:', error);
			alert('Failed to delete event. Please try again.');
		}
	}
};

const eventPassed = (event) => {
	return event.status === 'p' && !event.isShowing;
};

const getStatusInfo = (event, cleanDateFn) => {

	if (eventPassed(event)) {
		// Straight to Dates rather than the wizard's first step: a finished
		// run is editable again, and adding dates is why an organizer comes
		// back to one. Landing on "What's your event called?" buries that.
		return { color: 'bg-slate-200', progress: 'event has no more dates', url: `/hosting/event/${event.slug}/edit?view=Dates` };
	}

	// Map status codes to steps and their info
	// Status represents the LAST completed step, so we show the NEXT step to complete
	// This should align with the STEP_MAP in Creation/Core/index.vue
	const statusInfo = {
		'0': { color: 'bg-orange-400', progress: 'add event type', view: 'EventType' },
		'1': { color: 'bg-orange-400', progress: 'add event name', view: 'Name' },
		'2': { color: 'bg-orange-400', progress: 'add category', view: 'Category' },
		'3': { color: 'bg-orange-400', progress: 'add genres', view: 'Genres' },
		'4': { color: 'bg-orange-400', progress: 'add physical/remote location', view: event.attendance_type_id === 1 || event.hasLocation ? 'Location' : 'Remote' },
		'5': { color: 'bg-orange-400', progress: 'add description', view: 'Description' },
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
	
	// If there's a direct URL, use it, otherwise construct the edit URL with query parameter
	if (info.url) {
		return info;
	}

	const result = {
		...info,
		url: `/hosting/event/${event.slug}/edit?view=${info.view}`
	};
	return result;
};

const cleanDate = (data) => dayjs(data).format("MMM DD, YYYY");

const closeSubmissionModal = () => {
	showSubmissionModal.value = false;
	// Clean up the URL
	window.history.replaceState({}, document.title, window.location.pathname);
};


// Busy flags for the dashboard actions, so a double click can't make two
// drafts, two copies or two delete requests. On success the page navigates
// away, so they stay on; they reset only on error.
const creatingEvent = ref(false);
const duplicatingEvent = ref(false);
const deletingEvent = ref(false);

const createNewEvent = async () => {
	if (creatingEvent.value) return;

	// Check if organizer is at the unpublished-events cap (bypass for admins)
	const unpublishedCount = props.organizer.events.filter(
		event => !['p', 'e'].includes(event.status) && !event.is_deleted
	).length;

	if (unpublishedCount >= MAX_UNPUBLISHED_EVENTS && !props.user.isAdmin) {
		alert(`You can only have ${MAX_UNPUBLISHED_EVENTS} unpublished events at a time. Please publish or delete existing events before creating new ones.`);
		return;
	}

	creatingEvent.value = true;

	try {
		const response = await axios.post(`/hosting/event/create`, {
			organizer_id: props.organizer.id
		});
		
		// Redirect to the new event's edit page
		window.location.href = `/hosting/event/${response.data.event.slug}/edit`;
	} catch (error) {
		creatingEvent.value = false;
		console.error('Error creating event:', error);
		alert('Failed to create new event. Please try again.');
	}
};

// The top bar menu's "Create new event" card asks this page to do it.
window.addEventListener('hosting-create-event', createNewEvent);
onUnmounted(() => window.removeEventListener('hosting-create-event', createNewEvent));

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

const selectFilter = (id) => {
	currentFilter.value = id;
	try {
		localStorage.setItem(FILTER_KEY, id);
	} catch {
		// Private windows can refuse; the list still switches.
	}
	isOpen.value = false;
};

// Airbnb-style pill tabs. Show runs only appear when there are some; venues live under the menu's Your venues.
const shows = computed(() => props.organizer?.kept_projects ?? []);
const tabs = computed(() => [
	{ id: 'events', name: 'Events' },
	...(shows.value.length ? [{ id: 'shows', name: 'Show Runs' }] : []),
]);

// The tab is kept in the address, so going back or reloading lands on it.
const tabFromUrl = new URLSearchParams(window.location.search).get('tab');
const tab = ref(tabs.value.some((item) => item.id === tabFromUrl) ? tabFromUrl : 'events');

const setTab = (id) => {
	tab.value = id;
	const url = new URL(window.location);
	url.searchParams.set('tab', id);
	window.history.replaceState({}, '', url);
};


const showCards = computed(() => shows.value.map((show) => ({
	key: `show-${show.id}`,
	name: show.name,
	note: show.pending_requests ? `${show.pending_requests} asking to join` : null,
	image: show.largeImagePath,
	shape: 'aspect-[3/4]',
	status: show.pending_requests ? 'Needs your answer' : 'Live',
	statusColor: show.pending_requests ? 'bg-yellow-300' : 'bg-green-500',
	count: show.runs_count,
	href: `/hosting/projects/${show.slug}/edit`,
})));


// The event an organizer just tried to edit or delete after its run ended
// more than 90 days ago. isEditLocked is the SERVER's answer, appended to
// the event JSON (Event::isEditLockedFor — it already exempts moderators
// and admins), so this never disagrees with the refusal behind it; the
// modal just gets there first and offers the duplicate.
const lockedEvent = ref(null);
const duplicatingLocked = ref(false);


// The event's own poster is the hero, like the rows above use; the redirect
// case may only know the slug, and then a placeholder stands in.
const lockedEventImage = computed(() => {
	const event = lockedEvent.value;
	if (!event) return null;
	if (event.images?.length) return `${imageUrl}${event.images[0].large_image_path}`;
	if (event.thumbImagePath) return `${imageUrl}${event.thumbImagePath}`;
	return null;
});

const lockedEventEnded = computed(() =>
	lockedEvent.value?.closingDate ? `Ended ${cleanDate(lockedEvent.value.closingDate)}` : 'Past event'
);

const openLockedModal = (event) => {
	lockedEvent.value = event;
	closeModal();
};

const closeLockedModal = () => {
	lockedEvent.value = null;

	// Arrived by redirect? Drop the ?locked= so a reload doesn't reopen this.
	if (new URLSearchParams(window.location.search).has('locked')) {
		window.history.replaceState({}, document.title, window.location.pathname);
	}
};

// The answer to "so what do I do instead?": Event::duplicate() copies the
// description, category, genres and advisories but drops the dates, tickets
// and closing date — the shape of a new run. No confirm(): this modal IS the
// prompt. The copy opens in the wizard, where the organizer renames it.
const duplicateLockedEvent = async () => {
	const event = lockedEvent.value;
	if (!event) return;

	duplicatingLocked.value = true;

	try {
		// The slug may have come straight from ?locked=, so it is encoded here.
		const response = await axios.post(`/api/events/${encodeURIComponent(event.slug)}/duplicate`);
		window.location.href = `/hosting/event/${response.data.event.slug}/edit`;
	} catch (error) {
		duplicatingLocked.value = false;
		if (error.response?.status === 422) {
			alert(error.response.data.message);
		} else {
			console.error('Error duplicating event:', error);
			alert('Failed to duplicate event. Please try again.');
		}
	}
};

const duplicateEvent = async (event) => {
	if (duplicatingEvent.value) return;

	if (confirm('Are you sure you want to duplicate this event?')) {
		duplicatingEvent.value = true;
		try {
			const response = await axios.post(`/api/events/${event.slug}/duplicate`);
			closeModal(); // Close the modal first
			window.location.href = `/hosting/event/${response.data.event.slug}/edit`;
		} catch (error) {
			duplicatingEvent.value = false;
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