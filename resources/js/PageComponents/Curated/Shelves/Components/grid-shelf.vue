<template>
    <div class="whitespace-nowrap overflow-y-hidden overflow-x-auto m-auto w-full px-8 md:px-32">
        <div class="border-t border-gray-300">
            <div v-if="shelf.name" class="mt-8 mb-0 md:mt-12">
                <div>
                    <h2 class="text-3.5xl text-black font-bold">{{ shelf.name }}</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-8 mt-8">
                <div v-for="post in gridPosts" 
                    :key="post.id"
                    class="relative">
                    <div class="flex w-full flex-col overflow-hidden relative">
                        <a :href="`/communities/${community.slug}/posts/${post.slug}`"
                        class="block h-full absolute w-full rounded-2xl top-0 left-0 z-10">
                        </a>
                        
                        <div class="aspect-[4/3] w-full rounded-2xl overflow-hidden">
                            <img v-if="getPostImage(post)"
                                :src="`${imageUrl}${getPostImage(post)}`"
                                :alt="post.name"
                                class="w-full h-full object-cover" />
                        </div>

                        <div class="mb-8 mt-2">
                            <div class="mt-4 font-medium whitespace-normal">
                                <p class="text-4xl leading-tight text-black">{{ post.name }}</p>
                            </div>
                            <div class="mt-2 text-xl text-gray-500">
                                {{ formatDate(post.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    shelf: Object,
    community: Object,
    rows: {
        type: Number,
        default: 1
    }
})

const imageUrl = import.meta.env.VITE_IMAGE_URL

const gridPosts = computed(() => {
    // 4 posts per row * number of rows
    const limit = 4 * props.rows
    return props.shelf.published_posts.data.slice(0, limit)
})

const getPostImage = (post) => {
    if (post.event_id && post.featured_event_image) {
        return post.featured_event_image.thumbImagePath
    } else if (post.images && post.images.length > 0) {
        return post.images[0].thumb_image_path || post.images[0].large_image_path
    } else {
        return post.thumbImagePath
    }
}

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' }
    return new Date(dateString).toLocaleDateString('en-US', options)
}
</script>