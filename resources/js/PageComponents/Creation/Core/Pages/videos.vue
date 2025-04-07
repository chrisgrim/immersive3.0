<template>
  <div class="videos-component">
    <h3 class="text-2xl mb-4">{{ title }}</h3>
    
    <!-- Video List -->
    <div v-if="modelValue.length > 0" class="mb-8 space-y-6">
      <div 
        v-for="(video, index) in modelValue" 
        :key="index" 
        class="bg-white rounded-xl shadow-sm p-4 border border-neutral-200"
      >
        <div class="flex justify-between items-center mb-3">
          <div class="flex items-center space-x-2">
            <span class="font-medium">{{ getPlatformLabel(video.platform) }} Video</span>
            <span class="text-neutral-500 text-sm">#{{ index + 1 }}</span>
          </div>
          <button 
            @click="removeVideo(index)" 
            class="text-red-500 hover:text-red-700 transition-colors duration-200"
          >
            Remove
          </button>
        </div>
        
        <!-- Video Preview based on platform -->
        <div class="mb-2">
          <!-- YouTube Embed -->
          <div v-if="video.platform === 'youtube'" class="relative aspect-video w-full">
            <iframe
              :src="`https://www.youtube.com/embed/${video.id}`"
              class="absolute top-0 left-0 w-full h-full rounded-xl"
              frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            ></iframe>
          </div>
          
          <!-- TikTok Embed -->
          <div v-else-if="video.platform === 'tiktok'" class="relative w-full flex justify-center">
            <div class="tiktok-embed-container w-full max-w-md mx-auto">
              <blockquote 
                class="tiktok-embed" 
                :cite="`https://www.tiktok.com/@tiktok/video/${video.id}`" 
                :data-video-id="video.id" 
                style="max-width: 605px; min-width: 325px;"
              >
                <section>
                  <a target="_blank" href="https://www.tiktok.com">TikTok Video</a>
                </section>
              </blockquote>
            </div>
          </div>
          
          <!-- Instagram Embed -->
          <div v-else-if="video.platform === 'instagram'" class="relative w-full flex justify-center">
            <div class="instagram-embed-container w-full max-w-md mx-auto">
              <blockquote
                class="instagram-media"
                :data-instgrm-permalink="`https://www.instagram.com/p/${video.id}/`"
                data-instgrm-version="14"
                style="background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);"
              >
                <div style="padding:16px;">
                  <a :href="`https://www.instagram.com/p/${video.id}/`" style="background:#FFFFFF; line-height:0; padding:0 0; text-align:center; text-decoration:none; width:100%;" target="_blank">
                    <div style="display: flex; flex-direction: row; align-items: center;">
                      <div style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 40px; margin-right: 14px; width: 40px;"></div>
                      <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center;">
                        <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 100px;"></div>
                        <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 60px;"></div>
                      </div>
                    </div>
                    <div style="padding: 19% 0;"></div>
                    <div style="display:block; height:50px; margin:0 auto 12px; width:50px;">
                      <svg width="50px" height="50px" viewBox="0 0 60 60" version="1.1" xmlns="https://www.w3.org/2000/svg" xmlns:xlink="https://www.w3.org/1999/xlink">
                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                          <g transform="translate(-511.000000, -20.000000)" fill="#000000">
                            <g>
                              <path d="M556.869,30.41 C554.814,30.41 553.148,32.076 553.148,34.131 C553.148,36.186 554.814,37.852 556.869,37.852 C558.924,37.852 560.59,36.186 560.59,34.131 C560.59,32.076 558.924,30.41 556.869,30.41 M541,60.657 C535.114,60.657 530.342,55.887 530.342,50 C530.342,44.114 535.114,39.342 541,39.342 C546.887,39.342 551.658,44.114 551.658,50 C551.658,55.887 546.887,60.657 541,60.657 M541,33.886 C532.1,33.886 524.886,41.1 524.886,50 C524.886,58.899 532.1,66.113 541,66.113 C549.9,66.113 557.115,58.899 557.115,50 C557.115,41.1 549.9,33.886 541,33.886 M565.378,62.101 C565.244,65.022 564.756,66.606 564.346,67.663 C563.803,69.06 563.154,70.057 562.106,71.106 C561.058,72.155 560.06,72.803 558.662,73.347 C557.607,73.757 556.021,74.244 553.102,74.378 C549.944,74.521 548.997,74.552 541,74.552 C533.003,74.552 532.056,74.521 528.898,74.378 C525.979,74.244 524.393,73.757 523.338,73.347 C521.94,72.803 520.942,72.155 519.894,71.106 C518.846,70.057 518.197,69.06 517.654,67.663 C517.244,66.606 516.755,65.022 516.623,62.101 C516.479,58.943 516.448,57.996 516.448,50 C516.448,42.003 516.479,41.056 516.623,37.899 C516.755,34.978 517.244,33.391 517.654,32.338 C518.197,30.938 518.846,29.942 519.894,28.894 C520.942,27.846 521.94,27.196 523.338,26.654 C524.393,26.244 525.979,25.756 528.898,25.623 C532.057,25.479 533.004,25.448 541,25.448 C548.997,25.448 549.943,25.479 553.102,25.623 C556.021,25.756 557.607,26.244 558.662,26.654 C560.06,27.196 561.058,27.846 562.106,28.894 C563.154,29.942 563.803,30.938 564.346,32.338 C564.756,33.391 565.244,34.978 565.378,37.899 C565.522,41.056 565.552,42.003 565.552,50 C565.552,57.996 565.522,58.943 565.378,62.101 M570.82,37.631 C570.674,34.438 570.167,32.258 569.425,30.349 C568.659,28.377 567.633,26.702 565.965,25.035 C564.297,23.368 562.623,22.342 560.652,21.575 C558.743,20.834 556.562,20.326 553.369,20.18 C550.169,20.033 549.148,20 541,20 C532.853,20 531.831,20.033 528.631,20.18 C525.438,20.326 523.257,20.834 521.349,21.575 C519.376,22.342 517.703,23.368 516.035,25.035 C514.368,26.702 513.342,28.377 512.574,30.349 C511.834,32.258 511.326,34.438 511.181,37.631 C511.035,40.831 511,41.851 511,50 C511,58.147 511.035,59.17 511.181,62.369 C511.326,65.562 511.834,67.743 512.574,69.651 C513.342,71.625 514.368,73.296 516.035,74.965 C517.703,76.634 519.376,77.658 521.349,78.425 C523.257,79.167 525.438,79.673 528.631,79.82 C531.831,79.965 532.853,80.001 541,80.001 C549.148,80.001 550.169,79.965 553.369,79.82 C556.562,79.673 558.743,79.167 560.652,78.425 C562.623,77.658 564.297,76.634 565.965,74.965 C567.633,73.296 568.659,71.625 569.425,69.651 C570.167,67.743 570.674,65.562 570.82,62.369 C570.966,59.17 571,58.147 571,50 C571,41.851 570.966,40.831 570.82,37.631"></path>
                            </g>
                          </g>
                        </g>
                      </svg>
                    </div>
                    <div style="padding-top: 8px;">
                      <div style="color:#3897f0; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:550; line-height:18px;">View this post on Instagram</div>
                    </div>
                    <div style="padding: 12.5% 0;"></div>
                    <div style="display: flex; flex-direction: row; margin-bottom: 14px; align-items: center;">
                      <div>
                        <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(0px) translateY(7px);"></div>
                        <div style="background-color: #F4F4F4; height: 12.5px; transform: rotate(-45deg) translateX(3px) translateY(1px); width: 12.5px; flex-grow: 0; margin-right: 14px; margin-left: 2px;"></div>
                        <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(9px) translateY(-18px);"></div>
                      </div>
                      <div style="margin-left: 8px;">
                        <div style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 20px; width: 20px;"></div>
                        <div style="width: 0; height: 0; border-top: 2px solid transparent; border-left: 6px solid #f4f4f4; border-bottom: 2px solid transparent; transform: translateX(16px) translateY(-4px) rotate(30deg)"></div>
                      </div>
                      <div style="margin-left: auto;">
                        <div style="width: 0px; border-top: 8px solid #F4F4F4; border-right: 8px solid transparent; transform: translateY(16px);"></div>
                        <div style="background-color: #F4F4F4; flex-grow: 0; height: 12px; width: 16px; transform: translateY(-4px);"></div>
                        <div style="width: 0; height: 0; border-top: 8px solid #F4F4F4; border-left: 8px solid transparent; transform: translateY(-4px) translateX(8px);"></div>
                      </div>
                    </div>
                    <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center; margin-bottom: 24px;">
                      <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 224px;"></div>
                      <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 144px;"></div>
                    </div>
                  </a>
                </div>
              </blockquote>
            </div>
          </div>
        </div>
        
        <div class="text-sm text-neutral-500 truncate">
          ID: {{ video.id }}
        </div>
      </div>
    </div>
    
    <!-- Add Video Form (Only show if less than maxVideos) -->
    <div v-if="modelValue.length < maxVideos" class="border border-neutral-200 rounded-xl p-6 bg-white">
      <h4 class="font-medium mb-4">{{ modelValue.length > 0 ? 'Add Another Video' : 'Add Video' }}</h4>
      
      <div class="relative">
        <input 
          type="text"
          v-model="videoUrl"
          placeholder="Paste video URL here"
          class="w-full p-4 pr-12 border border-neutral-300 rounded-xl transition-all duration-200 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black"
          @input="handleVideoInput"
        />
        <div v-if="videoId" 
          @click="clearVideoInput"
          class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
          </svg>
        </div>
      </div>
      
      <p v-if="detectedPlatform && !videoError" class="text-green-600 mt-2">
        {{ getPlatformLabel(detectedPlatform) }} video detected
      </p>
      
      <p v-if="videoError" class="text-red-500 mt-2">
        {{ videoError }}
      </p>
      
      <div class="mt-4 flex justify-end">
        <button 
          @click="addVideo"
          :disabled="!videoId"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Add Video
        </button>
      </div>
      
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
        <div v-else-if="detectedPlatform === 'tiktok'" class="w-full flex justify-center">
          <div class="tiktok-embed-container w-full max-w-md mx-auto">
            <blockquote 
              class="tiktok-embed" 
              :cite="`https://www.tiktok.com/@tiktok/video/${videoId}`" 
              :data-video-id="videoId" 
              style="max-width: 605px; min-width: 325px;"
            >
              <section>
                <a target="_blank" href="https://www.tiktok.com">TikTok Video</a>
              </section>
            </blockquote>
          </div>
        </div>
        
        <!-- Instagram Preview -->
        <div v-else-if="detectedPlatform === 'instagram'" class="w-full flex justify-center">
          <div class="instagram-embed-container w-full max-w-md mx-auto">
            <blockquote
              class="instagram-media"
              :data-instgrm-permalink="`https://www.instagram.com/p/${videoId}/`"
              data-instgrm-version="14"
              style="background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);"
            >
              <div style="padding:16px;">
                <a :href="`https://www.instagram.com/p/${videoId}/`" style="background:#FFFFFF; line-height:0; padding:0 0; text-align:center; text-decoration:none; width:100%;" target="_blank">
                  <div style="display: flex; flex-direction: row; align-items: center;">
                    <div style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 40px; margin-right: 14px; width: 40px;"></div>
                    <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center;">
                      <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 100px;"></div>
                      <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 60px;"></div>
                    </div>
                  </div>
                  <div style="padding: 19% 0;"></div>
                  <div style="display:block; height:50px; margin:0 auto 12px; width:50px;">
                    <svg width="50px" height="50px" viewBox="0 0 60 60" version="1.1" xmlns="https://www.w3.org/2000/svg" xmlns:xlink="https://www.w3.org/1999/xlink">
                      <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g transform="translate(-511.000000, -20.000000)" fill="#000000">
                          <g>
                            <path d="M556.869,30.41 C554.814,30.41 553.148,32.076 553.148,34.131 C553.148,36.186 554.814,37.852 556.869,37.852 C558.924,37.852 560.59,36.186 560.59,34.131 C560.59,32.076 558.924,30.41 556.869,30.41 M541,60.657 C535.114,60.657 530.342,55.887 530.342,50 C530.342,44.114 535.114,39.342 541,39.342 C546.887,39.342 551.658,44.114 551.658,50 C551.658,55.887 546.887,60.657 541,60.657 M541,33.886 C532.1,33.886 524.886,41.1 524.886,50 C524.886,58.899 532.1,66.113 541,66.113 C549.9,66.113 557.115,58.899 557.115,50 C557.115,41.1 549.9,33.886 541,33.886 M565.378,62.101 C565.244,65.022 564.756,66.606 564.346,67.663 C563.803,69.06 563.154,70.057 562.106,71.106 C561.058,72.155 560.06,72.803 558.662,73.347 C557.607,73.757 556.021,74.244 553.102,74.378 C549.944,74.521 548.997,74.552 541,74.552 C533.003,74.552 532.056,74.521 528.898,74.378 C525.979,74.244 524.393,73.757 523.338,73.347 C521.94,72.803 520.942,72.155 519.894,71.106 C518.846,70.057 518.197,69.06 517.654,67.663 C517.244,66.606 516.755,65.022 516.623,62.101 C516.479,58.943 516.448,57.996 516.448,50 C516.448,42.003 516.479,41.056 516.623,37.899 C516.755,34.978 517.244,33.391 517.654,32.338 C518.197,30.938 518.846,29.942 519.894,28.894 C520.942,27.846 521.94,27.196 523.338,26.654 C524.393,26.244 525.979,25.756 528.898,25.623 C532.057,25.479 533.004,25.448 541,25.448 C548.997,25.448 549.943,25.479 553.102,25.623 C556.021,25.756 557.607,26.244 558.662,26.654 C560.06,27.196 561.058,27.846 562.106,28.894 C563.154,29.942 563.803,30.938 564.346,32.338 C564.756,33.391 565.244,34.978 565.378,37.899 C565.522,41.056 565.552,42.003 565.552,50 C565.552,57.996 565.522,58.943 565.378,62.101 M570.82,37.631 C570.674,34.438 570.167,32.258 569.425,30.349 C568.659,28.377 567.633,26.702 565.965,25.035 C564.297,23.368 562.623,22.342 560.652,21.575 C558.743,20.834 556.562,20.326 553.369,20.18 C550.169,20.033 549.148,20 541,20 C532.853,20 531.831,20.033 528.631,20.18 C525.438,20.326 523.257,20.834 521.349,21.575 C519.376,22.342 517.703,23.368 516.035,25.035 C514.368,26.702 513.342,28.377 512.574,30.349 C511.834,32.258 511.326,34.438 511.181,37.631 C511.035,40.831 511,41.851 511,50 C511,58.147 511.035,59.17 511.181,62.369 C511.326,65.562 511.834,67.743 512.574,69.651 C513.342,71.625 514.368,73.296 516.035,74.965 C517.703,76.634 519.376,77.658 521.349,78.425 C523.257,79.167 525.438,79.673 528.631,79.82 C531.831,79.965 532.853,80.001 541,80.001 C549.148,80.001 550.169,79.965 553.369,79.82 C556.562,79.673 558.743,79.167 560.652,78.425 C562.623,77.658 564.297,76.634 565.965,74.965 C567.633,73.296 568.659,71.625 569.425,69.651 C570.167,67.743 570.674,65.562 570.82,62.369 C570.966,59.17 571,58.147 571,50 C571,41.851 570.966,40.831 570.82,37.631"></path>
                          </g>
                        </g>
                      </g>
                    </svg>
                  </div>
                  <div style="padding-top: 8px;">
                    <div style="color:#3897f0; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:550; line-height:18px;">View this post on Instagram</div>
                  </div>
                  <div style="padding: 12.5% 0;"></div>
                  <div style="display: flex; flex-direction: row; margin-bottom: 14px; align-items: center;">
                    <div>
                      <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(0px) translateY(7px);"></div>
                      <div style="background-color: #F4F4F4; height: 12.5px; transform: rotate(-45deg) translateX(3px) translateY(1px); width: 12.5px; flex-grow: 0; margin-right: 14px; margin-left: 2px;"></div>
                      <div style="background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(9px) translateY(-18px);"></div>
                    </div>
                    <div style="margin-left: 8px;">
                      <div style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 20px; width: 20px;"></div>
                      <div style="width: 0; height: 0; border-top: 2px solid transparent; border-left: 6px solid #f4f4f4; border-bottom: 2px solid transparent; transform: translateX(16px) translateY(-4px) rotate(30deg)"></div>
                    </div>
                    <div style="margin-left: auto;">
                      <div style="width: 0px; border-top: 8px solid #F4F4F4; border-right: 8px solid transparent; transform: translateY(16px);"></div>
                      <div style="background-color: #F4F4F4; flex-grow: 0; height: 12px; width: 16px; transform: translateY(-4px);"></div>
                      <div style="width: 0; height: 0; border-top: 8px solid #F4F4F4; border-left: 8px solid transparent; transform: translateY(-4px) translateX(8px);"></div>
                    </div>
                  </div>
                  <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center; margin-bottom: 24px;">
                    <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 224px;"></div>
                    <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 144px;"></div>
                  </div>
                </a>
              </div>
            </blockquote>
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
import { ref, computed, onMounted, nextTick, watch } from 'vue';

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
  title: {
    type: String,
    default: 'Add Videos (Optional)'
  }
});

