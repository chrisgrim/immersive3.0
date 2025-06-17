<template>
    <div v-if="similarEvents.length > 0" class="md:mt-12">
        <div class="justify-between flex">
            <div class="my-4 h-16">
                <div class="h-full flex items-center">
                    <h2 class="text-2.5xl text-black font-medium mt-2">{{ title }}</h2>
                </div>
            </div>
            <div v-if="similarEvents.length >= 1" class="inline-flex items-center gap-2 invisible md:visible">
                <button 
                    aria-label="Scroll Left"
                    class="rounded-full w-14 h-14 border border-gray-300 p-0 bg-white hover:shadow-md transition-shadow" 
                    @click="scrollLeft">
                    <svg 
                    xmlns="http://www.w3.org/2000/svg" 
                    viewBox="0 0 24 24" 
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                    class="w-2/4 h-full m-auto"
                    >
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>
                <button 
                aria-label="Scroll Right"
                class="rounded-full w-14 h-14 border border-gray-300 p-0 bg-white hover:shadow-md transition-shadow" 
                @click="scrollRight">
                    <svg 
                    xmlns="http://www.w3.org/2000/svg" 
                    viewBox="0 0 24 24" 
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                    class="w-2/4 h-full m-auto"
                    >
                        <path d="M9 18l6-6-6-6" />
                    </svg>
                    </button>
            </div>
        </div>
        
        
  
        <div class="overflow-y-hidden overflow-x-auto whitespace-nowrap scrollbar-hide">
            <div ref="scrollContainer" 
            class="overflow-x-auto flex scrollbar-hide" 
            style="scroll-snap-type: x mandatory;">
            <div v-for="similarEvent in similarEvents"
               :key="similarEvent.id"
               class="ml-10 first:ml-0 snap-start snap-always w-[23rem]">
                <a :href="`/events/${similarEvent.slug}`" class="block w-full pb-16">
                <div class="rounded-2xl overflow-hidden h-full border border-gray-300" style="width: 23rem;">
                    <div class="w-full relative" style="height:30.67rem">
                        <img 
                        class="h-full w-full object-cover"
                        loading="lazy" 
                        :src="getImageSrc(similarEvent)"
                        :alt="similarEvent.name">
                  
                        <!-- Location Tag -->
                        <div class="absolute bottom-4 left-4 bg-black bg-opacity-70 text-white px-3 py-1 rounded-full text-sm">
                        {{ getEventLocation(similarEvent) }}
                        </div>
                    </div>
                
                    <div class="text-left bg-white p-6">
                        <h3 class="text-2xl font-medium text-black line-clamp-2">{{ similarEvent.name }}</h3>
                  
                        <!-- Dates if available -->
                        <p v-if="similarEvent.shows && similarEvent.shows.length > 0" class="text-sm text-neutral-600 mt-2">
                        {{ formatDate(similarEvent.shows[0].date) }}
                        </p>
                  
                        <!-- Tag line or description -->
                        <p class="text-sm text-neutral-700 mt-2 line-clamp-2">
                        {{ similarEvent.tag_line || similarEvent.description }}
                  </p>
                </div>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted, onUnmounted, computed } from 'vue'
  
  const props = defineProps({
    event: {
      type: Object,
      required: true
    }
  })
  
  const similarEvents = ref([])
  const isLoading = ref(false)
  const error = ref(null)
  const isSameCity = ref(false)
  const scrollContainer = ref(null)
  const imageUrl = import.meta.env.VITE_IMAGE_URL
  
  const title = computed(() => {
    // If the event has a location and we found events in the same city
    if (props.event.hasLocation && isSameCity.value && props.event.location && props.event.location.city) {
      return `More events in ${props.event.location.city}`
    }
    
    // If we found events but they're not from the same city as the current event
    if (props.event.hasLocation && !isSameCity.value) {
      return 'Other events you might enjoy'
    }
    
    // Default for online/remote events
    return 'Similar online events'
  })
  
  const scrollLeft = () => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollBy({
        left: -scrollContainer.value.offsetWidth,
        behavior: 'smooth'
      })
    }
  }
  
  const scrollRight = () => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollBy({
        left: scrollContainer.value.offsetWidth,
        behavior: 'smooth'
      })
    }
  }
  
  const formatDate = (dateString) => {
    if (!dateString) return ''
    const options = { month: 'long', day: 'numeric', year: 'numeric' }
    return new Date(dateString).toLocaleDateString('en-US', options)
  }
  
  const getEventLocation = (event) => {
    // For remote events - match blade template logic
    if (!event.hasLocation) {
      // Check for remoteLocations (capital L) first, then fallback to remotelocations (lowercase)
      const remoteLocations = event.remoteLocations || event.remotelocations
      if (remoteLocations && remoteLocations.length > 0) {
        const locationName = remoteLocations[0].name || 'Remote Event'
        return locationName.charAt(0).toUpperCase() + locationName.slice(1)
      }
      return 'Remote Event'
    }
    
    // For events with location object - match blade template logic
    if (event.location) {
      const city = event.location.city ? event.location.city.charAt(0).toUpperCase() + event.location.city.slice(1) : ''
      
      if (event.location.country === 'United States' || event.location.country === 'US') {
        return `${city}, ${event.location.region}`
      }
      return `${city}, ${event.location.country_long || event.location.country}`
    }
    
    // For events with location_latlon (from API)
    if (event.location_latlon && typeof event.location_latlon === 'object') {
      if (event.location_latlon.city) {
        const city = event.location_latlon.city.charAt(0).toUpperCase() + event.location_latlon.city.slice(1)
        
        if (event.location_latlon.country === 'United States' || event.location_latlon.country === 'US') {
          return `${city}, ${event.location_latlon.region || ''}`
        }
        return `${city}, ${event.location_latlon.country || ''}`
      }
    }
    
    return 'Location details on event page'
  }
  
  const getEventImage = (event) => {
    // Check for images array first
    if (event.images && event.images.length > 0) {
      return event.images[0].large_image_path || event.images[0].thumb_image_path;
    }
    
    // Fall back to direct image paths if available (these are more commonly available from the API)
    if (event.thumbImagePath) {
      return event.thumbImagePath;
    }
    
    if (event.largeImagePath) {
      return event.largeImagePath;
    }
    
    // Final fallbacks for other possible formats
    if (event.thumb_image_path) {
      return event.thumb_image_path;
    }
    
    if (event.large_image_path) {
      return event.large_image_path;
    }
    
    return null;
  }
  
  const getImageSrc = (event) => {
    const imagePath = getEventImage(event);
    
    // If it's already a full URL or starts with /, return as is
    if (!imagePath || imagePath.startsWith('http') || imagePath.startsWith('/')) {
        return imagePath;
    }
    
    // Otherwise, prepend the image URL
    return `${imageUrl}${imagePath}`;
  }
  
  const findSimilarEventsLocally = () => {
    if (!window.allEvents || !window.allEvents.length) {
        return []
    }
    
    // Find events with the same category
    const sameCategorySimilar = window.allEvents.filter(e => 
        e.id !== props.event.id && 
        e.category_id === props.event.category_id
    ).slice(0, 6)
    
    // If we have enough category matches, use those
    if (sameCategorySimilar.length >= 3) {
        return sameCategorySimilar
    }
    
    // Otherwise, add some more events
    const otherEvents = window.allEvents
        .filter(e => e.id !== props.event.id && !sameCategorySimilar.some(s => s.id === e.id))
        .slice(0, 6 - sameCategorySimilar.length)
    
    return [...sameCategorySimilar, ...otherEvents]
  }
  
  onMounted(async () => {
    isLoading.value = true
    
    try {
      const response = await axios.get(`/api/events/${props.event.slug}/similar`)
      
      // Check if we have the new response format
      if (response?.data?.events) {
        similarEvents.value = response.data.events
        isSameCity.value = response.data.isSameCity
      } else if (Array.isArray(response?.data)) {
        // Handle old format for backward compatibility
        similarEvents.value = response.data
      } else {
        // Try client-side fallback for empty results
        similarEvents.value = findSimilarEventsLocally()
      }
    } catch (err) {
      console.error(`Error fetching similar events:`, err)
      error.value = 'Could not load similar events'
      
      // Try client-side fallback
      similarEvents.value = findSimilarEventsLocally()
    } finally {
      isLoading.value = false
    }
  })
  
  onUnmounted(() => {
    // Decrement instance count when component is unmounted
    if (window._similarEventsInstances) window._similarEventsInstances--;
  })
  </script>
  
  <style scoped>
  .scrollbar-hide {
    -ms-overflow-style: none;  /* Internet Explorer and Edge */
    scrollbar-width: none;  /* Firefox */
  }
  
  .scrollbar-hide::-webkit-scrollbar {
    display: none;  /* Chrome, Safari, and Opera */
  }
  
  .line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  </style> 