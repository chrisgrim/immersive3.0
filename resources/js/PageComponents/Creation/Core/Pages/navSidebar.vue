<template>
    <!-- Mobile Back Button (shown only when a section is active) -->
    <div 
        v-if="isMobile && activeSection" 
        class="fixed top-0 left-0 right-0 z-50 bg-white border-b p-4"
    >
        <div class="flex items-center gap-4">
            <button 
                @click="$emit('navigate', null)"
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
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
            <h2 class="text-xl font-semibold">{{ activeSection }}</h2>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="flex-shrink-0 space-y-8 w-full p-8 mx-auto mb-20 lg-air:max-w-[40rem] pt-12 lg-air:pt-28">
        <!-- Header with back button (hidden on mobile when section is active) -->
        <div 
            v-if="!isMobile || !activeSection"
            class="w-full flex items-center gap-4 pb-8"
        >
            <a 
                href="/hosting/events" 
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors flex-shrink-0"
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
            </a>
            <a href="/hosting/events" class="ml-4 text-5xl font-semibold truncate">Listings</a>
        </div>

        <!-- Name & Tag Line -->
        <div 
            @click="$emit('navigate', 'Name')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 w-full max-w-full"
        >
            <h3 class="text-xl font-semibold mb-4">Name</h3>
            <p class="text-gray-600 mb-2 leading-tight break-words hyphens-auto overflow-hidden">{{ event.name || 'No name set' }}</p>
            <p class="text-gray-500 text-xl leading-tight break-words hyphens-auto overflow-hidden">{{ event.tag_line || 'No tagline set' }}</p>
        </div>

        <!-- Category -->
        <div 
            @click="$emit('navigate', 'Category')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Category</h3>
            <div class="flex gap-4">
                <img 
                    v-if="categoryImagePath"
                    :src="categoryImagePath"
                    class="w-16 h-16 rounded-lg object-cover flex-shrink-0"
                    :alt="categoryName"
                />
                <div class="flex-1 min-w-0 justify-center flex flex-col">
                    <p class="text-gray-600 mb-2 truncate">{{ categoryName }}</p>
                </div>
            </div>
        </div>

        <!-- Genres -->
        <div 
            @click="$emit('navigate', 'Genres')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Genres</h3>
            <div class="flex flex-wrap gap-2">
                <span 
                    v-for="genre in event.genres?.slice(0, 3)" 
                    :key="genre.id"
                    class="px-3 py-1 bg-gray-100 rounded-full text-lg"
                >
                    {{ genre.name }}
                </span>
                <span v-if="event.genres?.length > 3" class="text-gray-500 text-lg">
                    +{{ event.genres.length - 3 }} more
                </span>
                <span v-if="!event.genres?.length" class="text-gray-500 text-lg">
                    No genres selected
                </span>
            </div>
        </div>

        <!-- Location -->
        <div 
            @click="$emit('navigate', props.event?.hasLocation ? 'Location' : 'Remote')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Location</h3>
            <template v-if="props.event?.hasLocation">
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
                        <p class="text-gray-600 mb-2 truncate">{{ props.event?.location?.city || 'No city set' }}</p>
                        <p class="text-gray-500 text-lg truncate">{{ props.event?.location?.venue || 'No venue set' }}</p>
                    </div>
                </div>
            </template>
            <template v-else>
                <p class="text-gray-600">Remote Event</p>
            </template>
        </div>

        <!-- Description -->
        <div 
            @click="$emit('navigate', 'Description')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Description</h3>
            <p class="text-gray-600 line-clamp-2 break-words hyphens-auto">{{ event.description || 'No description set' }}</p>
        </div>

        <!-- Dates -->
        <div 
            @click="$emit('navigate', 'Dates')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <div class="flex justify-between items-start">
                <h3 class="text-xl font-semibold">Dates</h3>
            </div>
            <p class="text-gray-600 mt-4">
                {{ typeof showsCount === 'string' ? showsCount : `${showsCount} shows total` }}
            </p>
            <p v-if="typeof showsCount === 'number'" class="text-gray-500 text-sm">
                {{ remainingShows }} upcoming
            </p>
        </div>

        <!-- Tickets -->
        <div 
            @click="$emit('navigate', 'Tickets')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Tickets</h3>
            <p class="text-gray-600 mb-2">{{ ticketCount }} ticket types</p>
            <p class="text-gray-500 text-sm">{{ ticketPriceRange }}</p>
        </div>

        <!-- Images -->
        <div 
            @click="$emit('navigate', 'Images')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Images</h3>
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
                    <p class="text-gray-600">{{ imageCount }} images</p>
                </div>
            </div>
        </div>

        <!-- Advisories -->
        <div 
            @click="$emit('navigate', 'Advisories')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Advisories</h3>
            <div class="space-y-4">
                <!-- Interactive Level -->
                <div>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-if="event.interactive_level"
                            class="px-3 py-1 bg-gray-100 rounded-full text-lg"
                        >
                            {{ event.interactive_level.name }}
                        </span>
                        <span v-else class="text-gray-500 text-sm">
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
                            class="px-3 py-1 bg-gray-100 rounded-full text-lg"
                        >
                            {{ contact.name }}
                        </span>
                        <span v-if="event.contact_levels?.length > 3" class="text-gray-500 text-sm">
                            +{{ event.contact_levels.length - 3 }} more
                        </span>
                        <span v-if="!event.contact_levels?.length" class="text-gray-500 text-sm">
                            No contact levels set
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Advisories -->
        <div 
            @click="$emit('navigate', 'Content')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Content Advisories</h3>
            <div class="flex flex-wrap gap-2">
                <span 
                    v-for="content in event.content_advisories?.slice(0, 3)" 
                    :key="content.id"
                    class="px-3 py-1 bg-gray-100 rounded-full text-lg"
                >
                    {{ content.name }}
                </span>
                <span v-if="event.content_advisories?.length > 3" class="text-gray-500 text-lg">
                    +{{ event.content_advisories.length - 3 }} more
                </span>
                <span v-if="!event.content_advisories?.length" class="text-gray-500 text-lg">
                    No content advisories selected
                </span>
            </div>
        </div>

        <!-- Mobility -->
        <div 
            @click="$emit('navigate', 'Mobility')"
            class="p-8 border shadow-custom-1 rounded-3xl cursor-pointer hover:bg-gray-50 overflow-hidden"
        >
            <h3 class="text-xl font-semibold mb-4">Mobility</h3>
            <div class="flex flex-wrap gap-2">
                <span 
                    v-for="mobility in event.mobility_advisories?.slice(0, 3)" 
                    :key="mobility.id"
                    class="px-3 py-1 bg-gray-100 rounded-full text-lg"
                >
                    {{ mobility.name }}
                </span>
                <span v-if="event.mobility?.length > 3" class="text-gray-500 text-lg">
                    +{{ event.mobility.length - 3 }} more
                </span>
                <span v-if="!event.mobility_advisories?.length" class="text-gray-500 text-lg">
                    No mobility options selected
                </span>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed, ref } from 'vue';
import { LMap, LTileLayer, LMarker } from "@vue-leaflet/vue-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const props = defineProps({
    event: {
        type: Object,
        required: true
    },
    isMobile: {
        type: Boolean,
        default: false
    },
    activeSection: {
        type: String,
        default: null
    }
});

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

const ticketCount = computed(() => {
    return props.event.shows?.[0]?.tickets?.length || 0;
});

const ticketPriceRange = computed(() => {
    const tickets = props.event.shows?.[0]?.tickets || [];
    if (!tickets.length) return 'No tickets set';
    
    const prices = tickets.map(t => parseFloat(t.ticket_price));
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    
    if (min === max) return `${tickets[0].currency}${min.toFixed(2)}`;
    return `${tickets[0].currency}${min.toFixed(2)} - ${tickets[0].currency}${max.toFixed(2)}`;
});

const imageCount = computed(() => {
    return props.event.images?.length || 0;
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
    return props.event.shows ? Object.keys(props.event.shows).length : 0;
});

const remainingShows = computed(() => {
    if (props.event.showtype === 'a') return '';
    if (!props.event.shows) return 0;
    const now = new Date();
    const futureShows = props.event.shows.filter(show => {
        return new Date(show.date) >= now;
    });
    return futureShows.length;
});

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