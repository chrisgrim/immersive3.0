<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2>Review Your Listing</h2>

            <!-- Main Content -->
            <div class="gap-8 mt-8">
                <!-- Images Section -->
                <div class="grid gap-4">
                    <!-- Single Image -->
                    <div v-if="event.images?.length === 1" 
                         class="aspect-[3/4] w-[30rem] overflow-hidden md:rounded-xl">
                        <img :src="imageUrl + event.images[0].large_image_path"
                             :alt="`${event.name} Immersive Event - Main Image`"
                             class="w-full h-full object-cover"
                        />
                    </div>

                    <!-- Multiple Images -->
                    <div v-else 
                         class="grid gap-2 md:rounded-xl overflow-hidden"
                         :class="{
                             'grid-cols-3': event.images?.length === 2 || event.images?.length > 3,
                             'grid-cols-2': event.images?.length === 3
                         }">
                        
                        <!-- First Image -->
                        <div v-if="event.images?.length === 2" 
                             class="col-span-1 h-full">
                            <img :src="imageUrl + event.images[0].large_image_path"
                                 :alt="`${event.name} Immersive Event - Main Image`"
                                 class="w-full h-full object-cover"
                            />
                        </div>

                        <!-- Right Side Image (for 2 images) -->
                        <template v-if="event.images?.length === 2">
                            <div class="col-span-2 h-full">
                                <img :src="imageUrl + event.images[1].large_image_path"
                                     :alt="`${event.name} Immersive Event - Image 2`"
                                     class="w-full h-full object-cover"
                                />
                            </div>
                        </template>
                        
                        <!-- First Image (for 3 images) -->
                        <div v-if="event.images?.length === 3" 
                             class="h-full">
                            <img :src="imageUrl + event.images[0].large_image_path"
                                 :alt="`${event.name} Immersive Event - Main Image`"
                                 class="w-full h-full object-cover"
                            />
                        </div>
                        
                        <!-- Special 3-image layout -->
                        <template v-if="event.images?.length === 3">
                            <div class="grid grid-rows-2 gap-2 h-full">
                                <div class="aspect-[3/2]">
                                    <img :src="imageUrl + event.images[1].large_image_path"
                                         :alt="`${event.name} Immersive Event - Image 2`"
                                         class="w-full h-full object-cover"
                                    />
                                </div>
                                <div class="aspect-[3/2]">
                                    <img :src="imageUrl + event.images[2].large_image_path"
                                         :alt="`${event.name} Immersive Event - Image 3`"
                                         class="w-full h-full object-cover"
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- Original 4+ images layout -->
                        <template v-else-if="event.images?.length > 3">
                            <!-- First Column (1/3 width) -->
                            <div class="col-span-1 h-full">
                                <img :src="imageUrl + event.images[0].large_image_path"
                                     :alt="`${event.name} Immersive Event - Main Image`"
                                     class="w-full h-full object-cover"
                                />
                            </div>

                            <!-- Second Column (2/3 width) -->
                            <div class="col-span-2 grid grid-cols-2 grid-rows-2 gap-2">
                                <div v-for="index in Math.min(4, event.images.length - 1)" 
                                     :key="index"
                                     class="aspect-[3/2]">
                                    <img :src="imageUrl + event.images[index].large_image_path"
                                         :alt="`${event.name} Immersive Event - Image ${index + 1}`"
                                         class="w-full h-full object-cover"
                                    />
                                </div>
                            </div>
                        </template>
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

            <!-- Event Details Section -->
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
                                    ${{ ticket.price }}<br>
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