<template>
    <div class="relative w-full" style="padding-top: 56.25%"> <!-- 16:9 Aspect Ratio -->
        <iframe
            :src="youtubeEmbedUrl"
            :title="alt"
            class="absolute inset-0 w-full h-full rounded-2xl"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
        ></iframe>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    src: {
        type: String,
        required: true
    },
    alt: {
        type: String,
        default: 'Video Player'
    }
})

const youtubeEmbedUrl = computed(() => {
    // Handle different YouTube URL formats
    let videoId = props.src

    // If it's a full YouTube URL, extract the video ID
    if (props.src.includes('youtube.com') || props.src.includes('youtu.be')) {
        const url = new URL(props.src)
        if (props.src.includes('youtube.com')) {
            videoId = url.searchParams.get('v')
        } else {
            videoId = url.pathname.slice(1)
        }
    }

    // Return the embed URL with additional parameters
    return `https://www.youtube.com/embed/${videoId}?rel=0&modestbranding=1`
})
</script>
