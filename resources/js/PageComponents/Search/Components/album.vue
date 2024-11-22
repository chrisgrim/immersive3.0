<template>
    <div>
        <div 
            v-for="(card) in items" 
            :key="card.id">
            <div class="flex border-b pb-4 mb-4">
                <div class="w-[29rem] relative">
                    <a 
                        :href="getUrl(card)" 
                        class="absolute h-full w-full left-0 top-0 rounded-2xl z-20" />
                    <div class="rounded-2xl relative overflow-hidden bg-gray-100 transition-transform duration-200 ease-in-out group-hover:scale-[1.02]">
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
                </div>
                <div class="relative m-4 w-full">
                    <div 
                        v-if="card.category"
                        class="category">
                        <button 
                            @click="handleCategoryClick(card.category.id)"
                            class="py-2 px-4 mb-4 rounded-full border border-gray-300 uppercase text-lg transition-colors duration-200 hover:bg-black hover:text-white hover:border-black">
                            {{ card.category.name }}
                        </button>
                    </div>
                    <a  
                        v-if="card.name"
                        :href="getUrl(card)">
                        <p class="font-medium text-black">{{ card.name }}</p>
                    </a>
                    <template v-if="card.tag_line">
                        <div class="mb-4 block text-ellipsis line-clamp-2 w-full max-h-16 text-xl">
                            {{ card.tag_line }} 
                        </div>
                    </template>
                    <ul class="m-0 p-0 flex">
                        <li 
                            class="text-xl list-disc mr-4 ml-4 first:list-none first:ml-0" 
                            v-for="itemTag in getEventTags(card)" 
                            :key="itemTag.id">
                            {{ itemTag.name }}
                        </li>
                    </ul>
                    <div class="absolute right-0 bottom-0 text-right font-extrabold">
                        {{ getFixedPrice(card) }}
                    </div>
                </div>
            </div>
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