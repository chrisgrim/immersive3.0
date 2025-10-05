<template>
  <div class="videos-component">
    <div class="flex items-center justify-between mb-4">
      <p class="text-2xl">Add youtube or tiktok videos (optional)</p>
      
      <div v-if="modelValue.length > 0" class="flex items-center gap-3">
        <span class="text-gray-600">Show on:</span>
        <ToggleSwitch
          v-model="localShowInSlideshow"
          leftLabel="Page"
          rightLabel="Gallery"
          @update:modelValue="updateShowInSlideshow"
        />
      </div>
    </div>
    
    <!-- Video List -->
    <div v-if="modelValue.length > 0" class="mb-8 space-y-6">
        <draggable 
          v-model="localVideos" 
          class="space-y-6"
          handle=".video-handle"
          item-key="id"
          @change="handleSort"
        >
            <template #item="{element, index}">
                <div 
                :key="index" 
                class="bg-black rounded-xl relative group overflow-visible">
                    <div class="absolute z-10 left-[-4rem] flex flex-col items-center justify-center gap-0 p-2 bg-black text-white rounded-xl border video-handle cursor-move">
                        <div @click.stop="moveVideo(index, -1)" class="cursor-pointer p-1 rounded transition-colors hover:bg-white hover:text-black">
                            <component :is="RiArrowUpSLine" class="w-8 h-8" />
                        </div>
                        <div @click.stop="moveVideo(index, 1)" class="cursor-pointer p-1 rounded transition-colors hover:bg-white hover:text-black">
                            <component :is="RiArrowDownSLine" class="w-8 h-8" />
                        </div>
                    </div>
              <div 
                @click="removeVideo(index)" 
                class="absolute z-10 top-4 right-4 opacity-0 md:opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer bg-white hover:bg-red-500 border border-neutral-200 hover:border-red-200 hover:text-white rounded-2xl p-4 shadow-sm transition-colors"
                @mouseenter="hoveredVideo = index"
                @mouseleave="hoveredVideo = null">
                Remove Video
              </div>
          
              <!-- Video Preview based on platform -->
              <div class="mb-2">
                <!-- YouTube Embed -->
                <div v-if="element.platform === 'youtube'" class="relative aspect-video w-full">
                  <iframe
                    :src="`https://www.youtube.com/embed/${element.id}`"
                    class="absolute top-0 left-0 w-full h-full rounded-b-xl"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                  ></iframe>
                </div>
                
                <!-- TikTok Embed -->
                <div v-else-if="element.platform === 'tiktok'" class="relative w-full">
                  <div class="tiktok-embed-container w-full">
                    <iframe
                      class="tiktok-iframe w-full"
                      :src="`https://www.tiktok.com/player/v1/${element.id}?music_info=1&description=1&autoplay=0&controls=1`"
                      allow="fullscreen"
                      frameborder="0"
                      style="aspect-ratio: 16/9; border-radius: 0.75rem;"
                    ></iframe>
                  </div>
                </div>
                
                <!-- Instagram Embed -->
                <div v-else-if="element.platform === 'instagram'" class="relative w-full flex justify-center">
                  <div class="instagram-embed-container w-full max-w-md mx-auto">
                    <blockquote
                      class="instagram-media"
                      :data-instgrm-permalink="`https://www.instagram.com/p/${element.id}/`"
                      data-instgrm-version="14"
                      data-instgrm-captioned="false"
                      data-instgrm-permalink-show-header="false"
                      data-instgrm-permalink-show-footer="false"
                      style="background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);"
                    >
                      <div style="padding:0;">
                        <a :href="`https://www.instagram.com/p/${element.id}/`" target="_blank">
                          <!-- Just a placeholder until Instagram script loads -->
                          <div style="padding:100% 0 0 0; position:relative;">
                            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%);">
                              <svg width="50" height="50" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                                <g fill="none" fill-rule="evenodd">
                                  <path d="M0 30C0 13.432 13.432 0 30 0s30 13.432 30 30-13.432 30-30 30S0 46.568 0 30z" fill="#000" fill-opacity=".1"/>
                                  <path d="M30 50.5c11.322 0 20.5-9.178 20.5-20.5S41.322 9.5 30 9.5 9.5 18.678 9.5 30 18.678 50.5 30 50.5z" stroke="#FFF"/>
                                  <path d="M25.5 20.5l12 10-12 10v-20z" fill="#FFF"/>
                                </g>
                              </svg>
                            </div>
                          </div>
                        </a>
                      </div>
                    </blockquote>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </draggable>
    </div>
    
    <!-- Add Video Form (Only show if less than maxVideos) -->
    <div v-if="modelValue.length < maxVideos" class="rounded-xl bg-white">
      <h4 class="font-medium mb-4">{{ modelValue.length > 0 ? 'Add Another Video' : '' }}</h4>
      
      <div class="relative">
        <input 
          type="text"
          v-model="videoUrl"
          placeholder="Paste video URL here"
          class="w-full text-2.5xl p-4 pr-12 border border-neutral-300 rounded-xl transition-all duration-200 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black"
          @input="handleVideoInput"
        />
        <div v-if="videoId" 
          @click="clearVideoInput"
          class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer">
        </div>
        <div v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
          <LoadingSpinner />
        </div>
      </div>
      
      <p v-if="videoError" class="text-red-500 mt-2">
        {{ videoError }}
      </p>
      
      <!-- Video Preview -->
      <div v-if="videoId" class="mt-4">
        <!-- YouTube Preview -->
        <div v-if="detectedPlatform === 'youtube'" class="aspect-video w-full">
          <iframe
            :src="`https://www.youtube.com/embed/${videoId}`"
            class="w-full h-full rounded-xl"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
          ></iframe>
        </div>
        
        <!-- TikTok Preview -->
        <div v-else-if="detectedPlatform === 'tiktok'" class="w-full">
          <div class="tiktok-embed-container w-full">
            <iframe
              class="tiktok-iframe w-full"
              :src="`https://www.tiktok.com/player/v1/${videoId}?music_info=1&description=1&autoplay=0&controls=1`"
              allow="fullscreen"
              frameborder="0"
              style="aspect-ratio: 16/9; border-radius: 0.75rem;"
            ></iframe>
          </div>
        </div>
      </div>
    </div>
    
    <p v-if="modelValue.length >= maxVideos" class="mt-4 text-neutral-500 text-center">
      Maximum of {{ maxVideos }} videos reached
    </p>
  </div>
