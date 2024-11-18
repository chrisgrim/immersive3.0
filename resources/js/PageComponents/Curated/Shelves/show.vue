<template>
    <div class="rounded-2xl mb-8">
        <div class="flex">
            <h3 class="text-4xl font-semibold">{{ shelf.name }}</h3>
        </div>
        <div class="relative w-full inline-block">
            <Album
                :title="true"
                :text="true"
                :loadposts="shelf.published_posts.data"
                :value="shelf"
                :community="community"
                :draggable="owner" />
        </div>
    </div>
</template>

<script setup>
import Album from './Components/album.vue'

defineProps({
    shelf: {
        type: Object,
        required: true
    },
    owner: {
        type: Boolean,
        default: false
    },
    community: {
        type: Object,
        required: true
    }
})
</script>