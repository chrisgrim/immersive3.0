<template>
    <div class="w-full">
        <!-- Grid container with responsive columns -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Post card for each item -->
            <div 
                v-for="post in loadposts" 
                :key="post.id"
                class="group relative aspect-[4/3] rounded-2xl overflow-hidden"
            >
                <a :href="`/communities/${community.slug}/${post.slug}`">
                    <!-- Image -->
                    <div class="w-full h-full">
                        <img 
                            v-if="getPostImage(post)"
                            :src="`${imageUrl}${getPostImage(post)}`"
                            :alt="post.name"
                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <!-- Fallback if no image -->
                        <div 
                            v-else 
                            class="w-full h-full bg-gray-100 flex items-center justify-center"
                        >
                            <span class="text-gray-400">No image</span>
                        </div>
                    </div>

                    <!-- Title overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end p-4">
                        <h3 class="text-white text-xl font-semibold line-clamp-2">
                            {{ post.name }}
                        </h3>
                    </div>
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: Boolean,
    text: Boolean,
    loadposts: Array,
    value: Object,
    community: Object,
    draggable: Boolean
})

const imageUrl = import.meta.env.VITE_IMAGE_URL

// Helper function to get the post's image
const getPostImage = (post) => {
    if (post.event_id && post.featured_event_image) {
        return post.featured_event_image.thumbImagePath
    } else if (post.images && post.images.length > 0) {
        return post.images[0].thumb_image_path || post.images[0].large_image_path
    } else {
        return post.thumbImagePath
    }
}
</script>

<style scoped>
/* Optional: Add hover effects or transitions */
.group:hover img {
    transform: scale(1.05);
}
</style>
