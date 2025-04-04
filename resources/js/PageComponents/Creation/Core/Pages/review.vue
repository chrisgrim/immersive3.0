<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2 class="text-2xl font-bold mb-6">Review Your Listing</h2>

            <!-- Main Content -->
            <div class="w-full md:w-[61rem] mx-auto pb-24 space-y-8">
                <!-- Images Section -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <div class="grid gap-4 justify-center">
                        <!-- Single Image -->
                        <div v-if="event.images?.length === 1" 
                             class="aspect-[3/4] overflow-hidden rounded-xl" style="height: 45rem; width: fit-content;">
                            <img :src="imageUrl + event.images[0].large_image_path"
                                 :alt="`${event.name} Immersive Event - Main Image`"
                                 class="w-full h-full object-cover"
                                 style="height: 45rem;"
                            />
                        </div>

                        <!-- Multiple Images -->
                        <div v-else class="w-full">
                            <!-- First row: First image (vertical) and second image (same height) -->
                            <div v-if="event.images?.length >= 2" class="gap-2 md:rounded-2xl overflow-hidden" style="display: flex;">
                                <!-- First image (vertical 3:4 ratio) with fixed height -->
                                <div class="aspect-[3/4]" style="height: 25rem; flex-shrink: 0;">
                                    <img :src="imageUrl + event.images[0].large_image_path"
                                         :alt="`${event.name} Immersive Event - Image 1`"
                                         class="w-full h-full object-cover"
                                         style="height: 25rem;"
                                    />
                                </div>
                                
                                <!-- Second image (same height) -->
                                <div style="flex-grow: 1;">
                                    <img :src="imageUrl + event.images[1].large_image_path"
                                         :alt="`${event.name} Immersive Event - Image 2`"
                                         class="w-full object-cover"
                                         style="height: 25rem;"
                                    />
                                </div>
                            </div>
                            
                            <!-- Remaining images (two per row) -->
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <div v-for="(image, index) in event.images.slice(2)" 
                                     :key="index + 2"
                                     class="aspect-[3/2] rounded-xl overflow-hidden">
                                    <img :src="imageUrl + image.large_image_path"
                                         :alt="`${event.name} Immersive Event - Image ${index + 3}`"
                                         class="w-full h-full object-cover"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Video Section -->
                <div v-if="event.video" class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">Video</h3>
                    <div class="relative w-full aspect-video">
                        <iframe
                            :src="`https://www.youtube.com/embed/${event.video}`"
                            class="absolute top-0 left-0 w-full h-full rounded-xl"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>

                <!-- Name & Description -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-5xl leading-tight font-semibold mb-3 break-words hyphens-auto">{{ event.name }}</h3>
                    <p class="text-1xl mb-16 break-words hyphens-auto">{{ event.tag_line }}</p>
                    <p class="text-2.5xl font-normal whitespace-pre-line break-words hyphens-auto">{{ event.description }}</p>
                </div>

                <!-- Location Section -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">
                        {{ event.hasLocation ? 'Location:' : 'Remote Event:' }}
                    </h3>
                    <div v-if="event.hasLocation">
                        <div class="mb-4">
                            <p class="text-black font-medium mb-4">{{ event.location.venue }}</p>
                            <p class="text-neutral-500 font-normal text-1xl leading-tight">{{ event.location.street }}</p>
                            <p class="text-neutral-500 font-normal text-1xl leading-tight">{{ event.location.city }}, {{ event.location.region }} {{ event.location.postal_code }}</p>
                        </div>
                    </div>
                    <div v-else>
                        <p class="font-medium mb-4">Available Platforms</p>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div v-for="location in event.remotelocations" 
                                 :key="location.id"
                                 class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words hyphens-auto">
                                {{ location.name }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category & Genres -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">Category:</h3>
                    <div class="flex gap-4 mb-4">
                        <img v-if="event.category?.thumbImagePath"
                             :src="imageUrl + event.category.thumbImagePath"
                             class="w-16 h-16 rounded-lg object-cover flex-shrink-0"
                             :alt="event.category?.name"
                        />
                        <div class="flex-1 min-w-0 justify-center flex flex-col">
                            <p class="text-gray-600 mb-2">{{ event.category?.name }}</p>
                            <p class="text-gray-500 text-sm">{{ event.subcategory?.name }}</p>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 mt-16">Tags:</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-8">
                        <div v-for="genre in event.genres" 
                             :key="genre.id"
                             class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-1xl break-words hyphens-auto">
                            {{ genre.name }}
                        </div>
                    </div>
                </div>

                <!-- Shows & Tickets -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">Dates:</h3>
                    <div class="flex justify-between items-center mb-4">
                        <p class="text-gray-600">{{ formatDateRange(event.shows) }}</p>
                        <p class="text-gray-600">{{ event.shows?.length || 0 }} show{{ event.shows?.length !== 1 ? 's' : '' }}</p>
                    </div>

                    <!-- Embargo Date -->
                    <div v-if="event.embargo_date" class="mt-4 mb-8 p-4 bg-yellow-50 rounded-xl">
                        <p class="text-yellow-800">
                            <span class="font-semibold">⚠️ Embargoed until:</span> 
                            {{ formatEmbargoDate(event.embargo_date) }}
                        </p>
                    </div>

                    <h3 class="text-xl font-semibold mb-4 mt-16">Tickets:</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div v-for="ticket in event.shows?.[0]?.tickets" 
                             :key="ticket.id"
                             class="flex flex-col border border-neutral-300 rounded-2xl">
                            <p class="px-4 pt-4 text-1xl font-semibold break-words hyphens-auto">{{ ticket.name }}</p>
                            <div class="flex-grow flex flex-col justify-end px-4 pb-4">
                                <p class="text-1xl font-semibold mt-14 leading-tight">${{ ticket.ticket_price }}</p>
                                <p v-if="ticket.description" class="text-lg text-gray-600 leading-tight break-words hyphens-auto">
                                    {{ ticket.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advisories -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">Advisories:</h3>
                    <div class="space-y-8">
                        <!-- Audience -->
                        <div v-if="event.advisories?.audience">
                            <p class="font-medium mb-4">Audience</p>
                            <p class="text-neutral-700 font-normal text-2.5xl leading-9 whitespace-pre-line break-words hyphens-auto">{{ event.advisories.audience }}</p>
                        </div>

                        <!-- Content Advisories -->
                        <div>
                            <p class="font-medium mb-4">Content Advisories</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div v-for="advisory in event.content_advisories" 
                                     :key="advisory.id"
                                     class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words hyphens-auto">
                                    {{ advisory.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Mobility Advisories -->
                        <div>
                            <p class="font-medium mb-4">Mobility Advisories</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div v-for="advisory in event.mobility_advisories" 
                                     :key="advisory.id"
                                     class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words hyphens-auto">
                                    {{ advisory.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Interaction Level -->
                        <div>
                            <p class="font-medium mb-4">Interaction Level</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words">
                                    {{ event.interactive_level.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Age Limit -->
                        <div>
                            <p class="font-medium mb-4">Age Requirement</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words">
                                    {{ event.age_limits?.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Sexual Content Description -->
                        <div v-if="event.advisories?.sexual">
                            <p class="font-medium mb-4">Sexual Content Description</p>
                            <p class="text-neutral-700 text-2.5xl font-normal whitespace-pre-line">{{ event.advisories.sexualDescription }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { inject } from 'vue';
import moment from 'moment-timezone';

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const event = inject('event');

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

// Component API
defineExpose({
    isValid: async () => true, // Review page is always valid
    submitData: () => ({}) // No data to submit from review page
});
</script>