const emit = defineEmits(['update:modelValue']);

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
  
  // Instagram patterns
  const instaPatterns = [
    /instagram\.com\/p\/([a-zA-Z0-9_-]+)/i,                    // Standard post format
    /instagram\.com\/reel\/([a-zA-Z0-9_-]+)/i,                 // Standard reel format
    /instagram\.com\/[^\/]+\/reel\/([a-zA-Z0-9_-]+)/i,         // Username/reel format
    /instagram\.com\/[^\/]+\/p\/([a-zA-Z0-9_-]+)/i,            // Username/post format
  ];
  
  for (const pattern of instaPatterns) {
    const match = url.match(pattern);
    if (match && match[1]) {
      return { platform: 'instagram', id: match[1] };
    }
  }
  
  // Check if it's a direct Instagram shortcode (if not detected as TikTok ID)
  // Only if it has proper Instagram shortcode format
  const instaShortcodePattern = /^[a-zA-Z0-9_-]{10,12}$/;
  if (instaShortcodePattern.test(url)) {
    return { platform: 'instagram', id: url };
  }
  
  return { platform: null, id: null };
};

// Video handling methods
const clearVideoInput = () => {
  videoUrl.value = '';
  videoId.value = '';
  detectedPlatform.value = '';
  videoError.value = '';
};

