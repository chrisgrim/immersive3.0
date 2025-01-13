<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2 class="text-2xl font-bold mb-6">Review Your Listing</h2>

            <!-- Main Content -->
            <div class="w-full md:w-[61rem] mx-auto pb-24 space-y-8">
                <!-- Images Section -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <div class="grid gap-4">
                        <!-- Single Image -->
                        <div v-if="event.images?.length === 1" 
                             class="aspect-[3/4] w-[30rem] overflow-hidden rounded-xl">
                            <img :src="imageUrl + event.images[0].large_image_path"
                                 :alt="`${event.name} Immersive Event - Main Image`"
                                 class="w-full h-full object-cover"
                            />
                        </div>

                        <!-- Multiple Images (keeping your existing image layout) -->
                        <div v-else 
                             class="grid gap-2 rounded-xl overflow-hidden"
                             :class="{
                                 'grid-cols-3': event.images?.length === 2 || event.images?.length > 3,
                                 'grid-cols-2': event.images?.length === 3
                             }">
                            <!-- Your existing image layout code -->
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
                    <h3 class="text-5xl leading-tight font-semibold mb-3">{{ event.name }}</h3>
                    <p class="text-1xl mb-16">{{ event.tag_line }}</p>
                    <p class="text-regular whitespace-pre-line">{{ event.description }}</p>
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
                                 class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl">
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
                             class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-1xl">
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
                            <p class="px-4 pt-4 text-1xl font-semibold">{{ ticket.name }}</p>
                            <div class="flex-grow flex flex-col justify-end px-4 pb-4">
                                <p class="text-1xl font-semibold mt-14 leading-tight">${{ ticket.ticket_price }}</p>
                                <p v-if="ticket.description" class="text-lg text-gray-600 leading-tight">
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
                            <p class="text-neutral-700 text-1xl whitespace-pre-line">{{ event.advisories.audience }}</p>
                        </div>

                        <!-- Content Advisories -->
                        <div>
                            <p class="font-medium mb-4">Content Advisories</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div v-for="advisory in event.content_advisories" 
                                     :key="advisory.id"
                                     class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl">
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
                                     class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl">
                                    {{ advisory.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Interaction Level -->
                        <div>
                            <p class="font-medium mb-4">Interaction Level</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl">
                                    {{ event.interactive_level.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Sexual Content Description -->
                        <div v-if="event.advisories?.sexual">
                            <p class="font-medium mb-4">Sexual Content Description</p>
                            <p class="text-neutral-700 text-1xl whitespace-pre-line">{{ event.advisories.sexualDescription }}</p>
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