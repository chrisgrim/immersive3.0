<template>
    <div class="my-10 md:mt-16 md:mb-24 px-10 md:px-32">
        <div class="w-full relative block overflow-hidden mb-8 rounded-xl flex flex-col md:flex-row">
            <div v-if="firstPost && getPostImage(firstPost)" 
                 class="rounded-2xl overflow-hidden relative inline-block bg-slate-400 w-full md:w-3/5 md:order-last mb-8 md:mb-0">
                <div class="aspect-video">
                    <picture class="w-full h-full">
                        <source 
                            type="image/webp" 
                            :srcset="`${imageUrl}${getPostImage(firstPost)}`">
                        <img 
                            loading="lazy"
                            class="object-cover w-full h-full"
                            :src="`${imageUrl}${getPostImage(firstPost).replace('.webp', '.jpg')}`"
                            :alt="`${firstPost.name}`">
                    </picture>
                </div>
            </div>
            
            <div class="flex items-center justify-center md:justify-start md:w-2/5">
                <div class="w-full md:w-4/5">
                    <div>
                        <p class="text-gray-500">{{ shelf.name }}: </p>
                        <h2 class="text-6xl leading-[4.5rem] mt-8 font-medium text-black">{{ firstPost?.name }}</h2>
                    </div>
                    <a :href="`/communities/${community.slug}/posts/${firstPost?.slug}`">
                        <button class="bg-[#ff385c] text-white border-none p-6 mt-8 rounded-2xl font-bold text-xl">
                            Check it out
                        </button>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    shelf: Object,
    community: Object
})

const imageUrl = import.meta.env.VITE_IMAGE_URL

const firstPost = computed(() => {
    return props.shelf.published_posts.data[0] || null
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
</script>