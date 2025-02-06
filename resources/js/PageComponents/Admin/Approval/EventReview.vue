<template>
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none px-8">
            <h2 class="text-2xl font-bold mb-6">Review Event</h2>
        </div>

        <!-- After Fixed Header Section and before Scrollable Content Section -->
        <div v-if="props.event?.duplicateEvents?.length" class="flex-none px-8 mb-4">
            <div class="p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                <h3 class="text-yellow-800 font-semibold mb-2">⚠️ Possible Duplicate Events Found:</h3>
                <div class="space-y-2">
                    <div v-for="duplicate in props.event.duplicateEvents" 
                         :key="duplicate.id" 
                         class="flex items-center justify-between">
                        <span class="text-yellow-700">{{ duplicate.name }}</span>
                        <a :href="`/events/${duplicate.slug}`" 
                           target="_blank"
                           class="text-blue-600 hover:text-blue-800 underline">
                            View Event
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scrollable Content Section -->
        <div class="flex-1 overflow-auto">
            <div class="max-w-screen-xl mx-auto px-8 pt-8">
                <div class="w-full md:w-[61rem] mx-auto pb-24 space-y-8">
                    <!-- Images Section -->
                    <div v-if="props.event?.images?.length" class="p-8 shadow-custom-1 rounded-3xl">
                        <div class="grid gap-4">
                            <!-- Single Image -->
                            <div v-if="props.event?.images?.length === 1" 
                                 class="aspect-[3/4] w-[30rem] overflow-hidden rounded-xl">
                                <img :src="imageUrl + props.event?.images[0].large_image_path"
                                     :alt="`${props.event?.name} Immersive Event - Main Image`"
                                     class="w-full h-full object-cover"
                                />
                            </div>

                            <!-- Multiple Images -->
                            <div v-else 
                                 class="grid gap-2 rounded-xl overflow-hidden"
                                 :class="{
                                     'grid-cols-3': props.event?.images?.length === 2 || props.event?.images?.length > 3,
                                     'grid-cols-2': props.event?.images?.length === 3
                                 }">
                                <!-- First Image -->
                                <div v-if="props.event?.images?.length === 2" 
                                     class="col-span-1 h-full">
                                    <img :src="imageUrl + props.event?.images[0].large_image_path"
                                         :alt="`${props.event?.name} Immersive Event - Main Image`"
                                         class="w-full h-full object-cover rounded-lg"
                                    />
                                </div>

                                <!-- Right Side Image (for 2 images) -->
                                <template v-if="props.event?.images?.length === 2">
                                    <div class="col-span-2 h-full">
                                        <img :src="imageUrl + props.event?.images[1].large_image_path"
                                             :alt="`${props.event?.name} Immersive Event - Image 2`"
                                             class="w-full h-full object-cover rounded-lg"
                                        />
                                    </div>
                                </template>

                                <!-- First Image (for 3 images) -->
                                <div v-if="props.event?.images?.length === 3" 
                                     class="h-full">
                                    <img :src="imageUrl + props.event?.images[0].large_image_path"
                                         :alt="`${props.event?.name} Immersive Event - Main Image`"
                                         class="w-full h-full object-cover rounded-lg"
                                    />
                                </div>

                                <!-- Special 3-image layout -->
                                <template v-if="props.event?.images?.length === 3">
                                    <div class="grid grid-rows-2 gap-2 h-full">
                                        <div class="aspect-[3/2]">
                                            <img :src="imageUrl + props.event?.images[1].large_image_path"
                                                 :alt="`${props.event?.name} Immersive Event - Image 2`"
                                                 class="w-full h-full object-cover rounded-lg"
                                            />
                                        </div>
                                        <div class="aspect-[3/2]">
                                            <img :src="imageUrl + props.event?.images[2].large_image_path"
                                                 :alt="`${props.event?.name} Immersive Event - Image 3`"
                                                 class="w-full h-full object-cover rounded-lg"
                                            />
                                        </div>
                                    </div>
                                </template>

                                <!-- 4+ images layout -->
                                <template v-else-if="props.event?.images?.length > 3">
                                    <!-- First Column (1/3 width) -->
                                    <div class="col-span-1 h-full">
                                        <img :src="imageUrl + props.event?.images[0].large_image_path"
                                             :alt="`${props.event?.name} Immersive Event - Main Image`"
                                             class="w-full h-full object-cover rounded-lg"
                                        />
                                    </div>

                                    <!-- Second Column (2/3 width) -->
                                    <div class="col-span-2 grid grid-cols-2 grid-rows-2 gap-2">
                                        <div v-for="index in Math.min(4, props.event?.images.length - 1)" 
                                             :key="index"
                                             class="aspect-[3/2]">
                                            <img :src="imageUrl + props.event?.images[index].large_image_path"
                                                 :alt="`${props.event?.name} Immersive Event - Image ${index + 1}`"
                                                 class="w-full h-full object-cover rounded-lg"
                                            />
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- After Images Section -->
                    <div v-if="props.event?.youtube_url" class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-4">Video</h3>
                        <div class="relative w-full" style="padding-top: 56.25%"> <!-- 16:9 Aspect Ratio -->
                            <iframe
                                :src="getYoutubeEmbedUrl(props.event.youtube_url)"
                                class="absolute top-0 left-0 w-full h-full rounded-xl"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </div>
                    </div>

                    <!-- Add after Images Section and before Name & Description -->
                    <div v-if="props.event?.video" class="p-8 shadow-custom-1 rounded-3xl">
                        <div class="relative w-full aspect-video">
                            <iframe
                                :src="`https://www.youtube.com/embed/${props.event.video}`"
                                class="absolute top-0 left-0 w-full h-full rounded-xl"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </div>
                    </div>

                    <!-- Name & Description -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-5xl leading-tight font-semibold mb-3 break-words hyphens-auto">{{ props.event?.name }}</h3>
                        <p class="text-1xl mb-16 break-words hyphens-auto">{{ props.event?.tag_line }}</p>
                        <p class="text-regular whitespace-pre-line break-words hyphens-auto">{{ props.event?.description }}</p>
                    </div>

                    <!-- Organizer Section -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-4">Event Organizer:</h3>
                        <div class="flex flex-col gap-4">
                            <!-- Organizer Header -->
                            <div class="flex items-center">
                                <div v-if="props.event?.organizer?.largeImagePath" class="flex-shrink-0">
                                    <img 
                                        :src="`${imageUrl}${props.event.organizer.largeImagePath}`"
                                        :alt="props.event?.organizer?.name"
                                        class="w-16 h-16 rounded-full object-cover"
                                    />
                                </div>
                                <div class="flex-grow mt-4">
                                    <h4 class="text-4xl font-medium">{{ props.event?.organizer?.name }}</h4>
                                    <p class="text-gray-600 text-xl">Created: {{ formatDate(props.event?.organizer?.created_at) }}</p>
                                </div>
                            </div>

                            <!-- Description -->
                            <div v-if="props.event?.organizer?.description" class="mt-2">
                                <p class="text-gray-700 whitespace-pre-line">{{ props.event?.organizer?.description }}</p>
                            </div>

                            <!-- Social Links -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <!-- Website -->
                                <a v-if="props.event?.organizer?.website" 
                                   :href="props.event.organizer.website"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex items-center gap-4 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black group">
                                    <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                        <component :is="RiSearchLine" class="w-6 h-6 text-neutral-700" />
                                    </div>
                                    <span class="truncate">{{ props.event.organizer.website }}</span>
                                </a>

                                <!-- Twitter -->
                                <a v-if="props.event?.organizer?.twitterHandle" 
                                   :href="`https://twitter.com/${props.event.organizer.twitterHandle}`"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex items-center gap-4 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black group">
                                    <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                        <component :is="RiTwitterLine" class="w-6 h-6 text-neutral-700" />
                                    </div>
                                    <span class="truncate">@{{ props.event.organizer.twitterHandle }}</span>
                                </a>

                                <!-- Instagram -->
                                <a v-if="props.event?.organizer?.instagramHandle" 
                                   :href="`https://instagram.com/${props.event.organizer.instagramHandle}`"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex items-center gap-4 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black group">
                                    <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                        <component :is="RiInstagramLine" class="w-6 h-6 text-neutral-700" />
                                    </div>
                                    <span class="truncate">@{{ props.event.organizer.instagramHandle }}</span>
                                </a>

                                <!-- Facebook -->
                                <a v-if="props.event?.organizer?.facebookHandle" 
                                   :href="`https://facebook.com/${props.event.organizer.facebookHandle}`"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex items-center gap-4 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black group">
                                    <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                        <component :is="RiFacebookBoxLine" class="w-6 h-6 text-neutral-700" />
                                    </div>
                                    <span class="truncate">{{ props.event.organizer.facebookHandle }}</span>
                                </a>

                                <!-- Patreon -->
                                <a v-if="props.event?.organizer?.patreon" 
                                   :href="`https://patreon.com/${props.event.organizer.patreon}`"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex items-center gap-4 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black group">
                                    <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                        <component :is="RiPatreonLine" class="w-6 h-6 text-neutral-700" />
                                    </div>
                                    <span class="truncate">{{ props.event.organizer.patreon }}</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-4">
                            {{ props.event?.hasLocation ? 'Location:' : 'Remote Event:' }}
                        </h3>
                        <div v-if="props.event?.hasLocation">
                            <div class="mb-4">
                                <p class="text-black font-medium mb-4">{{ props.event?.location.venue }}</p>
                                <p class="text-neutral-500 font-normal text-1xl leading-tight">{{ props.event?.location.street }}</p>
                                <p class="text-neutral-500 font-normal text-1xl leading-tight">{{ props.event?.location.city }}, {{ props.event?.location.region }} {{ props.event?.location.postal_code }}</p>
                            </div>
                            <!-- Map -->
                            <div class="w-full h-60 rounded-lg overflow-hidden">
                                <l-map 
                                    ref="locationMapRef"
                                    :zoom="map.zoom" 
                                    :center="map.center"
                                    :style="{
                                        height: '100%',
                                        width: '100%'
                                    }"
                                    @ready="onMapReady"
                                    :options="{ 
                                        scrollWheelZoom: false, 
                                        zoomControl: false,
                                        dragging: false,
                                        touchZoom: false,
                                        doubleClickZoom: false,
                                        boxZoom: false,
                                        tap: false
                                    }"
                                >
                                    <l-tile-layer :url="map.url" />
                                    <l-marker 
                                        :lat-lng="map.center"
                                        :icon="icon"
                                    />
                                </l-map>
                            </div>
                        </div>
                        <div v-else>
                            <p class="font-medium mb-4">Available Platforms</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div v-for="location in props.event?.remotelocations" 
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
                            <img 
                                v-if="categoryImagePath"
                                :src="categoryImagePath"
                                class="w-16 h-16 rounded-lg object-cover flex-shrink-0"
                                :alt="props.event?.category?.name"
                            />
                            <div class="flex-1 min-w-0 justify-center flex flex-col">
                                <p class="text-gray-600 mb-2">{{ props.event?.category?.name }}</p>
                                <p class="text-gray-500 text-sm">{{ props.event?.subcategory?.name }}</p>
                            </div>
                        </div>
                        <h3 class="text-xl font-semibold mb-4 mt-16">Tags:</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-8">
                            <div v-for="genre in props.event?.genres" 
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
                            <p class="text-gray-600">{{ formatDateRange(props.event?.shows) }}</p>
                            <p class="text-gray-600">{{ props.event?.shows?.length || 0 }} show{{ props.event?.shows?.length !== 1 ? 's' : '' }}</p>
                        </div>
                        
                        <!-- Add Embargo Date Display -->
                        <div v-if="props.event?.embargo_date" class="mt-4 mb-8 p-4 bg-yellow-50 rounded-xl">
                            <p class="text-yellow-800">
                                <span class="font-semibold">⚠️ Embargoed until:</span> 
                                {{ formatEmbargoDate(props.event.embargo_date) }}
                            </p>
                        </div>

                        <h3 class="text-xl font-semibold mb-4 mt-16">Tickets:</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div v-for="ticket in props.event?.shows?.[0]?.tickets" 
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
                        <div class="mt-16">
                            <h3 class="text-xl font-semibold mb-4">Ticket Link:</h3>
                            <a v-if="props.event?.ticketUrl"
                                :href="props.event?.ticketUrl"
                                target="_blank"
                                rel="nofollow noopener"
                                class="text-blue-600 hover:text-blue-800 text-1xl">
                                {{ props.event?.ticketUrl}}
                            </a>
                        </div>
                    </div>

                    <!-- Advisories -->
                    <div class="p-8 shadow-custom-1 rounded-3xl">
                        <h3 class="text-xl font-semibold mb-4">Advisories:</h3>
                        <div class="space-y-8">
                            <!-- Audience -->
                            <div v-if="props.event?.advisories?.audience">
                                <p class="font-medium mb-4">Audience</p>
                                <p class="text-neutral-700 text-1xl whitespace-pre-line">{{ props.event?.advisories?.audience }}</p>
                            </div>

                            <!-- Content Advisories -->
                            <div>
                                <p class="font-medium mb-4">Content Advisories</p>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <div v-for="advisory in props.event?.content_advisories" 
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
                                    <div v-for="advisory in props.event?.mobility_advisories" 
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
                                    <div class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words hyphens-auto">
                                        {{ props.event?.interactive_level?.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fixed Footer Section -->
        <div class="flex border-t border-gray-200 bg-white h-32 justify-end items-center">
            <div class="px-8 py-6 flex gap-4">
                <!-- Edit Button -->
                <a 
                    :href="`/hosting/event/${props.event?.slug}/edit`"
                    target="_blank"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors border border-black': true,
                        'bg-white text-black hover:bg-gray-100': true
                    }"
                >
                    <div class="flex items-center gap-2">
                        Edit Event
                    </div>
                </a>

                <!-- Reject Button -->
                <button 
                    @click="onReject"
                    :disabled="processing"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors border border-black': true,
                        'bg-white text-black hover:bg-gray-100': !processing,
                        'bg-gray-300 text-gray-500 cursor-not-allowed': processing
                    }"
                >
                    <div class="flex items-center gap-2">
                        <svg 
                            v-if="isRejecting"
                            class="animate-spin h-5 w-5" 
                            xmlns="http://www.w3.org/2000/svg" 
                            fill="none" 
                            viewBox="0 0 24 24"
                        >
                            <circle 
                                class="opacity-25" 
                                cx="12" 
                                cy="12" 
                                r="10" 
                                stroke="currentColor" 
                                stroke-width="4"
                            />
                            <path 
                                class="opacity-75" 
                                fill="currentColor" 
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{ isRejecting ? 'Rejecting...' : 'Reject' }}
                    </div>
                </button>

                <!-- Approve Button -->
                <button 
                    @click="onApprove"
                    :disabled="processing"
                    :class="{
                        'px-6 py-3 rounded-lg transition-colors': true,
                        'bg-black text-white hover:bg-gray-800': !processing,
                        'bg-gray-300 text-gray-500 cursor-not-allowed': processing
                    }"
                >
                    <div class="flex items-center gap-2">
                        <svg 
                            v-if="isApproving"
                            class="animate-spin h-5 w-5" 
                            xmlns="http://www.w3.org/2000/svg" 
                            fill="none" 
                            viewBox="0 0 24 24"
                        >
                            <circle 
                                class="opacity-25" 
                                cx="12" 
                                cy="12" 
                                r="10" 
                                stroke="currentColor" 
                                stroke-width="4"
                            />
                            <path 
                                class="opacity-75" 
                                fill="currentColor" 
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{ isApproving ? 'Approving...' : 'Approve' }}
                    </div>
                </button>
            </div>
        </div>

        <!-- Add this right before the final closing </div> -->
        <teleport to="body">
            <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
                <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh]">
                    <!-- Header -->
                    <div class="p-8 pb-6">
                        <h2 class="text-2xl font-bold mb-2">Reject Event</h2>
                        <p class="text-gray-500 font-normal">Please provide a reason for rejecting this event</p>
                    </div>

                    <!-- Content -->
                    <div class="p-8 pt-0 overflow-y-auto flex-1">
                        <div class="space-y-6">
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Reason</p>
                                <textarea 
                                    v-model="rejectionReason"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter reason for rejection..."
                                    rows="4"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-8 border-t border-neutral-400">
                        <div class="flex justify-end space-x-4">
                            <button 
                                @click="closeRejectModal"
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="confirmReject"
                                :disabled="!rejectionReason.trim()"
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>

