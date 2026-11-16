<template>
    <!-- Main Navigation -->
    <nav class="relative flex flex-col items-center flex-shrink-0 w-full mx-auto pt-12">
        <!-- Fixed Header -->
        <div class="w-full flex items-center gap-4 z-50 bg-white px-10 py-8 lg-air:max-w-[40rem] overflow-hidden">
            <button 
                @click="goBack"
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
            >
                <svg 
                    class="w-8 h-8" 
                    viewBox="0 0 24 24" 
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                >
                    <path d="M19 12H5"/>
                    <path d="M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <!-- Back to this event's own organization's listings, not whichever one is active. -->
            <a 
            :href="event.organizer?.slug ? `/hosting/events?organizer=${event.organizer.slug}` : '/hosting/events'" 
            class="cursor-pointer flex-shrink-0">
                <div class="rounded-full bg-neutral-100 w-16 h-16 flex items-center justify-center hover:bg-neutral-200">
                    <svg 
                        class="w-8 h-8" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2"
                    >
                        <g stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="4" cy="6" r="1" fill="currentColor"/>
                            <line x1="8" y1="6" x2="20" y2="6"/>
                            <circle cx="4" cy="12" r="1" fill="currentColor"/>
                            <line x1="8" y1="12" x2="20" y2="12"/>
                            <circle cx="4" cy="18" r="1" fill="currentColor"/>
                            <line x1="8" y1="18" x2="20" y2="18"/>
                        </g>
                    </svg>
                </div>
            </a>
            <!-- Its page on the site, once it is live (the title used to be this link). -->
            <a
                v-if="['p', 'e'].includes(event.status)"
                :href="event.public_url || `/events/${event.slug}`"
                aria-label="See it on the site"
                title="See it on the site"
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
                data-nav-view
            >
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
            <!-- Settings (duplicate, its show run, delete), opening on the right like a step.
                 The event's name is in the Name card just below, so no title up here. -->
            <button
                type="button"
                aria-label="Settings"
                title="Settings"
                class="ml-auto inline-flex items-center justify-center w-16 h-16 rounded-full transition-colors flex-shrink-0"
                :class="currentStep === 'Settings' ? 'bg-[#222222] text-white' : 'bg-neutral-100 hover:bg-neutral-200'"
                :aria-pressed="currentStep === 'Settings'"
                data-nav-settings
                @click="$emit('navigate', 'Settings')"
            >
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3c.4-1.7 2.9-1.7 3.4 0a1.7 1.7 0 002.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.5c1.8.4 1.8 2.9 0 3.4a1.7 1.7 0 00-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-2.9 1.8-3.4 0a1.7 1.7 0 00-2.5-1c-1.6.9-3.3-.9-2.4-2.4a1.7 1.7 0 00-1.1-2.6c-1.7-.4-1.7-2.9 0-3.4a1.7 1.7 0 001.1-2.5c-.9-1.6.8-3.3 2.4-2.4a1.7 1.7 0 002.5-1.1z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="w-full flex flex-col md:items-center overflow-y-auto max-h-[calc(100vh-11rem)] md:max-h-[calc(100vh-20rem)]">
            <div class="w-full space-y-10 lg-air:max-w-[40rem] p-10 pb-40 md:pb-20">
                <!-- Name & Tag Line -->
                <button
                    @click="$emit('navigate', 'Name')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Name',
                            'border border-neutral-200': currentStep !== 'Name'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Name</h3>
                    <!-- The event's name, big: there is no title above the cards any more. -->
                    <p class="text-[3.2rem] leading-[3.8rem] font-semibold text-[#222222] mb-4 break-words hyphens-auto overflow-hidden">
                        {{ event.name || 'No name set' }}
                    </p>
                    <p class="text-neutral-500 text-[1.3rem] leading-[1.8rem] font-normal line-clamp-3 break-words hyphens-auto overflow-hidden">
                        {{ event.tag_line || 'No tagline set' }}
                    </p>
                </button>

                <!-- Category -->
                <button
                    @click="$emit('navigate', 'Category')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Category',
                            'border border-neutral-200': currentStep !== 'Category'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Category</h3>
                    <div class="flex gap-4">
                        <img 
                            v-if="categoryImagePath"
                            :src="categoryImagePath"
                            class="w-16 h-16 rounded-lg object-cover flex-shrink-0"
                            :alt="categoryName"
                        />
                        <div class="flex-1 min-w-0 justify-center flex flex-col">
                            <p class="text-neutral-600 mb-2 truncate">{{ categoryName }}</p>
                        </div>
                    </div>
                </button>

                <!-- Genres -->
                <button
                    @click="$emit('navigate', 'Genres')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Genres',
                            'border border-neutral-200': currentStep !== 'Genres'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Genres</h3>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-for="genre in event.genres?.slice(0, 3)" 
                            :key="genre.id"
                            class="px-3 py-1 bg-neutral-100 rounded-full text-lg text-neutral-600"
                        >
                            {{ genre.name }}
                        </span>
                        <span v-if="event.genres?.length > 3" class="text-neutral-500 text-lg text-neutral-600">
                            +{{ event.genres.length - 3 }} more
                        </span>
                        <span v-if="!event.genres?.length" class="text-neutral-500 text-lg text-neutral-600">
                            No genres selected
                        </span>
                    </div>
                </button>

                <!-- Location -->
                <button
                    @click="$emit('navigate', (props.event?.attendance_type_id === 1 || props.event?.hasLocation) ? 'Location' : 'Remote')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Location',
                            'border border-neutral-200': currentStep !== 'Location'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Location</h3>
                    <template v-if="props.event?.attendance_type_id === 1 || props.event?.hasLocation">
                        <div class="flex gap-4">
                            <div class="w-full h-60 rounded-lg overflow-hidden flex-shrink-0">
                                <l-map 
                                    ref="locationMapRef"
                                    :zoom="14" 
                                    :center="mapCenter"
                                    :style="{
                                        height: '100%',
                                        width: '100%',
                                        minHeight: isMobile ? '200px' : '240px'
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
                                    <l-tile-layer url="https://{s}.tile.jawg.io/jawg-sunny/{z}/{x}/{y}{r}.png?access-token=5Pwt4rF8iefMU4hIcRqZJ0GXPqWi5l4NVjEn4owEBKOdGyuJVARXbYTBDO2or3cU" />
                                    <l-marker 
                                        :lat-lng="mapCenter"
                                        :icon="icon"
                                    />
                                </l-map>
                            </div>
                            <div class="flex-1 min-w-0 justify-center flex flex-col">
                                <p class="text-neutral-600 mb-2 truncate">{{ props.event?.location?.city || 'No city set' }}</p>
                                <p class="text-neutral-500 text-lg truncate">{{ props.event?.location?.venue || 'No venue set' }}</p>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-neutral-600">Remote Event</p>
                    </template>
                </button>

                <!-- Description -->
                <button
                    @click="$emit('navigate', 'Description')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Description',
                            'border border-neutral-200': currentStep !== 'Description'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Description</h3>
                    <p class="text-neutral-600 line-clamp-2 break-words hyphens-auto">{{ event.description || 'No description set' }}</p>
                </button>

                <!-- Dates -->
                <button
                    @click="$emit('navigate', 'Dates')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Dates',
                            'border border-neutral-200': currentStep !== 'Dates'
                        }
                    ]"
                >
                    <div class="flex justify-between items-start">
                        <h3 class="text-xl font-semibold text-black">Dates</h3>
                    </div>
                    <p class="text-neutral-600 mt-4">
                        {{ typeof showsCount === 'string' ? showsCount : `${showsCount} shows total` }}
                    </p>
                    <p v-if="typeof showsCount === 'number'" class="text-neutral-500 text-sm">
                        {{ remainingShows }} upcoming
                    </p>
                </button>

                <!-- Tickets -->
                <button
                    @click="$emit('navigate', 'Tickets')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Tickets',
                            'border border-neutral-200': currentStep !== 'Tickets'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Tickets</h3>
                    <p class="text-neutral-600 mb-2">{{ ticketCount }} ticket types</p>
                    <p class="text-neutral-500 text-sm">{{ ticketPriceRange }}</p>
                </button>

                <!-- Images -->
                <button
                    @click="$emit('navigate', 'Images')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Images',
                            'border border-neutral-200': currentStep !== 'Images'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Images</h3>
                    <div class="flex gap-4">
                        <picture v-if="firstImagePath" class="w-16 h-16 flex-shrink-0">
                            <source 
                                :srcset="firstImagePath"
                                type="image/webp"
                            >
                            <img 
                                :src="firstImagePath.replace('.webp', '.jpg')"
                                class="w-16 h-16 rounded-lg object-cover"
                                alt="Event image"
                            />
                        </picture>
                        <div class="flex-1 min-w-0 justify-center flex flex-col">
                            <p class="text-neutral-600">{{ imageCount }} images</p>
                            <p class="text-neutral-600">{{ videoCount }} videos</p>
                        </div>
                    </div>
                </button>

                <!-- Advisories -->
                <button
                    @click="$emit('navigate', 'Advisories')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Advisories',
                            'border border-neutral-200': currentStep !== 'Advisories'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Advisories</h3>
                    <div class="space-y-4">
                        <!-- Interactive Level -->
                        <div>
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-if="event.interactive_level"
                                    class="px-3 py-1 bg-neutral-100 rounded-full text-lg text-neutral-600"
                                >
                                    {{ event.interactive_level.name }}
                                </span>
                                <span v-else class="text-neutral-500 text-sm text-neutral-600">
                                    No interaction level set
                                </span>
                            </div>
                        </div>

                        <!-- Contact Levels -->
                        <div>
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="contact in event.contact_levels?.slice(0, 3)" 
                                    :key="contact.id"
                                    class="px-3 py-1 bg-neutral-100 rounded-full text-lg text-neutral-600"
                                >
                                    {{ contact.name }}
                                </span>
                                <span v-if="event.contact_levels?.length > 3" class="text-neutral-500 text-sm text-neutral-600">
                                    +{{ event.contact_levels.length - 3 }} more
                                </span>
                                <span v-if="!event.contact_levels?.length" class="text-neutral-500 text-sm text-neutral-600">
                                    No contact levels set
                                </span>
                            </div>
                        </div>
                    </div>
                </button>

                <!-- Content Advisories -->
                <button
                    @click="$emit('navigate', 'Content')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Content',
                            'border border-neutral-200': currentStep !== 'Content'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Content Advisories</h3>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-for="content in event.content_advisories?.slice(0, 3)" 
                            :key="content.id"
                            class="px-3 py-1 bg-neutral-100 rounded-full text-lg text-neutral-600"
                        >
                            {{ content.name }}
                        </span>
                        <span v-if="event.content_advisories?.length > 3" class="text-neutral-500 text-lg text-neutral-600">
                            +{{ event.content_advisories.length - 3 }} more
                        </span>
                        <span v-if="!event.content_advisories?.length" class="text-neutral-500 text-lg text-neutral-600">
                            No content advisories selected
                        </span>
                    </div>
                </button>

                <!-- Mobility -->
                <button
                    @click="$emit('navigate', 'Mobility')"
                    :class="[
                        'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200 overflow-hidden',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Mobility',
                            'border border-neutral-200': currentStep !== 'Mobility'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4 text-black">Mobility</h3>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-for="mobility in event.mobility_advisories?.slice(0, 3)" 
                            :key="mobility.id"
                            class="px-3 py-1 bg-neutral-100 rounded-full text-lg text-neutral-600"
                        >
                            {{ mobility.name }}
                        </span>
                        <span v-if="event.mobility?.length > 3" class="text-neutral-500 text-lg text-neutral-600">
                            +{{ event.mobility.length - 3 }} more
                        </span>
                        <span v-if="!event.mobility_advisories?.length" class="text-neutral-500 text-lg text-neutral-600">
                            No mobility options selected
                        </span>
                    </div>
                </button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed, ref } from 'vue';
