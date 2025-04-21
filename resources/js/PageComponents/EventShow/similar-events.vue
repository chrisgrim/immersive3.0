<template>
    <div v-if="similarEvents.length > 0" class="mt-12">
        <div class="justify-between flex">
            <div class="my-4 h-16">
                <div class="h-full flex items-center">
                    <h2 class="text-3xl md:text-4xl text-black font-medium mt-2">{{ title }}</h2>
                </div>
            </div>
            <div v-if="similarEvents.length >= 4" class="inline-flex items-center gap-2 invisible md:visible">
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
  const imageUrl = import.meta.env.VITE_IMAGE_URL
  const scrollContainer = ref(null)
  const cardWidth = ref(0)
  
  // Compute title based on location
  const title = computed(() => {
    if (props.event.hasLocation) {
      return `More events in ${props.event.location.city}`
    }
    return 'Similar events you might like'
  })
  
  
  onMounted(async () => {
    // Track component instances to detect duplicates
    if (!window._similarEventsInstances) window._similarEventsInstances = 0;
    window._similarEventsInstances++;
    
    console.log(`⏱️ Similar events component instance #${window._similarEventsInstances} mounted for event ${props.event.slug}`);
    console.time('similar-events-total')
    
    // Try up to 2 retries
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            console.time('similar-events-fetch')
            console.log(`⏱️ Starting API fetch for similar events (attempt ${attempt + 1})`)
            
            const response = await axios.get(`/api/events/${props.event.slug}/similar`)
            console.timeEnd('similar-events-fetch')
            console.log(`⏱️ Received ${response?.data?.length || 0} similar events`)
            
            if (response?.data?.length) {
                similarEvents.value = response.data
                break // Success, exit retry loop
            } else {
                console.warn('Received empty response from similar events API')
                
                // On the last attempt, try the client-side fallback
                if (attempt === 2) {
                    // Try client-side fallback for empty results
                    const localSimilarEvents = findSimilarEventsLocally()
                    if (localSimilarEvents.length > 0) {
                        console.log('⏱️ Using client-side fallback for empty API response')
                        similarEvents.value = localSimilarEvents
                    } else {
                        similarEvents.value = []
                    }
                }
            }
        } catch (err) {
            console.error(`Error fetching similar events (attempt ${attempt + 1}):`, err)
            
            // On last attempt, set error and try client-side fallback
            if (attempt === 2) {
                error.value = 'Could not load similar events'
                
                // Try client-side fallback as last resort
                const localSimilarEvents = findSimilarEventsLocally()
                if (localSimilarEvents.length > 0) {
                    similarEvents.value = localSimilarEvents
                    console.log('⏱️ Using client-side fallback for similar events')
                } else {
                    similarEvents.value = []
                }
            } else {
                // Wait before retry
                await new Promise(resolve => setTimeout(resolve, 500))
            }
        }
    }
    
    console.timeEnd('similar-events-total')
    console.log('⏱️ Similar events component fully loaded')
  })
  
  onUnmounted(() => {
    // Decrement instance count when component is unmounted
    if (window._similarEventsInstances) window._similarEventsInstances--;
    console.log(`⏱️ Similar events component unmounted, ${window._similarEventsInstances} instances remain`);
  })
  
  const findSimilarEventsLocally = () => {
    // This is a fallback method if the API fails
    // We'll look for events from the global window object if available
    
    console.log('⏱️ Attempting to find similar events on the client side')
    
    if (!window.allEvents || !window.allEvents.length) {
        console.log('⏱️ No global events available for client-side matching')
        return []
    }
    
    // Find events with the same category
    const sameCategorySimilar = window.allEvents.filter(e => 
        e.id !== props.event.id && 
        e.category_id === props.event.category_id
    ).slice(0, 6)
    
    // If we have enough category matches, use those
    if (sameCategorySimilar.length >= 3) {
        console.log(`⏱️ Found ${sameCategorySimilar.length} similar events by category on client side`)
        return sameCategorySimilar
    }
    
    // Otherwise, add some more events
    const otherEvents = window.allEvents
        .filter(e => e.id !== props.event.id && !sameCategorySimilar.some(s => s.id === e.id))
        .slice(0, 6 - sameCategorySimilar.length)
    
    const combined = [...sameCategorySimilar, ...otherEvents]
    console.log(`⏱️ Found ${combined.length} similar events on client side (${sameCategorySimilar.length} by category)`)
    
    return combined
  }
  
  const formatDate = (dateString) => {
    if (!dateString) return ''
    const options = { month: 'long', day: 'numeric', year: 'numeric' }
    return new Date(dateString).toLocaleDateString('en-US', options)
  }
  
  const getEventImage = (event) => {
    console.log('Event image data:', { 
        event: event,
        event_id: event.id,
        has_images: !!event.images?.length,
        image_data: event.thumbImagePath
    })
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
    
    console.warn(`No image found for event ${event.id} (${event.name})`);
    return null;
  }
  
  const getEventLocation = (event) => {
    if (!event.hasLocation && event.remotelocations && event.remotelocations.length > 0) {
      return event.remotelocations[0].name || 'Remote Event'
    } else if (event.location) {
      if (event.location.country === 'United States') {
        return `${event.location.city}, ${event.location.region}`
      }
      return `${event.location.city}, ${event.location.country}`
    }
    return 'Location not specified'
  }
  
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
  
  const getImageSrc = (event) => {
    const imagePath = getEventImage(event);
    
    // If it's already a full URL or starts with /, return as is
    if (!imagePath || imagePath.startsWith('http') || imagePath.startsWith('/')) {
        return imagePath;
    }
    
    // Otherwise, prepend the image URL
    return `${imageUrl}${imagePath}`;
  }
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