</template>

<script setup>
import { RiCloseCircleLine, RiCloseCircleFill, RiArrowUpSLine, RiArrowDownSLine } from "@remixicon/vue";
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue';
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';
import draggable from 'vuedraggable';

// Props and emits
const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  maxVideos: {
    type: Number,
    default: 4
  },
  showInSlideshow: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['update:modelValue', 'update:showInSlideshow']);

// Utility functions
const debounce = (fn, delay) => {
  let timeoutId;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn(...args), delay);
  };
};

// State
const videoUrl = ref('');
const videoId = ref('');
const detectedPlatform = ref('');
const videoError = ref('');
const hoveredVideo = ref(null);
const isLoading = ref(false);
const localShowInSlideshow = ref(props.showInSlideshow);
const localVideos = ref([]);

// Initialize local videos from props
watch(() => props.modelValue, (newValue) => {
  // Ensure each video has a unique ID for draggable
  localVideos.value = newValue.map((video, index) => ({
    ...video,
    id: video.id || `video-${index}-${Date.now()}`
  }));
}, { immediate: true, deep: true });

// Move video up or down in the list
const moveVideo = (index, direction) => {
  // Calculate new index
  const newIndex = index + direction;
  
  // Check if new index is valid
  if (newIndex < 0 || newIndex >= localVideos.value.length) {
    return; // Can't move outside bounds
  }
  
  // Create a copy of the array
  const newVideos = [...localVideos.value];
  
  // Swap the videos
  [newVideos[index], newVideos[newIndex]] = [newVideos[newIndex], newVideos[index]];
  
  // Update the array
  localVideos.value = newVideos;
  updateVideos(newVideos);
  
  // Refresh TikTok embeds
  nextTick(() => {
    refreshTikTokEmbeds();
  });
};

// Handle sorting of videos
const handleSort = ({ moved }) => {
  if (moved) {
    // Update the parent with the new order
    updateVideos(localVideos.value);
    
    // Refresh TikTok embeds after sorting
    nextTick(() => {
      refreshTikTokEmbeds();
    });
  }
};

// Helper functions
const getPlatformLabel = (platform) => {
  const labels = {
    'youtube': 'YouTube',
    'tiktok': 'TikTok',
    'instagram': 'Instagram'
  };
  return labels[platform] || platform.charAt(0).toUpperCase() + platform.slice(1);
};