import { LMap, LTileLayer, LMarker } from "@vue-leaflet/vue-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { formatPrice } from '@/composables/useCurrency';
import { savedTiers } from '@/composables/useEventTickets';
import { isShowUpcoming, showHistoryDays, usesCurtainTimes } from '@/composables/useShowDates';

const props = defineProps({
    event: {
        type: Object,
        required: true
    },
    currentStep: {
        type: String,
        default: null
    }
});

const isMobile = computed(() => window?.Laravel?.isMobile ?? false);

const imageUrl = import.meta.env.VITE_IMAGE_URL;

const firstImagePath = computed(() => {
    const firstImage = props.event.images?.[0];
    if (!firstImage) return null;
    
    // Use thumb_image_path for the sidebar (it's smaller and more appropriate)
    const path = firstImage.thumb_image_path;
    return path ? `${imageUrl}${path}` : null;
});

const categoryImagePath = computed(() => {
    // First try to get image from event->category->images
    if (props.event.category?.images?.[0]?.path) {
        return `${imageUrl}${props.event.category.images[0].path}`;
    }
    // Fallback to thumbImagePath
    if (props.event.category?.thumbImagePath) {
        return `${imageUrl}${props.event.category.thumbImagePath}`;
    }
    return null;
});

// Computed properties for formatting
const formatShowType = computed(() => {
    switch(props.event.showtype) {
        case 's': return 'Single Show';
        case 'a': return 'Always Available';
        case 'o': return 'On Request';
        default: return 'No show type set';
    }
});

