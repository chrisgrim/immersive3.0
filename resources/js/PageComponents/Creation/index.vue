<template>
	<div class="flex justify-center md:justify-end">
		<!-- Room for the side panel on the right while events are picked (Airbnb's "Edit 1 listing"). -->
		<div class="px-8 md:px-16 2xl-air:px-32 w-full max-w-screen-5xl mt-8 md:mt-20 pb-40 md:pb-0 transition-[padding] duration-200" :class="picking ? 'md:!pr-[calc(max(42rem,33.333vw)+4rem)]' : ''">
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
				<div class="flex items-center justify-between gap-4 md:contents">
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
					<!-- On a phone New event sits beside the title (Airbnb's round plus). -->
					<button type="button" :disabled="creatingEvent" aria-label="New event" class="md:hidden flex-shrink-0 w-[4.4rem] h-[4.4rem] rounded-full bg-[#f2f2f2] text-[#222222] flex items-center justify-center disabled:opacity-50" data-new-event-phone @click="createNewEvent">
						<svg width="22" height="22" class="w-[2.2rem] h-[2.2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.6" d="M12 4v16M4 12h16"/></svg>
					</button>
				</div>

				</div>

				<!-- Events: the list as before, with its filters. On a phone the same pills
				     in one row that scrolls sideways, as Airbnb does, with New event last. -->
				<div v-if="tab === 'events' && !noEventsYet" class="flex gap-4 mb-8 md:mb-4 overflow-x-auto md:flex-wrap md:overflow-visible -mx-8 px-8 md:mx-0 md:px-0 [scrollbar-width:none]">
					<button 
						v-for="filter in filters" 
						:key="filter.id"
						@click="selectFilter(filter.id)"
						:class="[
							'flex-shrink-0 whitespace-nowrap px-6 py-3 rounded-full border text-lg font-medium transition-all',
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
						class="group flex-shrink-0 hidden md:inline-flex items-center h-[4.4rem] min-w-[4.4rem] px-[1.1rem] rounded-full bg-[#f2f2f2] text-[#222222] hover:bg-[#ebebeb] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
					>
						<svg class="w-[2.2rem] h-[2.2rem] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.6" d="M12 4v16M4 12h16"/></svg>
						<span
							class="overflow-hidden whitespace-nowrap text-lg font-medium transition-all duration-200 max-w-0 group-hover:max-w-[14rem] group-hover:pl-3 group-hover:pr-2 group-focus-visible:max-w-[14rem] group-focus-visible:pl-3 group-focus-visible:pr-2"
							:class="creatingEvent ? '!max-w-[14rem] pl-3 pr-2' : ''"
						>{{ creatingEvent ? 'Creating…' : 'New event' }}</span>
					</button>
				</div>

			</div>

			<!-- Event List Headers -->
			<div v-if="tab === 'events' && !noEventsYet" class="w-full hidden md:block">
				<div class="relative grid gap-8 py-4 md:-ml-[4rem] md:pl-[4rem] md:pr-4 h-36 items-center grid-cols-[8rem_auto] md:grid-cols-[6.4rem_36%_minmax(0,1fr)_12rem_16rem]">
					<!-- While any are picked: pick all of this list, or none (a dash when only some). -->
					<button v-if="picking" type="button" class="absolute left-[0.4rem] top-1/2 -translate-y-1/2 w-[3.2rem] h-[3.2rem] flex items-center justify-center" :aria-label="allPicked ? 'Pick none' : 'Pick all'" data-pick-all @click="allPicked ? clearPicked() : pickAll()">
						<span class="w-[2.4rem] h-[2.4rem] rounded-[0.6rem] bg-[#222222] flex items-center justify-center">
							<svg v-if="allPicked" class="w-[1.6rem] h-[1.6rem] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
							<span v-else class="w-[1.2rem] h-[0.25rem] rounded bg-white"></span>
						</span>
					</button>
					<h5 class="font-medium">Events</h5>
					<div class="hidden md:block"></div>
					<h5 class="font-medium">Status</h5>
					<h5 class="font-medium text-center">Ticket Clicks</h5>
				</div>
			</div>

			<!-- Event List. A show run with two or more runs here folds into one group:
			     stacked pictures, its name, how many runs and how many are on, and its
			     own Edit button; open it to see each run as a normal row. Everything
			     else is a plain row, a lone run with a small "Run of" tag. -->
			<div class="w-full" v-if="tab === 'events' && organizer && filteredEvents.length">
				<template v-for="row in listRows" :key="row.key">
					<EventRow
						v-if="row.type === 'event'"
						:event="row.event"
						:status="getStatusInfo(row.event, cleanDate)"
						:edited="cleanDate(row.event.updated_at)"
						:run-of="runOf(row.event)"
						:selected="picked.has(row.event.id)"
						:picking="picking"
						@open="openRow(row.event)"
						@pick="togglePick(row.event)"
						@edit="editEvent(row.event)"
						@duplicate="duplicateEvent(row.event)"
						@delete="confirmRemoveEvent(row.event)"
					/>

					<section v-else class="my-4 md:-ml-[4rem] rounded-[2.4rem] border border-neutral-200 transition-shadow" :class="isOpen(row) ? 'shadow-[0_6px_20px_rgba(0,0,0,0.1)]' : 'shadow-[0_2px_10px_rgba(0,0,0,0.06)] hover:shadow-[0_4px_16px_rgba(0,0,0,0.1)]'">
						<!-- Click anywhere on the card's top to open or close its runs. -->
						<div class="grid gap-8 items-center px-4 md:pl-[4rem] md:pr-4 py-4 cursor-pointer rounded-[2.4rem] grid-cols-[6rem_auto_auto] md:grid-cols-[6.4rem_36%_minmax(0,1fr)_12rem_16rem]" data-show-run-head @click="toggleGroup(row)">
							<!-- Stacked pictures of its runs, like an Airbnb wishlist cover -->
							<button type="button" class="relative h-24 md:h-[8rem] w-[6rem] md:w-[6.4rem]" :aria-label="`Show the runs of ${row.project.name}`">
								<span
									v-for="(picture, index) in row.pictures"
									:key="index"
									class="absolute top-0 h-24 md:h-[7.2rem] aspect-[3/4] rounded-2xl overflow-hidden border-2 border-white bg-gray-300 shadow-[0_2px_6px_rgba(0,0,0,0.12)]"
									:style="{ left: `${index * 0.6}rem`, zIndex: 3 - index, transform: `rotate(${(index - 1) * 3}deg)` }"
								>
									<img v-if="picture" :src="picture" alt="" class="h-full w-full object-cover" />
								</span>
							</button>

							<div class="min-w-0">
								<button type="button" class="text-left text-3xl md:text-2xl font-semibold text-[#222222] hover:underline break-words hyphens-auto">{{ row.project.name }}<span v-if="keptById[row.project.id]?.renaming" class="ml-[0.6rem] font-normal text-[#6a6a6a]" data-renaming-tag>(pending change)</span><span class="ml-3 align-middle relative -top-[0.2rem] inline-flex px-[0.8rem] py-[0.2rem] rounded-[0.6rem] bg-[#222222] text-white text-[1.1rem] leading-[1.6rem] font-semibold uppercase tracking-[0.04em] whitespace-nowrap" data-show-run-tag>Show run</span></button>
								<p class="text-lg md:text-md text-gray-500"><template v-if="row.events.length - row.joining">{{ row.events.length - row.joining }} {{ row.events.length - row.joining === 1 ? 'run' : 'runs' }}<span v-if="row.joining"> · </span></template><span v-if="row.joining">{{ row.joining }} waiting to join</span><span v-if="row.places"> · {{ row.places }}</span></p>
								<span v-if="row.waiting" class="inline-flex mt-2 px-3 py-1 rounded-full bg-[#fff4e5] text-md font-medium text-[#b45309]">{{ row.waiting }} asking to join</span>
							</div>

							<div class="hidden md:flex flex-row items-center">
								<div class="w-4 h-4 rounded-full" :class="row.live ? 'bg-green-500' : 'bg-slate-200'"></div>
								<p class="text-lg font-medium text-gray-500 ml-4">{{ row.live ? `${row.live} on now` : 'none on now' }}</p>
							</div>

							<div class="hidden md:flex justify-center items-center">
								<div v-if="row.clicks" class="bg-gray-100 px-4 py-2 rounded-full font-medium">{{ row.clicks }}</div>
								<div v-else class="text-gray-400">-</div>
							</div>

							<div class="flex items-center justify-end gap-2">
								<!-- Round grey buttons like New event: the words slide out on hover. -->
								<!-- Add a run: copies the newest run (already asking to join this show run) and opens it on Dates. -->
								<button type="button" :disabled="duplicatingEvent" aria-label="Add a run" class="hidden md:inline-flex flex-shrink-0 group items-center h-[4.4rem] min-w-[4.4rem] px-[1.1rem] rounded-full bg-[#f2f2f2] text-[#222222] hover:bg-[#ebebeb] transition-colors disabled:opacity-50" @click.stop="addRun(row)">
									<svg class="w-[2.2rem] h-[2.2rem] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.6" d="M12 4v16M4 12h16"/></svg>
									<span class="overflow-hidden whitespace-nowrap text-lg font-medium transition-all duration-200 max-w-0 group-hover:max-w-[12rem] group-hover:pl-3 group-hover:pr-2 group-focus-visible:max-w-[12rem] group-focus-visible:pl-3" :class="duplicatingEvent ? '!max-w-[12rem] pl-3 pr-2' : ''">{{ duplicatingEvent ? 'Adding…' : 'Add a run' }}</span>
								</button>
								<a v-if="row.project.kept" :href="`/hosting/show-runs/${row.project.slug}/edit`" aria-label="Edit show run" @click.stop class="hidden md:inline-flex flex-shrink-0 group items-center h-[4.4rem] min-w-[4.4rem] px-[1.2rem] rounded-full bg-[#f2f2f2] text-[#222222] hover:bg-[#ebebeb] transition-colors">
									<svg class="w-8 h-8 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
									<span class="overflow-hidden whitespace-nowrap text-lg font-medium transition-all duration-200 max-w-0 group-hover:max-w-[14rem] group-hover:pl-3 group-hover:pr-2 group-focus-visible:max-w-[14rem] group-focus-visible:pl-3">Edit show run</span>
								</a>
								<button type="button" :aria-expanded="isOpen(row)" :aria-label="isOpen(row) ? 'Hide its runs' : 'Show its runs'" @click.stop="toggleGroup(row)" class="w-12 h-12 rounded-full flex items-center justify-center hover:bg-gray-100">
									<svg class="w-7 h-7 transition-transform duration-200" :class="isOpen(row) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
								</button>
							</div>
						</div>

						<!-- Its runs, each a normal row, along a thin line -->
						<div v-if="isOpen(row)" class="pb-4 border-t border-neutral-100">
							<div class="md:hidden flex gap-3 mx-4 mt-4 mb-2">
								<button type="button" :disabled="duplicatingEvent" class="inline-flex px-5 py-2.5 rounded-full bg-[#222222] text-white text-lg font-medium disabled:opacity-50" @click="addRun(row)">{{ duplicatingEvent ? 'Adding…' : '+ Add a run' }}</button>
								<a v-if="row.project.kept" :href="`/hosting/show-runs/${row.project.slug}/edit`" class="inline-flex px-5 py-2.5 rounded-full border border-[#222222] text-lg font-medium">Edit show run</a>
							</div>
							<!-- The show's events, set in from the card's sides so they read as
							     inside the show run rather than another show run. -->
							<div class="mx-4 md:mx-[2.4rem] mt-3" data-show-run-events>
							<EventRow
								v-for="event in row.events"
								:key="event.id"
								nested
								:waiting="!event.run_of"
								:event="event"
								:status="getStatusInfo(event, cleanDate)"
								:edited="cleanDate(event.updated_at)"
								:selected="picked.has(event.id)"
								:picking="picking"
								@open="openRow(event)"
								@pick="togglePick(event)"
								@edit="editEvent(event)"
								@duplicate="duplicateEvent(event)"
								@delete="confirmRemoveEvent(event)"
							/>
							</div>
						</div>
					</section>
				</template>
			</div>

			<!-- A brand new organization: one big, clear next step. -->
			<div v-else-if="tab === 'events' && noEventsYet" class="flex flex-col items-center text-center pt-[6rem] md:pt-[10rem] pb-[8rem]" data-first-listing>
				<div class="w-[7.2rem] h-[7.2rem] rounded-full bg-[#f7f7f7] flex items-center justify-center">
					<svg class="w-[3.2rem] h-[3.2rem] text-[#222222]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
						<rect x="3.5" y="5" width="17" height="15" rx="2.5" />
						<path stroke-linecap="round" d="M3.5 10h17M8 3v4M16 3v4M12 13v4M10 15h4" />
					</svg>
				</div>
				<h2 class="mt-[2.4rem] text-[3.2rem] md:text-[4rem] leading-[1.15] font-semibold text-[#222222]">Make your first listing</h2>
				<p class="mt-[1.2rem] max-w-[46rem] text-[1.6rem] md:text-[1.8rem] leading-[2.6rem] text-[#6a6a6a]">
					Tell us about your experience, step by step. You can save and come back to it any time.
				</p>
				<button
					type="button"
					:disabled="creatingEvent"
					class="mt-[3.2rem] inline-flex items-center gap-[1rem] h-[5.6rem] px-[3.2rem] rounded-[1.2rem] bg-[#222222] hover:bg-black text-white text-[1.8rem] font-semibold transition-colors disabled:opacity-50"
					data-first-listing-start
					@click="createNewEvent"
				>
					<svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 4v16M4 12h16"/></svg>
					{{ creatingEvent ? 'Creating…' : 'Create a new listing' }}
				</button>
			</div>

			<div v-else-if="tab === 'events' && organizer && organizer.events && !filteredEvents.length" class="py-12">
				<p class="text-xl text-neutral-500">No events here yet.</p>
			</div>

			<!-- Show runs this company keeps whose runs are all by other companies: still one click away. -->
			<div v-if="tab === 'events' && otherShowRuns.length" class="mt-12 mb-20">
				<h5 class="font-medium mb-4">Show runs you keep</h5>
				<div class="flex flex-wrap gap-3">
					<a v-for="show in otherShowRuns" :key="show.id" :href="`/hosting/show-runs/${show.slug}/edit`" class="inline-flex items-center gap-3 pl-2 pr-5 py-2 rounded-full border border-neutral-200 hover:border-[#222222]">
						<span class="w-10 h-10 rounded-full overflow-hidden bg-gray-200 flex-shrink-0"><img v-if="show.largeImagePath" :src="`${imageUrl}${show.largeImagePath}`" alt="" class="w-full h-full object-cover" /></span>
						<span class="text-lg font-medium">{{ show.name }}</span>
						<span v-if="show.pending_requests" class="text-md text-[#b45309]">{{ show.pending_requests }} asking to join</span>
					</a>
				</div>
			</div>
		</div>

		<!-- Airbnb's listing pop-up, for an event that cannot be edited right now
		     (EI is looking at it, or it is deleted): its picture, name and city,
		     and Delete. Every other row opens the event straight away. -->
		<div v-if="selectedEvent"
			 class="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[401]"
			 @click="closeModal">
			<div class="bg-white w-full md:w-[50rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl relative max-h-[90vh] overflow-y-auto px-[2.4rem] pt-[2.4rem] pb-[3.2rem]"
				 role="dialog" aria-modal="true" aria-labelledby="event-pop-title" data-event-pop
				 @click.stop>
				<button type="button" aria-label="Close" class="w-[4rem] h-[4rem] -ml-[0.8rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" @click="closeModal">
					<svg class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
				</button>
				<div class="flex flex-col items-center text-center mt-2">
					<div class="w-[18.5rem] aspect-square rounded-[1.6rem] overflow-hidden bg-[#ebebeb]">
						<img v-if="eventImage(selectedEvent)" :src="eventImage(selectedEvent)" :alt="selectedEvent.name || ''" class="w-full h-full object-cover">
					</div>
					<h2 id="event-pop-title" class="mt-[2rem] text-[1.8rem] leading-[2.4rem] font-semibold text-[#222222] break-words hyphens-auto">{{ selectedEvent.name || 'No name yet' }}</h2>
					<p v-if="eventPlace(selectedEvent)" class="mt-1 text-[1.6rem] text-[#6a6a6a] font-normal">{{ eventPlace(selectedEvent) }}</p>
					<p v-if="selectedEvent.is_deleted" class="mt-4 text-[1.5rem] text-[#6a6a6a] font-normal">This event has been deleted.</p>
					<p v-else-if="underReview(selectedEvent)" class="mt-4 text-[1.5rem] text-[#6a6a6a] font-normal">A moderator is looking at it. You can edit it again once it is reviewed.</p>
				</div>
				<template v-if="!selectedEvent.is_deleted">
					<button v-if="!underReview(selectedEvent)" type="button" class="mt-[3.2rem] w-full h-[5.2rem] rounded-[1.2rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold" data-pop-edit @click="editEvent(selectedEvent)">Edit event</button>
					<button type="button" :disabled="deletingEvent" class="w-full h-[4.8rem] rounded-[1.2rem] flex items-center justify-center gap-3 text-[1.6rem] font-semibold text-[#222222] hover:bg-[#f7f7f7] disabled:opacity-50" :class="underReview(selectedEvent) ? 'mt-[3.2rem]' : 'mt-[1.6rem]'" data-pop-delete @click="confirmRemoveEvent(selectedEvent)">
						<svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
						{{ deletingEvent ? 'Deleting…' : 'Delete event' }}
					</button>
				</template>
			</div>
		</div>

		<DuplicateAsk v-if="duplicateAsk" :event="duplicateAsk" @close="duplicateAsk = null" />

		<!-- Delete? Airbnb's pop-up: the picture, name and city (or how many), Delete and Cancel. -->
		<div v-if="deleteAsk"
			 class="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[402]"
			 @click="closeDeleteAsk">
			<div class="bg-white w-full md:w-[50rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl relative max-h-[90vh] overflow-y-auto px-[2.4rem] pt-[2.4rem] pb-[3.2rem]"
				 role="alertdialog" aria-modal="true" aria-labelledby="delete-ask-title" data-delete-ask
				 @click.stop>
				<button type="button" aria-label="Close" class="w-[4rem] h-[4rem] -ml-[0.8rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" @click="closeDeleteAsk">
					<svg class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
				</button>
				<div class="flex flex-col items-center text-center mt-2">
					<template v-if="deleteAsk.events.length === 1">
						<div class="w-[18.5rem] aspect-square rounded-[1.6rem] overflow-hidden bg-[#ebebeb]">
							<img v-if="eventImage(deleteAsk.events[0])" :src="eventImage(deleteAsk.events[0])" :alt="deleteAsk.events[0].name || ''" class="w-full h-full object-cover">
						</div>
						<h2 id="delete-ask-title" class="mt-[2rem] text-[1.8rem] leading-[2.4rem] font-semibold text-[#222222] break-words hyphens-auto">Delete {{ deleteAsk.events[0].name || 'this event' }}?</h2>
						<p v-if="eventPlace(deleteAsk.events[0])" class="mt-1 text-[1.6rem] text-[#6a6a6a] font-normal">{{ eventPlace(deleteAsk.events[0]) }}</p>
					</template>
					<template v-else>
						<!-- Several: their pictures fanned out, like a show run's card. -->
						<div class="relative h-[14rem] w-[16rem]">
							<span v-for="(event, index) in deleteAsk.events.slice(0, 3)" :key="event.id" class="absolute top-0 h-[14rem] aspect-[3/4] rounded-[1.6rem] overflow-hidden border-2 border-white bg-[#ebebeb] shadow-[0_2px_6px_rgba(0,0,0,0.12)]" :style="{ left: `${index * 2.4}rem`, zIndex: 3 - index, transform: `rotate(${(index - 1) * 4}deg)` }">
								<img v-if="eventImage(event)" :src="eventImage(event)" alt="" class="h-full w-full object-cover">
							</span>
						</div>
						<h2 id="delete-ask-title" class="mt-[2rem] text-[1.8rem] leading-[2.4rem] font-semibold text-[#222222]">Delete {{ deleteAsk.events.length }} events?</h2>
						<p v-if="deleteAsk.skipped" class="mt-1 text-[1.5rem] text-[#6a6a6a] font-normal">{{ deleteAsk.skipped }} {{ deleteAsk.skipped === 1 ? 'other stays' : 'others stay' }} as {{ deleteAsk.skipped === 1 ? 'it is' : 'they are' }}: deleted already, or ended more than 90 days ago.</p>
					</template>
					<p class="mt-4 text-[1.5rem] text-[#6a6a6a] font-normal">{{ deleteAsk.events.length === 1 ? 'It moves to Deleted.' : 'They move to Deleted.' }}</p>
					<p v-if="deleteError" class="mt-4 text-[1.4rem] text-[#c13515]" role="alert">{{ deleteError }}</p>
				</div>
				<button type="button" :disabled="deletingEvent" class="mt-[3.2rem] w-full h-[5.2rem] rounded-[1.2rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold flex items-center justify-center gap-3 disabled:opacity-50" data-delete-yes @click="doDelete">
					<svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
					{{ deletingEvent ? 'Deleting…' : (deleteAsk.events.length === 1 ? 'Delete event' : `Delete ${deleteAsk.events.length} events`) }}
				</button>
				<button type="button" :disabled="deletingEvent" class="mt-[1.6rem] w-full h-[4.8rem] rounded-[1.2rem] text-[1.6rem] font-semibold text-[#222222] hover:bg-[#f7f7f7] disabled:opacity-50" data-delete-no @click="closeDeleteAsk">Cancel</button>
			</div>
		</div>

		<!-- The side panel, Airbnb's "Edit 1 listing": for one picked event Edit,
		     Duplicate, Add to a show run and Delete; for several, Add to a show run and Delete. -->
		<aside v-if="picking" class="hidden md:flex flex-col fixed right-0 bottom-0 w-[max(42rem,33.333vw)] bg-white border-l border-[#ebebeb] z-[300] overflow-y-auto" :style="{ top: `${panelTop}px` }" aria-label="Picked events" data-pick-panel>
			<div class="flex items-start justify-between gap-6 px-[3.2rem] pt-[3.2rem]">
				<h2 class="text-[2.6rem] leading-[3.2rem] font-semibold text-[#222222]">{{ pickedEvents.length === 1 ? 'Edit 1 event' : `${pickedEvents.length} events picked` }}</h2>
				<div class="flex items-center gap-3 flex-shrink-0">
					<span v-if="pickedEvents.length === 1" class="w-[4rem] h-[5.2rem] rounded-[0.8rem] overflow-hidden bg-[#ebebeb]"><img v-if="eventImage(pickedEvents[0])" :src="eventImage(pickedEvents[0])" alt="" class="w-full h-full object-cover"></span>
					<button type="button" aria-label="Close" class="w-[4rem] h-[4rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" data-pick-close @click="clearPicked">
						<svg class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
					</button>
				</div>
			</div>
			<p v-if="pickedEvents.length === 1" class="px-[3.2rem] mt-1 text-[1.5rem] text-[#6a6a6a] font-normal break-words">{{ pickedEvents[0].name || 'No name yet' }}</p>

			<nav class="mt-[2.4rem] px-[3.2rem] pb-[3.2rem]">
				<template v-if="pickedEvents.length === 1 && !pickedEvents[0].is_deleted">
					<button v-if="!underReview(pickedEvents[0])" type="button" :class="panelRow" data-panel-edit @click="editEvent(pickedEvents[0])">Edit<svg :class="panelChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
					<button type="button" :disabled="duplicatingEvent" :class="panelRow" data-panel-duplicate @click="duplicateEvent(pickedEvents[0])">{{ duplicatingEvent ? 'Duplicating…' : 'Duplicate' }}<svg :class="panelChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>

					<!-- Its show run: the one it is in or asking to join; or, once this company has a
					     show run, Add to one. The first show run starts from Duplicate ("a new run of
					     the same show?") or a new event of the same name. -->
					<div v-if="!(pickedEvents[0].run_of || pickedEvents[0].joining) && hasShowRuns" class="border-b border-[#ebebeb]">
						<button type="button" :class="[panelRow, '!border-b-0']" :aria-expanded="runsOpen" data-panel-add-run @click="toggleRuns(pickedEvents[0])">Add to a show run<svg :class="[panelChevron, runsOpen ? 'rotate-90' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
						<div v-if="runsOpen" class="pb-[1.6rem] -mt-[0.8rem]">
								<p v-if="runChoices === null" class="text-[1.4rem] text-[#6a6a6a] font-normal">Loading…</p>
								<p v-else-if="!runChoices.length" class="text-[1.4rem] text-[#6a6a6a] font-normal">No show runs to join yet.</p>
								<button v-for="choice in runChoices" :key="choice.id" type="button" :disabled="runBusy" class="w-full flex items-center gap-4 py-3 text-left rounded-xl hover:bg-[#f7f7f7] disabled:opacity-50" data-run-choice @click="joinRun([pickedEvents[0]], choice)">
									<span class="w-[4rem] h-[4rem] rounded-xl overflow-hidden bg-[#ebebeb] flex-shrink-0"><img v-if="choice.image" :src="`${imageUrl}${choice.image}`" alt="" class="w-full h-full object-cover"></span>
									<span class="flex-1 min-w-0 text-[1.5rem] font-medium text-[#222222] truncate">{{ choice.name }}</span>
								</button>
								<p v-if="runError" class="mt-2 text-[1.4rem] text-red-600">{{ runError }}</p>
						</div>
					</div>
					<div v-if="pickedEvents[0].run_of || pickedEvents[0].joining" class="border-b border-[#ebebeb] py-[2.4rem] flex items-center justify-between gap-4" data-panel-run>
						<p class="text-[1.6rem] text-[#222222] min-w-0">{{ pickedEvents[0].run_of ? 'Part of' : 'Asking to join' }} <span class="font-semibold">{{ (pickedEvents[0].run_of ?? pickedEvents[0].joining).name }}</span></p>
						<button v-if="removable(pickedEvents[0])" type="button" :disabled="runBusy" class="flex-shrink-0 text-[1.4rem] font-semibold underline disabled:opacity-50" data-panel-leave-run @click="removeFromRuns([pickedEvents[0]])">Remove</button>
					</div>

					<button type="button" :disabled="deletingEvent" :class="[panelRow, '!text-[#c13515]']" data-panel-delete @click="confirmRemoveEvent(pickedEvents[0])">{{ deletingEvent ? 'Deleting…' : 'Delete' }}<svg :class="panelChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
				</template>
				<p v-else-if="pickedEvents.length === 1" class="text-[1.5rem] text-[#6a6a6a] font-normal">This event has been deleted.</p>
				<template v-else>
					<!-- Several: add them all to one show run (those not in one already), once this company has one. -->
					<div v-if="freeEvents.length && hasShowRuns" class="border-b border-[#ebebeb]">
						<button type="button" :class="[panelRow, '!border-b-0']" :aria-expanded="runsOpen" data-panel-add-run-all @click="toggleRuns(freeEvents[0])">Add {{ freeEvents.length === pickedEvents.length ? 'them' : `${freeEvents.length} of them` }} to a show run<svg :class="[panelChevron, runsOpen ? 'rotate-90' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
						<div v-if="runsOpen" class="pb-[1.6rem] -mt-[0.8rem]">
							<p v-if="freeEvents.length < pickedEvents.length" class="mb-2 text-[1.4rem] text-[#6a6a6a] font-normal">The others are in a show run already, asking to join one, or deleted.</p>
								<p v-if="runChoices === null" class="text-[1.4rem] text-[#6a6a6a] font-normal">Loading…</p>
								<p v-else-if="!runChoices.length" class="text-[1.4rem] text-[#6a6a6a] font-normal">No show runs to join yet.</p>
								<button v-for="choice in runChoices" :key="choice.id" type="button" :disabled="runBusy" class="w-full flex items-center gap-4 py-3 text-left rounded-xl hover:bg-[#f7f7f7] disabled:opacity-50" data-run-choice @click="joinRun(freeEvents, choice)">
									<span class="w-[4rem] h-[4rem] rounded-xl overflow-hidden bg-[#ebebeb] flex-shrink-0"><img v-if="choice.image" :src="`${imageUrl}${choice.image}`" alt="" class="w-full h-full object-cover"></span>
									<span class="flex-1 min-w-0 text-[1.5rem] font-medium text-[#222222] truncate">{{ choice.name }}</span>
								</button>
								<p v-if="runError" class="mt-2 text-[1.4rem] text-red-600">{{ runError }}</p>
						</div>
					</div>
					<!-- Several: take them out of their show runs (a request withdrawn, or a run of one this company keeps). -->
					<button v-if="removableEvents.length" type="button" :disabled="runBusy" :class="panelRow" data-panel-remove-runs @click="removeFromRuns(removableEvents)">{{ runBusy ? 'Removing…' : `Remove ${removableEvents.length === 1 ? '1 event' : `${removableEvents.length} events`} from show runs` }}<svg :class="panelChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
					<button type="button" :disabled="deletingEvent" :class="[panelRow, '!text-[#c13515]']" data-panel-delete-all @click="deletePicked">{{ deletingEvent ? 'Deleting…' : `Delete ${pickedEvents.length} events` }}<svg :class="panelChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
				</template>
			</nav>
		</aside>

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
							To add new dates, make a copy. This one stays as the record of its past run.
						</p>

						<RunSwitch v-if="lockedRunOffer" v-model="lockedAsRun" :offer="lockedRunOffer" class="mt-[2.4rem] max-w-[42rem] mx-auto" />
						<p v-if="lockedError" class="mt-4 text-[1.4rem] text-[#c13515]" role="alert" data-locked-error>{{ lockedError }}</p>

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
import { formatEmbargoShort } from '@/composables/dateUtils';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import EventRow from './event-row.vue';
import DuplicateAsk from './duplicate-ask.vue';
import RunSwitch from './run-switch.vue';
import { runOfferFor } from './runOffer';
import dayjs from 'dayjs';
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

const underReview = (event) => event.status === 'r' && !props.user?.isModerator;

// The whole row opens it, as Edit does (unless rows are being picked, below): a published event its edit page, a
// draft where it left off (its next step), the 90-day lock first explaining
// itself. Only what cannot be edited (EI is looking at it, or it is deleted)
// opens the pop-up instead.
const openRow = (event) => {
	// While any are picked (the side panel is open) a click picks or unpicks
	// the row instead, as on Airbnb: unpicking the last closes the panel.
	if (picking.value) {
		togglePick(event);
		return;
	}
	if (event.is_deleted || underReview(event)) {
		openModal(event);
		return;
	}
	editEvent(event);
};

const eventImage = (event) => {
	const path = event?.images?.[0]?.large_image_path ?? event?.thumbImagePath ?? null;
	return path ? `${imageUrl}${path}` : null;
};
const eventPlace = (event) => [event?.location?.city, event?.location?.region].filter(Boolean).join(', ');

// Picked rows (the boxes on the left): the side panel opens while any are.
const picked = ref(new Set());
const picking = computed(() => picked.value.size > 0);
const pickedEvents = computed(() => (props.organizer?.events ?? []).filter((event) => picked.value.has(event.id)));
const togglePick = (event) => {
	const next = new Set(picked.value);
	next.has(event.id) ? next.delete(event.id) : next.add(event.id);
	picked.value = next;
	runsOpen.value = false;
	runChoices.value = null;
	runError.value = '';
};
const clearPicked = () => {
	picked.value = new Set();
};
const allPicked = computed(() => filteredEvents.value.length > 0 && filteredEvents.value.every((event) => picked.value.has(event.id)));
const pickAll = () => {
	picked.value = new Set(filteredEvents.value.map((event) => event.id));
};
const panelRow = 'w-full flex items-center justify-between gap-4 py-[2.4rem] border-b border-[#ebebeb] text-left text-[1.6rem] text-[#222222] hover:underline disabled:opacity-50';
const panelChevron = 'w-[1.8rem] h-[1.8rem] flex-shrink-0 transition-transform';

// The panel starts under the site's top bar, wherever that ends.
const panelTop = ref(80);
const measureTop = () => {
	const bar = document.querySelector('header, nav');
	panelTop.value = Math.max(0, Math.round(bar?.getBoundingClientRect().bottom ?? 80));
};
onMounted(() => {
	measureTop();
	window.addEventListener('resize', measureTop);
});
onUnmounted(() => window.removeEventListener('resize', measureTop));

// Several picked: Delete only, one after another; a locked or deleted one is left alone.
const deletePicked = () => {
	if (deletingEvent.value) return;
	const events = pickedEvents.value.filter((event) => !event.is_deleted && !event.isEditLocked);
	if (!events.length) {
		alert('None of these can be deleted: they are deleted already, or ended more than 90 days ago.');
		return;
	}
	deleteError.value = '';
	deleteAsk.value = { events, skipped: pickedEvents.value.length - events.length };
};

// Add to a show run, from the panel: asks to join (the show's keeper or EI says
// yes). Offered once this company has a show run: one it keeps, or one its
// events are part of. Until then a show run starts from Duplicate.
const hasShowRuns = computed(() => shows.value.length > 0 || (props.organizer?.events ?? []).some((event) => event.run_of));
const runsOpen = ref(false);
const runChoices = ref(null);
const runBusy = ref(false);
const runError = ref('');
const toggleRuns = async (event) => {
	runsOpen.value = !runsOpen.value;
	runError.value = '';
	if (!runsOpen.value || runChoices.value !== null) return;
	try {
		const response = await axios.get(`/hosting/event/${event.slug}/show-runs`);
		runChoices.value = response.data.choices ?? [];
	} catch {
		runChoices.value = [];
		runError.value = 'Could not load the show runs. Please try again.';
	}
};
// Picked events that can still ask: not deleted, not in a show run or asking to join one.
const freeEvents = computed(() => pickedEvents.value.filter((event) => !event.is_deleted && !event.run_of && !event.joining));
// Each asks in turn; if any could ask, the list reloads to show them joining.
const joinRun = async (events, choice) => {
	if (runBusy.value || !events.length) return;
	runBusy.value = true;
	runError.value = '';
	let joined = 0;
	let reason = '';
	for (const event of events) {
		try {
			await axios.post(`/hosting/event/${event.slug}/link-request`, { project_id: choice.id });
			joined += 1;
		} catch (failure) {
			reason = runFailure(failure);
		}
	}
	if (joined) {
		window.location.reload();
		return;
	}
	runBusy.value = false;
	runError.value = reason;
};
const runFailure = (failure) => Object.values(failure.response?.data?.errors ?? {}).flat()[0] ?? failure.response?.data?.message ?? 'That did not work. Please try again.';
// Out of a show run: a request to join is withdrawn; a run of a show run this
// company keeps is taken out of it (as on the show run's own page).
const removable = (event) => {
	if (event.is_deleted) return null;
	if (event.joining) return `/hosting/event/${event.slug}/link-request`;
	if (event.run_of && keptIds.value.has(event.run_of.id)) return `/hosting/show-runs/${event.run_of.slug}/instances/${event.slug}`;
	return null;
};
const removableEvents = computed(() => pickedEvents.value.filter((event) => removable(event)));
const removeFromRuns = async (events) => {
	if (runBusy.value || !events.length) return;
	runBusy.value = true;
	let removed = 0;
	let reason = '';
	for (const event of events) {
		try {
			await axios.delete(removable(event));
			removed += 1;
		} catch (failure) {
			reason = runFailure(failure);
		}
	}
	if (removed) {
		window.location.reload();
		return;
	}
	runBusy.value = false;
	alert(reason);
};

// Asking before a delete is Airbnb's pop-up, not the browser's box: the
// event's picture, name and city (or how many), Delete and Cancel.
const deleteAsk = ref(null); // { events, skipped }
const deleteError = ref('');
const confirmRemoveEvent = (event) => {
	if (event.isEditLocked) {
		openLockedModal(event);
		return;
	}
	if (deletingEvent.value) return;
	closeModal();
	deleteError.value = '';
	deleteAsk.value = { events: [event], skipped: 0 };
};
// Some deleted and some not: say so, and show the list again once it is closed.
let reloadOnClose = false;
const closeDeleteAsk = () => {
	if (deletingEvent.value) return;
	deleteAsk.value = null;
	if (reloadOnClose) window.location.reload();
};
const doDelete = async () => {
	const events = deleteAsk.value?.events ?? [];
	if (!events.length || deletingEvent.value) return;
	// Stays on after success: the page reloads, so it can't be pressed twice meanwhile.
	deletingEvent.value = true;
	deleteError.value = '';
	let failed = 0;
	for (const event of events) {
		try {
			await axios.delete(`/hosting/event/${event.slug}`);
			emit('eventDeleted', event.id);
		} catch {
			failed += 1;
		}
	}
	if (failed) {
		deletingEvent.value = false;
		reloadOnClose = failed < events.length;
		deleteError.value = failed === events.length
			? (events.length === 1 ? 'That did not delete. Please try again.' : 'None of them deleted. Please try again.')
			: `${failed} could not be deleted; the others were.`;
		return;
	}
	window.location.reload();
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
		'e': { color: 'bg-green-500', progress: `goes live ${formatEmbargoShort(event.embargo_date, event.timezone)}`, url: `/hosting/event/${event.slug}/edit` },
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

	// Unfinished-drafts cap (Event::UNCOUNTED_STATUSES; EI staff exempt)
	const unpublishedCount = props.organizer.events.filter(
		event => !['p', 'e', 'r'].includes(event.status) && !event.is_deleted
	).length;

	if (unpublishedCount >= MAX_UNPUBLISHED_EVENTS && !props.user.isModerator) {
		alert(`You can have ${MAX_UNPUBLISHED_EVENTS} unfinished drafts at a time. Submit one for review or delete one before starting another.`);
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
// No events at all yet (in any list): the page shows one big "Make your first listing".
const noEventsYet = computed(() => filters.every((filter) => getFilteredEvents(filter.id).length === 0));

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
	clearPicked();
	try {
		localStorage.setItem(FILTER_KEY, id);
	} catch {
		// Private windows can refuse; the list still switches.
	}
};

// Airbnb-style pill tabs. Show runs only appear when there are some; venues live under the menu's Your venues.
const shows = computed(() => props.organizer?.kept_projects ?? []);
const tabs = computed(() => [{ id: 'events', name: 'Events' }]);

// Show runs live inside the list now (see listRows), not in a tab of their own.
const keptIds = computed(() => new Set(shows.value.map((show) => show.id)));
const keptById = computed(() => Object.fromEntries(shows.value.map((show) => [show.id, show])));
// The show run an event is in, or is waiting to join (a pending request).
const showOf = (event) => event.run_of ?? event.joining ?? null;
const runOf = (event) => {
	const show = showOf(event);
	return show ? {
		...show,
		kept: keptIds.value.has(show.id),
		waiting: !event.run_of,
		// Shown on a lone run's tag too, so a waiting request is never out of sight.
		asking: keptById.value[show.id]?.pending_requests || 0,
	} : null;
};

// The list in order: a show run with two or more runs in this view becomes one
// group, placed where its first run would be; everything else stays a row.
const listRows = computed(() => {
	const events = filteredEvents.value;
	const counts = {};
	events.forEach((event) => {
		const show = showOf(event);
		if (show) counts[show.id] = (counts[show.id] ?? 0) + 1;
	});

	const rows = [];
	const groups = {};
	events.forEach((event) => {
		const projectId = showOf(event)?.id;
		// Any event in a show run (or asking to join one) sits in its show
		// run's card, even alone, so it is plain that it is a show run.
		if (!projectId) {
			rows.push({ type: 'event', key: `event-${event.id}`, event });
			return;
		}
		if (!groups[projectId]) {
			groups[projectId] = { type: 'group', key: `show-${projectId}`, project: { ...runOf(event), waiting: false }, events: [] };
			rows.push(groups[projectId]);
		}
		groups[projectId].events.push(event);
	});

	Object.values(groups).forEach((group) => {
		const pictureOf = (event) => {
			const path = event.images?.[0]?.large_image_path ?? event.thumbImagePath;
			return path ? `${imageUrl}${path}` : null;
		};
		group.pictures = group.events.slice(0, 3).map(pictureOf);
		group.live = group.events.filter((event) => ['p', 'e'].includes(event.status) && isActive(event)).length;
		group.clicks = group.events.reduce((sum, event) => sum + (event.total_clicks || 0), 0);
		group.joining = group.events.filter((event) => !event.run_of).length;
		group.waiting = keptById.value[group.project.id]?.pending_requests || 0;
		const places = [...new Set(group.events.map((event) => event.location?.city).filter(Boolean))];
		group.places = places.length > 2 ? `${places.slice(0, 2).join(', ')} +${places.length - 2}` : places.join(', ');
	});

	return rows;
});

// "Add a run": a brand-new event that asks to join this show run, made in one
// step on the server (a refused request leaves no empty draft). Joining fills
// the empty fields from this company's earlier run of it, then the show's page
// (PrefillFromShow): words, links, genres, advisories, tickets and pictures;
// never the name, the place (a new event has not said it is in person yet) or
// the dates. It opens at the start and sits in this card as waiting until approved.
const addRun = async (group) => {
	if (duplicatingEvent.value) return;
	duplicatingEvent.value = true;
	try {
		const response = await axios.post('/hosting/event/create', { organizer_id: props.organizer.id, project_id: group.project.id });
		window.location.href = `/hosting/event/${response.data.event.slug}/edit`;
	} catch (error) {
		duplicatingEvent.value = false;
		alert(Object.values(error.response?.data?.errors ?? {}).flat()[0] || error.response?.data?.message || 'Could not start a new run. Please try again.');
	}
};

// Which show run cards are open, remembered in this browser like the list filter.
const GROUPS_KEY = 'hosting.openShowRuns';
const savedGroups = (() => {
	try {
		const saved = JSON.parse(localStorage.getItem(GROUPS_KEY) ?? '[]');
		return Array.isArray(saved) ? saved.filter((key) => typeof key === 'string') : [];
	} catch {
		return [];
	}
})();
const openGroups = ref(new Set(savedGroups));
// A card with one event starts open (nothing to fold away); it is
// remembered when closed instead.
const lone = (row) => row.events.length === 1;
const isOpen = (row) => (lone(row) ? !openGroups.value.has(`${row.key}:shut`) : openGroups.value.has(row.key));
const toggleGroup = (row) => {
	const key = lone(row) ? `${row.key}:shut` : row.key;
	const next = new Set(openGroups.value);
	next.has(key) ? next.delete(key) : next.add(key);
	openGroups.value = next;
	try {
		localStorage.setItem(GROUPS_KEY, JSON.stringify([...next].slice(-50)));
	} catch {
		// Private windows can refuse; the card still opens.
	}
};

// Kept show runs with no live or draft run of this company's (deleted ones do not count).
const otherShowRuns = computed(() => {
	const ownRuns = new Set((props.organizer?.events ?? []).filter((event) => !event.is_deleted).map((event) => event.run_of?.id).filter(Boolean));
	return shows.value.filter((show) => !ownRuns.has(show.id));
});

// The tab is kept in the address, so going back or reloading lands on it.
const tabFromUrl = new URLSearchParams(window.location.search).get('tab');
const tab = ref(tabs.value.some((item) => item.id === tabFromUrl) ? tabFromUrl : 'events');

const setTab = (id) => {
	tab.value = id;
	const url = new URL(window.location);
	url.searchParams.set('tab', id);
	window.history.replaceState({}, '', url);
};




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
	lockedRunChoice.value = null;
	lockedError.value = '';

	// Arrived by redirect? Drop the ?locked= so a reload doesn't reopen this.
	if (new URLSearchParams(window.location.search).has('locked')) {
		window.history.replaceState({}, document.title, window.location.pathname);
	}
};

// The answer to "so what do I do instead?": Event::duplicate() copies the
// description, category, genres and advisories but drops the dates, tickets
// and closing date — the shape of a new run. No confirm(): this modal IS the
// prompt. The copy opens in the wizard, where the organizer renames it.
// The same show run switch as Duplicate's pop-up; until touched it follows the offer.
const lockedRunOffer = computed(() => runOfferFor(lockedEvent.value));
const lockedRunChoice = ref(null);
const lockedAsRun = computed({
	get: () => lockedRunChoice.value ?? Boolean(lockedRunOffer.value?.on),
	set: (value) => { lockedRunChoice.value = value; },
});

const duplicateLockedEvent = async () => {
	const event = lockedEvent.value;
	if (!event) return;

	duplicatingLocked.value = true;

	try {
		// The slug may have come straight from ?locked=, so it is encoded here.
		const response = await axios.post(`/api/events/${encodeURIComponent(event.slug)}/duplicate`, lockedRunOffer.value ? { as_run: lockedAsRun.value } : {});
		window.location.href = `/hosting/event/${response.data.event.slug}/edit`;
	} catch (error) {
		duplicatingLocked.value = false;
		// Said inside the pop-up, never in the browser's box (which freezes the page).
		lockedError.value = error.response?.status === 429
			? 'Too many tries just now. Please wait a minute and try again.'
			: (Object.values(error.response?.data?.errors ?? {}).flat()[0] ?? error.response?.data?.message ?? 'Could not make a copy. Please try again.');
	}
};
const lockedError = ref('');

// Duplicate asks in its own pop-up (DuplicateAsk), which also asks whether
// the copy is a new run of the same show.
const duplicateAsk = ref(null);
const duplicateEvent = (event) => {
	closeModal();
	duplicateAsk.value = event;
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