<template>
    <div class="event-search relative min-h-[calc(100vh-7rem)]">
        <div class="w-full flex">
            <!-- Events List Section -->
            <section
                :class="{ 'w-0 hidden' : isFullMap }"
                class="z-10 relative inline-block w-[59%] max-[1200px]:w-[50%] min-h-[calc(100vh-8rem)]"
            >
                <div class="px-8 pt-16">
                    <!-- See all.vue: gated on hasEvents so the count and the
                         empty state can never appear together. -->
                    <results-header v-if="hasEvents" :total="events.total" />
                </div>
                
                <div class="px-8">
                    <EventList
                        v-if="hasEvents"
                        :items="events.data"
                        :user="user"
                        :columns="gridColumns"
                    />
                    <ShowMoreResults
                        v-if="hasEvents"
                        class="mt-6"
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
                        :columns="gridColumns"
                        :noResultsMode="true"
                        class="mt-12 mb-10"
                    />
                </div>
            </section>

            <!-- Map Component. It draws `pins` — every match in the search —
                 not the current page. No :key either: the map is never
                 remounted, so a page change leaves it where the user panned. -->
            <Map
                v-model="isFullMap"
                :pins="pins"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import ShowMoreResults from './Components/show-more-results.vue'
import Map from './Components/map.vue'
import ResultsHeader from './Components/results-header.vue'
import SimilarResults from './Components/similar-results.vue'
import { useSearchResults } from '@/composables/useSearchResults'

const props = defineProps({
    searchedEvents: Object,
    user: Object,
    // The initial map markers: every match under the current filters, each
    // slimmed to what a marker draws (Event::mapPins()), capped server-side.
    pins: { type: Array, default: () => [] }
})

const isFullMap = ref(false)

const { events, pins, loading, hasEvents, handleShowMore } = useSearchResults({
    searchedEvents: props.searchedEvents,
    pins: props.pins,
})

// Below ~1200px the list column narrows (see the matching max-[1200px]:w-[50%]
// on this section and on Map.vue) to make room for the map, which no longer
// leaves enough width for 4 event cards per row — drop to 3 in lockstep so
// the two changes always land at the same breakpoint.
const windowWidth = ref(window.innerWidth)
const updateWindowWidth = () => { windowWidth.value = window.innerWidth }
const gridColumns = computed(() => windowWidth.value <= 1200 ? 3 : 4)

onMounted(() => window.addEventListener('resize', updateWindowWidth))
onUnmounted(() => window.removeEventListener('resize', updateWindowWidth))
</script>