const handleVideoInput = debounce(async () => {
  videoError.value = '';
  videoId.value = '';
  detectedPlatform.value = '';
  
  if (!videoUrl.value) return;
  
  // Extract platform and ID from URL
  const { platform, id } = extractVideoInfo(videoUrl.value);
  
  if (!platform || !id) {
    videoError.value = 'Could not detect a valid YouTube, TikTok, or Instagram video URL';
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
        } else {
          videoError.value = 'This YouTube video cannot be embedded or does not exist';
        }
        break;
        
      case 'tiktok':
        // TikTok doesn't have a simple API for checking, so we'll just set the ID
        videoId.value = id;
        detectedPlatform.value = platform;
        break;
        
      case 'instagram':
        // Instagram also doesn't have a simple public API to verify, so we'll just set the ID
        videoId.value = id;
        detectedPlatform.value = platform;
        break;
    }
  } catch (error) {
    videoError.value = 'Error validating video URL';
    console.error('Video validation error:', error);
  }
}, 500);

// Update the parent's video array
const updateVideos = (newVideos) => {
  emit('update:modelValue', newVideos);
};

// Add video to the list
const addVideo = () => {
  if (!videoId.value || !detectedPlatform.value) return;
  
  const newVideos = [...props.modelValue, {
    id: videoId.value,
    platform: detectedPlatform.value,
    url: videoUrl.value
  }];
  
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
  const newVideos = [...props.modelValue];
  newVideos.splice(index, 1);
  updateVideos(newVideos);
};