const formatShowDates = computed(() => {
    if (!props.event.dateArray?.length) return 'No dates set';
    if (props.event.dateArray.length === 1) {
        return new Date(props.event.dateArray[0]).toLocaleDateString();
    }
    return `${props.event.dateArray.length} dates scheduled`;
});

const savedTickets = computed(() => savedTiers(props.event));

const ticketCount = computed(() => savedTickets.value.length);

const ticketPriceRange = computed(() => {
    const tickets = savedTickets.value;
    if (!tickets.length) return 'No tickets set';

    const prices = tickets.map(t => parseFloat(t.ticket_price));
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    const currency = tickets[0].currency;

    if (min === max) return formatPrice(min, currency);
    return `${formatPrice(min, currency)} - ${formatPrice(max, currency)}`;
});

const imageCount = computed(() => {
    return props.event.images?.length || 0;
});

const videoCount = computed(() => {
    return props.event.videos?.length || 0;
});

const categoryName = computed(() => {
    return props.event.category?.name || 'No category set';
});

const locationMapRef = ref(null);

const mapCenter = computed(() => {
    if (!props.event?.location?.latitude || !props.event?.location?.longitude) {
        return [40.7127753, -74.0059728];
    }
    return [props.event.location.latitude, props.event.location.longitude];
});

