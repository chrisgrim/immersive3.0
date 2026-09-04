<template>
    <div class="event-search relative">
        <div class="w-full md:flex">
            <!-- Map Component (Background) -->
            <div class="fixed top-0 left-0 right-0 bottom-[40vh] transition-all duration-300 ease-in-out"
                 :class="isFullMap ? 'z-[49]' : 'z-[48]'">
                <!-- `pins` is every match in the search, not the current page.
                     :key stays only for the full-map toggle's remount below;
                     a page change no longer bumps it. -->
                <Map
                    v-model="isFullMap"
                    :key="mapKey"
                    :pins="pins"
                    :full-map="isFullMap"
                    :popup-clearance="popupClearance"
                />
            </div>

            <div 
                :style="{ height: `${mapHeight}px` }"
                @click="showFullMap"
                class="w-full relative z-[49] transition-all duration-300"
                :class="isFullMap ? 'opacity-0 pointer-events-none' : ''" />

            <!-- Events List Section -->
            <div 
                ref="panel"
                :style="{
                    transform: isFullMap 
                        ? `translate3d(0, ${mapHeight}px, 0)` 
                        : 'translate3d(0, 0px, 0)'
                }"
                class="min-h-[64vh] relative w-full z-49 transition-transform duration-300">
                <div class="bg-white rounded-t-5xl shadow-custom-6">
                    <div 
                        @click="hideFullMap"
                        class="w-full flex items-center gap-4 pt-4 h-28 relative before:content-[''] before:block before:absolute before:top-6 before:left-1/2 before:-translate-x-5 before:w-16 before:h-2 before:rounded-full before:bg-[#351b1b7a]">
                        <p class="text-black text-1xl font-medium w-full text-center" v-if="hasEvents">{{ events.total }} immersive events.</p>
                        <p class="text-black text-1xl font-medium w-full text-center" v-else>No events found.</p>
                    </div>
                    <div class="p-8 mt-[-1rem] overflow-y-auto overflow-x-hidden gap-x-6 scrolling-touch min-h-[64vh]">
                        <EventList
                            v-if="hasEvents"
                            :items="events.data"
                            :user="user"
                            :columns="2"
                        />
                        <ShowMoreResults
                            v-if="hasEvents"
                            class="mt-6 mb-8"
                            :shown="events.data.length"
                            :total="events.total"
                            :has-more="events.has_more"
                            :limit-reached="events.limit_reached"
                            :loading="loading"
                            @more="handleShowMore"
                        />
                        
                        <SimilarResults 
                            v-if="!hasEvents"
                            :event="{id: 0, slug: 'placeholder'}"
                            :user="user"
                            :columns="2"
                            :noResultsMode="true"
                            class="mt-12 mb-10"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import ShowMoreResults from './Components/show-more-results.vue'
import Map from './Components/map-mobile.vue'
import SimilarResults from './Components/similar-results.vue'
import { useSearchResults } from '@/composables/useSearchResults'

const props = defineProps({
    searchedEvents: Object,
    user: Object,
    // See location.vue: the initial markers, every match, slimmed.
    pins: { type: Array, default: () => [] }
})

// mapKey remounts the map when the full-map toggle resizes it
// (showFullMap/hideFullMap); Show more never touches it.
const mapKey = ref(0)
const isFullMap = ref(false)

const { events, pins, loading, hasEvents, handleShowMore } = useSearchResults({
    searchedEvents: props.searchedEvents,
    pins: props.pins,
})

const panel = ref(null)
const mapHeight = ref(0)
// See measurePanel(): px from the bottom of the screen the map's popup must
// keep clear so it sits above the list panel's header.
const popupClearance = ref(70)

// Where the list panel's top edge will sit once the full map is open, as a
// clearance from the bottom of the screen. The panel slides down by
// mapHeight from its place in the flow, and that place includes whatever
// sits above it (the nav), so it is measured rather than assumed. From the
// list state the transform hasn't been applied yet, so the slide is added;
// in the full-map state the rect already is the on-screen position.
const measurePanel = () => {
    if (!panel.value) return
    const top = panel.value.getBoundingClientRect().top + (isFullMap.value ? 0 : mapHeight.value)
    popupClearance.value = Math.max(0, Math.round(window.innerHeight - top))
}

const showFullMap = () => {
    window.scrollTo(0, 0);
    measurePanel();
    isFullMap.value = true;
    document.body.classList.add('noscroll');
    mapKey.value += 1;
}

const hideFullMap = () => {
    isFullMap.value = false;
    document.body.classList.remove('noscroll');
    mapKey.value += 1;
}

const handleResize = () => {
    mapHeight.value = window.innerHeight * 0.4;
    // The panel's new height and slide land over the 300ms transition;
    // measuring synchronously here reads the old geometry on a rotation.
    setTimeout(measurePanel, 350);
}

onMounted(() => {
    mapHeight.value = window.innerHeight * 0.4;
    window.addEventListener('resize', handleResize);
})

onUnmounted(() => window.removeEventListener('resize', handleResize))
</script>
<style scoped>
.event-search {
    touch-action: pan-y;
}

/* Allow scrolling within the content area */
.overflow-y-auto {
    -webkit-overflow-scrolling: touch;
    touch-action: pan-y pinch-zoom;
}
</style>
