<template>
  <div>
    <!-- Add the debugging indicator showing which view we're in -->
    <div v-if="debugMode" class="fixed top-0 right-0 bg-red-500 text-white p-2 z-50">
      Current View: {{ searchType }} | 
      <button @click="toggleView" class="underline">Toggle View</button>
    </div>

    <!-- Conditionally render the right component based on searchType -->
    <Location 
      v-if="searchType === 'inPerson'"
      :searched-events="searchedEvents"
      :categories="categories"
      :user="user"
      :tags="tags"
      :mobile="mobile"
      :max-price="maxPrice"
      :searched-categories="searchedCategories"
      :searched-tags="searchedTags"
      :in-person-categories="inPersonCategories"
    />
    <AllEvents 
      v-else
      :searched-events="searchedEvents"
      :categories="categories"
      :user="user"
      :tags="tags"
      :mobile="mobile"
      :max-price="maxPrice"
      :searched-categories="searchedCategories"
      :searched-tags="searchedTags"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import Location from '@/PageComponents/Search/location.vue';
import AllEvents from '@/PageComponents/Search/all.vue';
import eventStore from '@/Stores/EventStore';

// Props that come from the backend
const props = defineProps({
  searchedEvents: Object,
  categories: Array,
  user: Object,
  tags: Array,
  mobile: Boolean,
  searchedCategories: Array,
  searchedTags: Array,
  inPersonCategories: Array,
  maxPrice: {
    type: Number,
    default: 1000
  }
});

// For development/debugging - set to false in production
const debugMode = ref(true);

// Initialize searchType from URL or default to 'inPerson'
const searchType = ref('inPerson');

// Watch for changes to eventStore's searchType
const unsubscribe = ref(null);

// Function to toggle view (for debugging)
function toggleView() {
  const newType = searchType.value === 'inPerson' ? 'allEvents' : 'inPerson';
  searchType.value = newType;
  eventStore.setSearchType(newType);
}

onMounted(() => {
  // Initialize from URL
  const params = new URLSearchParams(window.location.search);
  const typeFromUrl = params.get('searchType') || 'inPerson';
  
  // Set the initial searchType
  searchType.value = typeFromUrl;
  
  // Log for debugging
  console.log('Search.vue mounted with searchType:', typeFromUrl);
  
  // Subscribe to eventStore changes to keep searchType in sync
  unsubscribe.value = eventStore.subscribe(state => {
    if (state.location.searchType !== searchType.value) {
      console.log('SearchType changed in store:', state.location.searchType);
      searchType.value = state.location.searchType;
    }
  });
  
  // Ensure the eventStore has the correct initial searchType
  eventStore.setSearchType(typeFromUrl);
  
  // Set up a MutationObserver to detect URL changes
  const observer = new MutationObserver(() => {
    const params = new URLSearchParams(window.location.search);
    const newType = params.get('searchType') || 'inPerson';
    
    if (newType !== searchType.value) {
      console.log('URL searchType changed:', newType);
      searchType.value = newType;
      eventStore.setSearchType(newType);
    }
  });
  
  // Watch for changes to the URL
  watch(() => window.location.search, () => {
    const params = new URLSearchParams(window.location.search);
    const newType = params.get('searchType') || 'inPerson';
    
    if (newType !== searchType.value) {
      console.log('URL searchType changed (watcher):', newType);
      searchType.value = newType;
      eventStore.setSearchType(newType);
    }
  });
  
  // Also listen for popstate events (browser back/forward)
  window.addEventListener('popstate', () => {
    const params = new URLSearchParams(window.location.search);
    const newType = params.get('searchType') || 'inPerson';
    
    if (newType !== searchType.value) {
      console.log('URL searchType changed (popstate):', newType);
      searchType.value = newType;
      eventStore.setSearchType(newType);
    }
  });
});

// Clean up
onUnmounted(() => {
  if (unsubscribe.value) {
    unsubscribe.value();
  }
});
</script> 