// TikTok embed handling
const loadTikTokScript = () => {
  // Check if script is already loaded
  if (document.querySelector('script[src="https://www.tiktok.com/embed.js"]')) {
    // If script exists, try to re-run it by removing and re-adding it
    try {
      const oldScript = document.querySelector('script[src="https://www.tiktok.com/embed.js"]');
      const parent = oldScript.parentNode;
      parent.removeChild(oldScript);
        
      setTimeout(() => {
        const newScript = document.createElement('script');
        newScript.setAttribute('src', 'https://www.tiktok.com/embed.js');
        newScript.setAttribute('async', true);
        document.head.appendChild(newScript);
      }, 100);
    } catch (e) {
      console.error('Error reloading TikTok script:', e);
    }
  } else {
    // If script doesn't exist, load it
    const script = document.createElement('script');
    script.setAttribute('src', 'https://www.tiktok.com/embed.js');
    script.setAttribute('async', true);
    document.head.appendChild(script);
  }
};

// Add function to load Instagram embed SDK
const loadInstagramScript = () => {
  // Check if script is already loaded
  if (document.querySelector('script[src="https://www.instagram.com/embed.js"]')) {
    // If script exists, try to re-run it
    try {
      if (window.instgrm) {
        window.instgrm.Embeds.process();
      }
    } catch (e) {
      console.error('Error processing Instagram embeds:', e);
      
      // If processing fails, reload the script
      const oldScript = document.querySelector('script[src="https://www.instagram.com/embed.js"]');
      const parent = oldScript.parentNode;
      parent.removeChild(oldScript);
      
      setTimeout(() => {
        const newScript = document.createElement('script');
        newScript.setAttribute('src', 'https://www.instagram.com/embed.js');
        newScript.setAttribute('async', true);
        document.head.appendChild(newScript);
      }, 100);
    }
  } else {
    // If script doesn't exist, load it
    const script = document.createElement('script');
    script.setAttribute('src', 'https://www.instagram.com/embed.js');
    script.setAttribute('async', true);
    document.head.appendChild(script);
  }
};

// When videos change, reinitialize embeds
const refreshTikTokEmbeds = () => {
  nextTick(() => {
    loadTikTokScript();
  });
};

// Instagram embeds refresh
const refreshInstagramEmbeds = () => {
  nextTick(() => {
    loadInstagramScript();
  });
};

// Watch for changes in the videos array to refresh embeds
watch(() => props.modelValue, () => {
  refreshTikTokEmbeds();
  refreshInstagramEmbeds();
}, { deep: true });

// Watch for changes in the videoId and platform values to refresh embeds for preview
watch([videoId, detectedPlatform], ([newId, newPlatform]) => {
  if (newId) {
    if (newPlatform === 'tiktok') {
      refreshTikTokEmbeds();
    } else if (newPlatform === 'instagram') {
      refreshInstagramEmbeds();
    }
  }
});

// Lifecycle
onMounted(() => {
  // Initialize embed scripts
  loadTikTokScript();
  loadInstagramScript();
});
</script>