// Video extraction functions
const extractVideoInfo = (url) => {
  if (!url) return { platform: null, id: null };
  
  // YouTube patterns
  const youtubeRegex = /^.*(?:(?:youtu\.be\/|v\/|vi\/|u\/\w\/|embed\/|shorts\/)|(?:(?:watch)?\?v(?:i)?=|\&v(?:i)?=))([^#\&\?]*).*/;
  const youtubeMatch = url.match(youtubeRegex);
  if (youtubeMatch && youtubeMatch[1].length === 11) {
    return { platform: 'youtube', id: youtubeMatch[1] };
  }
  
  // TikTok patterns
  const tikTokPatterns = [
    /tiktok\.com\/@[^\/]+\/video\/(\d+)/i,
    /vm\.tiktok\.com\/(\w+)/,
    /^(\d+)$/ // Direct video ID (only if clearly numeric)
  ];
  
  for (const pattern of tikTokPatterns) {
    const match = url.match(pattern);
    if (match && match[1]) {
      return { platform: 'tiktok', id: match[1] };
    }
  }
  
  return { platform: null, id: null };
};

// Video handling methods
const clearVideoInput = () => {
  videoUrl.value = '';
  videoId.value = '';
  detectedPlatform.value = '';
  videoError.value = '';
  isLoading.value = false;
};

// Check if video already exists in the collection
const isDuplicateVideo = (platform, id) => {
  return localVideos.value.some(video => 
    video.platform === platform && 
    (video.id === id || video.url === videoUrl.value)
  );
};

const handleVideoInput = debounce(async () => {
  videoError.value = '';
  videoId.value = '';
  detectedPlatform.value = '';
  
  if (!videoUrl.value) return;
  
  // Show loading spinner
  isLoading.value = true;
  
  // Block Instagram URLs
  if (videoUrl.value.includes('instagram.com')) {
    videoError.value = 'Instagram videos are not supported. Please use YouTube or TikTok videos.';
    isLoading.value = false;
    return;
  }
  
  // Extract platform and ID from URL
  const { platform, id } = extractVideoInfo(videoUrl.value);
  
  if (!platform || !id) {
    videoError.value = 'Could not detect a valid YouTube or TikTok video URL';
    isLoading.value = false;
    return;
  }
  
  // Check if this video is already in the collection
  if (isDuplicateVideo(platform, id)) {
    videoError.value = 'This video has already been added';
    isLoading.value = false;
    clearVideoInput();
    return;
  }
  
  // Verification differs by platform
  try {
    switch (platform) {
      case 'youtube':
        const ytResponse = await fetch(`https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=${id}&format=json`);
        if (ytResponse.ok) {
          videoId.value = id;
          detectedPlatform.value = platform;
          // Auto-add the video after a short delay for preview
          setTimeout(() => {
            addVideo();
            isLoading.value = false;
          }, 1000);
        } else {
          videoError.value = 'This YouTube video cannot be embedded or does not exist';
          isLoading.value = false;
        }
        break;
        
      case 'tiktok':
        // TikTok doesn't have a simple API for checking, so we'll just set the ID
        videoId.value = id;
        detectedPlatform.value = platform;
        // Auto-add the video after a short delay for preview
        setTimeout(() => {
          addVideo();
          isLoading.value = false;
        }, 1000);
        break;
    }
  } catch (error) {
    videoError.value = 'Error validating video URL';
    console.error('Video validation error:', error);
    isLoading.value = false;
  }
}, 500);

// Update the parent's video array
const updateVideos = (newVideos) => {
  emit('update:modelValue', newVideos);
};

// Add video to the list
const addVideo = () => {
  if (!videoId.value || !detectedPlatform.value) return;
  
  // Double-check for duplicates before adding
  if (isDuplicateVideo(detectedPlatform.value, videoId.value)) {
    videoError.value = 'This video has already been added';
    return;
  }
  
  const newVideo = {
    id: videoId.value,
    platform: detectedPlatform.value,
    url: videoUrl.value,
    // Add a unique ID for draggable
    uniqueId: `video-${localVideos.value.length}-${Date.now()}`
  };
  
  const newVideos = [...localVideos.value, newVideo];
  localVideos.value = newVideos;
  updateVideos(newVideos);
  
  // Clear the input
  clearVideoInput();
  
  // Refresh TikTok embeds
  nextTick(() => {
    refreshTikTokEmbeds();
  });
};

// Remove video from the list
const removeVideo = (index) => {
  const newVideos = [...localVideos.value];
  newVideos.splice(index, 1);
  localVideos.value = newVideos;
  updateVideos(newVideos);
};

// TikTok embed handling
const loadTikTokScript = () => {
  // No need to load the TikTok embed.js script when using the player/v1 endpoint
  // We might still want this method for compatibility with any existing embeds
  setupTikTokPlayerControls();
};

// Add TikTok player control functionality
const setupTikTokPlayerControls = () => {
  // Listen for messages from TikTok iframe players
  if (!window._tiktokPlayerListenerSet) {
    window.addEventListener('message', (event) => {
      try {
        const data = event.data;
        // Check if this is a TikTok player message
        if (data && data['x-tiktok-player']) {
          // Here you can handle player events if needed
          // For example, if you want to track when videos start playing
        }
      } catch (e) {
        console.error('Error processing TikTok player message:', e);
      }
    });
    window._tiktokPlayerListenerSet = true;
  }
  
  // Helper method to control TikTok players (can be exposed if needed)
  window.controlTikTokPlayer = (iframe, action, value = null) => {
    if (!iframe) return;
    
    const message = {
      type: action,
      value: value,
      'x-tiktok-player': true
    };
    
    try {
      iframe.contentWindow.postMessage(message, '*');
    } catch (e) {
      console.error('Error sending message to TikTok player:', e);
    }
  };
};

// Simplified TikTok embed refresh that doesn't rely on the embed.js script
const refreshTikTokEmbeds = () => {
  nextTick(() => {
    setupTikTokPlayerControls();
  });
};

// Update the slideshow preference
const updateShowInSlideshow = (value) => {
  emit('update:showInSlideshow', value);
};

// Keep the local toggle in sync with props
watch(() => props.showInSlideshow, (newValue) => {
  localShowInSlideshow.value = newValue;
}, { immediate: true });

// Lifecycle
onMounted(() => {
  // Initialize embed scripts
  loadTikTokScript();
  
  // Initialize toggle state from props
  localShowInSlideshow.value = props.showInSlideshow;
});
</script>
