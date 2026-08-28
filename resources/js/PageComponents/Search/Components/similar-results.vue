<template>
    <!-- The heading used to hang off similarEvents.length, so a search that
         matched nothing AND had no nearby events to suggest rendered a blank
         page — no explanation at all. It is unconditional now; only the
         suggestions below depend on there being suggestions. -->
    <div class="mt-8">
        <div class="mb-6">
            <h2 class="text-2.5xl text-black font-medium">
                Sorry, we couldn't find any events in {{ cityName }}
            </h2>

            <!-- When the filter panel is narrowing the search, that is almost
                 always the reason for the empty result, and "here are some
                 events nearby" buries it. Say the actionable thing instead. -->
            <p v-if="hasActiveFilters" class="text-xl text-gray-700 mt-2 leading-tight">
                Please remove filters to see more events
            </p>
            <p v-else-if="similarEvents.length" class="text-xl text-gray-700 mt-2 leading-tight">
                {{ isRemote
                    ? 'Here are some online events you might be interested in:'
                    : 'Here are some events in nearby areas you might be interested in:' }}
            </p>
        </div>

        <!-- Suggestions are fetched by location alone, ignoring the filters,
             so offering them under "remove your filters" would be answering a
             different question than the one just asked. -->
        <EventList
            v-if="similarEvents.length && !hasActiveFilters"
            :show-location="true"
            :items="similarEvents"
            :columns="columns"
            :user="user"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import EventList from '@/GlobalComponents/Grid/event-grid.vue'
import { useSearchFilters } from '@/composables/useSearchFilters'

// Same notion of "a filter is on" the results header uses for its summary
// line — category, genre or price, the three the filter panel owns.
const { hasActiveFilters } = useSearchFilters()

const props = defineProps({
  user: {
    type: Object,
    default: null
  },
  columns: {
    type: Number,
    default: 4
  }
})

const similarEvents = ref([])
const isLoading = ref(false)
const error = ref(null)
const isRemote = ref(false)
const cityName = ref('this location')

// Try to get city name from URL params
const extractCityFromUrl = () => {
  const params = new URLSearchParams(window.location.search)
  const city = params.get('city')
  
  if (city) {
    // For "City, State" format, just get the city part
    const parts = city.split(',')
    cityName.value = parts[0].trim()
  }
}

// Fetch similar events based on location
onMounted(async () => {
  isLoading.value = true
  
  // Extract city name from URL for the message
  extractCityFromUrl()
  
  try {
    // Get search parameters from URL
    const params = new URLSearchParams(window.location.search);
    const lat = params.get('lat');
    const lng = params.get('lng');
    
    // Call the similar-by-location endpoint
    const response = await axios.get(`/api/events/similar-by-location?lat=${lat}&lng=${lng}`);
    
    if (response?.data?.events) {
      similarEvents.value = response.data.events;
      
      // Set isRemote flag based on API response
      isRemote.value = response.data.isRemote || false;
    }
  } catch (err) {
    console.error(`Error fetching events:`, err);
    error.value = 'Could not load events';
  } finally {
    isLoading.value = false;
  }
});
</script>
