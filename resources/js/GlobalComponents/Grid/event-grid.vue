<template>
    <div class="grid grid-cols-2 gap-8" 
         :class="{
            'lg:grid-cols-2': columns === 2,
            'md:grid-cols-3': columns === 3,
            'md:grid-cols-3 lg:grid-cols-4': columns === 4,
            'md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5': columns === 5,
            'md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6': columns === 6
         }">
        <div 
            v-for="card in items" 
            :key="card.id"
            class="flex flex-col group w-full min-w-0">
            <a 
                :href="getUrl(card)" 
                class="block h-full flex flex-col"
                @click="(e) => hasClickListener && handleClick(e, card)"
            >
                <!-- Event Image Container with 3:4 aspect ratio -->
                <div class="relative overflow-hidden rounded-2xl bg-gray-100 transition-transform duration-200 ease-in-out group-hover:scale-[1.02]">
                    <div class="pb-[133.33%]"></div>
                    <picture v-if="card.images?.length" class="absolute inset-0">
                        <source 
                            type="image/webp" 
                            :srcset="`${imageUrl}${card.images[0].large_image_path}`"
                        > 
                        <img 
                            loading="lazy" 
                            class="h-full w-full object-cover"
                            :src="`${imageUrl}${card.images[0].large_image_path.slice(0, -4)}jpg`" 
                            :alt="`${card.name} Event`"
                        >
                    </picture>
                    <picture v-else-if="card.largeImagePath" class="absolute inset-0">
                        <source 
                            type="image/webp" 
                            :srcset="`${imageUrl}${card.largeImagePath}`"
                        > 
                        <img 
                            loading="lazy" 
                            class="h-full w-full object-cover"
                            :src="`${imageUrl}${card.largeImagePath.slice(0, -4)}jpg`" 
                            :alt="`${card.name} Event`"
                        >
                    </picture>
                </div>

                <!-- Content wrapper -->
                <div class="flex flex-col flex-grow min-h-0">
                    <button 
                        v-if="card.category"
                        @click.prevent="handleCategoryClick(card.category.id)"
                        class="mt-6 uppercase text-md font-light text-left break-words hyphens-auto w-full block overflow-hidden text-ellipsis"
                    >
                        {{ card.category.name }}
                    </button>

                    <h3 class="mt-2 mb-4 text-2xl text-black font-medium">
                        <span class="line-clamp-2 block">{{ card.name }}</span>
                    </h3>
                    
                    <!-- <p v-if="card.tag_line" class="mb-3 text-1xl leading-normal text-gray-600 line-clamp-2">
                        {{ card.tag_line }}
                    </p> -->
                    <!-- Price pushed to bottom -->
                    <p v-if="card.price_range" class="text-1xl mt-auto">{{ getFixedPrice(card) }}</p>
                </div>
            </a>
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits, computed } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        required: true
    },
    user: {
        type: Object,
        default: () => ({})
    },
    columns: {
        type: Number,
        default: 3,
        validator: (value) => [2, 3, 4, 5, 6].includes(value)
    },
    hasClickListener: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['click:item'])

const handleClick = (event, card) => {
    event.preventDefault()
    emit('click:item', card)
}

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL)

const getUrl = (card) => {
    return `/events/${card.slug}`
}

const getEventTags = (item) => {
    return item.genres?.slice(0, 3) || []
}

const getFixedPrice = (event) => {
    if (!event.price_range) return ''
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