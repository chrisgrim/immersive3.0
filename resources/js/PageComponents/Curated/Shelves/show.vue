<template>
    <div class="w-full pb-10">
        <!-- First Shelf - Grid Layout -->
        <GridShelf 
            v-if="isFirstShelf"
            :shelf="shelf"
            :rows="1"
            :community="community" />

        <!-- Second Shelf - Carousel Layout -->
        <CarouselShelf 
            v-else-if="isSecondShelf"
            :shelf="shelf"
            :community="community" />

        <!-- Third Shelf - Spotlight Layout -->
        <SpotlightShelf 
            v-else-if="isThirdShelf"
            :shelf="shelf"
            :community="community" />

        <!-- Remaining Shelves - Default Album Layout -->
        <GridShelf 
            v-else
            :shelf="shelf"
            :rows="calculateRows(shelf)"
            :community="community" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import GridShelf from './Components/grid-shelf.vue'
import CarouselShelf from './Components/carousel-shelf.vue'
import SpotlightShelf from './Components/spotlight-shelf.vue'

const props = defineProps({
    shelf: {
        type: Object,
        required: true
    },
    community: {
        type: Object,
        required: true
    },
    index: {
        type: Number,
        required: true
    }
})

const isFirstShelf = computed(() => props.index === 0)
const isSecondShelf = computed(() => props.index === 1)
const isThirdShelf = computed(() => props.index === 2)

const calculateRows = (shelf) => {
    const postCount = shelf.published_posts.data.length
    return Math.ceil(postCount / 4) // 4 posts per row
}
</script>