<script setup>
import { inject, computed, ref, watch, onMounted } from 'vue';
import moment from 'moment-timezone';
import { LMap, LTileLayer, LMarker } from "@vue-leaflet/vue-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import axios from 'axios';
import { 
    RiSearchLine,
    RiTwitterLine,
    RiInstagramLine,
    RiFacebookBoxLine,
    RiPatreonLine 
} from '@remixicon/vue';

const props = defineProps({
    event: {
        type: Object,
        required: true
    }
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;

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

const categoryImagePath = computed(() => {
    if (props.event?.category?.images?.[0]?.path) {
        return `${imageUrl}${props.event.category.images[0].path}`;
    }
    if (props.event?.category?.thumbImagePath) {
        return `${imageUrl}${props.event.category.thumbImagePath}`;
    }
    return null;
});

const locationMapRef = ref(null);

const map = ref({
    zoom: 14,
    center: computed(() => [
        parseFloat(props.event?.location?.latitude) || 40.7127753,
        parseFloat(props.event?.location?.longitude) || -74.0059728
    ]),
    url: 'https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU'
});

const icon = L.divIcon({
    className: 'custom-div-icon',
    html: `
        <div style="position: relative; width: 30px; height: 30px;">
            <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 25px;
                height: 25px;
                background: #ff385c;
                border: 3.5px solid white;
                border-radius: 50%;
                box-shadow: 0 0 0 5px #ff385c;
            "></div>
            <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 40px;
                height: 40px;
                background: rgba(255, 56, 92, 0.35);
                border-radius: 50%;
                animation: markerPulse 2s infinite;
            "></div>
        </div>
    `,
    iconSize: [30, 30],
    iconAnchor: [15, 15]
});

const onMapReady = () => {
    setTimeout(() => {
        if (locationMapRef.value?.leafletObject) {
            locationMapRef.value.leafletObject.invalidateSize();
            locationMapRef.value.leafletObject.setView(map.value.center, map.value.zoom);
        }
    }, 100);
};

const getYoutubeEmbedUrl = (url) => {
    if (!url) return '';
    
    // Handle different YouTube URL formats
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
    const match = url.match(regExp);
    
    if (match && match[2].length === 11) {
        return `https://www.youtube.com/embed/${match[2]}`;
    }
    
    return url;
};

const showRejectModal = ref(false);
const rejectionReason = ref('');
const processing = ref(false);
const isRejecting = ref(false);
const isApproving = ref(false);

const onReject = () => {
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    rejectionReason.value = '';
};

const confirmReject = async () => {
    if (!rejectionReason.value.trim()) return;
    
    try {
        processing.value = true;
        isRejecting.value = true;
        await axios.post(`/api/admin/approve/events/${props.event.slug}/reject`, {
            reason: rejectionReason.value
        });
        
        closeRejectModal();
        window.location.href = '/admin/dashboard?view=approve-events';
    } catch (error) {
        console.error('Error rejecting event:', error);
    } finally {
        processing.value = false;
        isRejecting.value = false;
    }
};

const onApprove = async () => {
    try {
        processing.value = true;
        isApproving.value = true;
        await axios.post(`/api/admin/approve/events/${props.event.slug}/approve`);
        window.location.href = '/admin/dashboard?view=approve-events';
    } catch (error) {
        console.error('Error approving event:', error);
    } finally {
        processing.value = false;
        isApproving.value = false;
    }
};

const formatDate = (date) => {
    return moment(date).format('MMM D, YYYY');
};

// Component API
defineExpose({
    isValid: async () => true, // Review page is always valid
    submitData: () => ({}) // No data to submit from review page
});

watch(() => props.event, (newValue) => {
    console.log('Event props in EventReview:', newValue); // Log whenever the event prop changes
}, { immediate: true, deep: true });
</script>

<style>
@import 'leaflet/dist/leaflet.css';

@keyframes markerPulse {
    0% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
    }
    100% {
        transform: translate(-50%, -50%) scale(3);
        opacity: 0;
    }
}
</style>
