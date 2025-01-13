<template>
    <div class="my-8 md:mt-16 md:mb-24 pb-16 bg-slate-100">
        <div class="justify-between flex px-8 lg-air:px-16 2xl-air:px-32 my-8 lg:my-12">
            <div v-if="shelf.name" class="mt-8 mb-8 md:mt-24">
                <div>
                    <h2 class="text-5xl text-black font-bold">{{ shelf.name }}</h2>
                </div>
            </div>
            
            <div class="inline-flex items-end gap-2 invisible md:visible">
                <button 
                    aria-label="Scroll Left"
                    class="rounded-full w-14 h-14 border border-gray-300 p-0 bg-white hover:shadow-md transition-shadow" 
                    @click="scrollLeft">
                    <svg class="w-2/4 h-full m-auto">
                        <use href="/storage/website-files/icons.svg#ri-arrow-left-s-line" />
                    </svg>
                </button>
                <button 
                    aria-label="Scroll Right"
                    class="rounded-full w-14 h-14 border border-gray-300 p-0 bg-white hover:shadow-md transition-shadow" 
                    @click="scrollRight">
                    <svg class="w-2/4 h-full m-auto">
                        <use href="/storage/website-files/icons.svg#ri-arrow-right-s-line" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="overflow-y-hidden overflow-x-auto whitespace-nowrap scrollbar-hide">
            <div ref="scrollContainer" 
                 class="overflow-x-auto flex scroll-p-10 lg:scroll-p-32 scrollbar-hide" 
                 style="scroll-snap-type: x mandatory;">
                <div v-for="post in shelf.published_posts.data"
                     :key="post.id"
                     class="ml-10 first:ml-0 snap-start snap-always w-[calc(31.25%- -7rem)] first:pl-8 last:pr-8 md:first:pl-32 md:last:pr-32">
                    <a :href="`/communities/${community.slug}/${post.slug}`" 
                       class="block w-full pb-16">
                        <div class="rounded-2xl overflow-hidden h-full border border-gray-300" :style="{ width: `${cardWidth}px` }">
                            <div class="w-full" :style="{ height: `${cardWidth}px` }">
                                <picture>
                                    <source 
                                        type="image/webp" 
                                        :srcset="`${imageUrl}${getPostImage(post)}`">
                                    <img 
                                        class="h-full w-full object-cover"
                                        loading="lazy" 
                                        :src="`${imageUrl}${getPostImage(post).replace('.webp', '.jpg')}`"
                                        :alt="post.name">
                                </picture>
                            </div>
                            
                            <div class="text-left bg-white p-8">
                                <h3 class="text-3.5xl font-medium text-black">{{ post.name }}</h3>
                                <p class="text-gray-600 text-lg my-4">{{ formatDate(post.created_at) }}</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    shelf: Object,
    community: Object
})

const imageUrl = import.meta.env.VITE_IMAGE_URL
const scrollContainer = ref(null)
const cardWidth = ref(0)

const calculateCardWidth = () => {
    cardWidth.value = window.innerWidth * 0.27 // 30% of viewport width
}

onMounted(() => {
    calculateCardWidth()
    window.addEventListener('resize', calculateCardWidth)
})

onUnmounted(() => {
    window.removeEventListener('resize', calculateCardWidth)
})

const formatDate = (dateString) => {
    const options = { month: 'long', day: 'numeric', year: 'numeric' }
    return new Date(dateString).toLocaleDateString('en-US', options)
}

const getPostImage = (post) => {
    if (post.event_id && post.featured_event_image) {
        return post.featured_event_image.thumbImagePath
    } else if (post.images && post.images.length > 0) {
        return post.images[0].thumb_image_path || post.images[0].large_image_path
    } else {
        return post.thumbImagePath
    }
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
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>