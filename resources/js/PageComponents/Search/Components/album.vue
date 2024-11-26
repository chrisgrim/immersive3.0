<template>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div 
            v-for="card in items" 
            :key="card.id"
            class="flex flex-col group">
            <a :href="getUrl(card)" class="block h-full flex flex-col">
                <!-- Event Image Container with 4:5 aspect ratio -->
                <div class="relative mb-3 overflow-hidden rounded-lg bg-gray-100 transition-transform duration-200 ease-in-out group-hover:scale-[1.02]">
                    <div class="pb-[125%]"></div>
                    <picture v-if="card.thumbImagePath" class="absolute inset-0">
                        <source 
                            type="image/webp" 
                            :srcset="`${imageUrl}${card.thumbImagePath}`"> 
                        <img 
                            loading="lazy" 
                            class="h-full w-full object-cover"
                            :src="`${imageUrl}${card.thumbImagePath.slice(0, -4)}jpg`" 
                            :alt="`${card.name} Immersive Event`">
                    </picture>
                </div>

                <!-- Content wrapper -->
                <div class="flex flex-col flex-grow">
                    <button 
                        v-if="card.category"
                        @click.prevent="handleCategoryClick(card.category.id)"
                        class="self-start py-2 px-4 mb-4 rounded-full border border-gray-300 uppercase text-lg transition-colors duration-200 hover:bg-black hover:text-white hover:border-black">
                        {{ card.category.name }}
                    </button>

                    <h3 class="my-3 text-2xl font-semibold leading-tight line-clamp-2">{{ card.name }}</h3>
                    
                    <p v-if="card.tag_line" class="mb-3 text-1xl leading-normal text-gray-600 line-clamp-2">
                        {{ card.tag_line }}
                    </p>

                    <ul class="flex flex-wrap gap-2 mb-3">
                        <li 
                            v-for="itemTag in getEventTags(card)" 
                            :key="itemTag.id"
                            class="text-lg text-gray-600">
                            {{ itemTag.name }}
                        </li>
                    </ul>

                    <!-- Price pushed to bottom -->
                    <p class="text-1xl font-semibold mt-auto">{{ getFixedPrice(card) }}</p>
                </div>
            </a>
        </div>
    </div>
</template>

<script setup>
import { defineProps, computed } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        required: true
    },
    user: {
        type: Object,
        default: () => ({})
    }
})

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL)

const getUrl = (card) => {
    return `/events/${card.slug}`
}

const getEventTags = (item) => {
    return item.genres.slice(0, 3)
}

const getFixedPrice = (event) => {
    return event.price_range.replace(/\d+(\.\d{1,2})?/g, dec => parseInt(dec))
}

const handleCategoryClick = (categoryId) => {
    if (window.location.pathname === '/' || window.location.pathname === '/index') {
        window.location.href = `/index/search?category=${categoryId}&searchType=allEvents`
        return
    }
    
    const params = new URLSearchParams(window.location.search)
    params.set('category', categoryId)
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`)
    
    window.dispatchEvent(new CustomEvent('filter-update', {
        detail: {
            type: 'category',
            value: [categoryId]
        }
    }))

    window.dispatchEvent(new CustomEvent('category-update', {
        detail: categoryId
    }))
}
</script>