const icon = L.divIcon({
    className: 'custom-div-icon',
    html: `
        <div style="
            width: 16px;
            height: 16px;
            background: #ff385c;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 4px #ff385c;
        "></div>
    `,
    iconSize: [12, 12],
    iconAnchor: [6, 6]
});

const onMapReady = () => {
    setTimeout(() => {
        if (locationMapRef.value?.leafletObject) {
            locationMapRef.value.leafletObject.invalidateSize();
        }
    }, 100);
};

const showsCount = computed(() => {
    if (props.event.showtype === 'a') return 'Anytime';
    // Plus the days more than a year old kept in the compact show history.
    const rows = props.event.shows ? Object.keys(props.event.shows).length : 0;
    return rows + showHistoryDays(props.event.show_history).length;
});

const remainingShows = computed(() => {
    if (props.event.showtype === 'a') return '';
    if (!props.event.shows) return 0;
    // By the show's day where the event is (composables/useShowDates.js),
    // not the browser's reading of the raw UTC string.
    const now = new Date();
    const curtainTimes = usesCurtainTimes(props.event.shows);
    const futureShows = props.event.shows.filter(show => isShowUpcoming(show.date, props.event.timezone, now, curtainTimes));
    return futureShows.length;
});

const goBack = () => {
    window.history.back()
};


defineEmits(['navigate']);
</script>

<style>
@import 'leaflet/dist/leaflet.css';

/* Mobile-specific styles */
@media (max-width: 767px) {
    .fixed-nav {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow-y: auto;
        background: white;
        z-index: 40;
    }
}

/* Adjust spacing for mobile */
@media (max-width: 767px) {
    .space-y-6 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1rem;
    }
}
</style>