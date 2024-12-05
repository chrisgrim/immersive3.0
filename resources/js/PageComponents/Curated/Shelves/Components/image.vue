<template>
    <div class="relative overflow-hidden rounded-xl w-full h-full">
        <img 
            v-if="displayImage"
            class="w-full h-full object-cover"
            loading="lazy" 
            :src="`${imageUrl}${displayImage}`" 
            :alt="`${element?.name}`"
        >
    </div>
</template>

<script setup>
import { computed } from 'vue'

const imageUrl = import.meta.env.VITE_IMAGE_URL

const props = defineProps({
    element: {
        type: Object,
        default: () => ({})
    }
})

const displayImage = computed(() => {
    if (!props.element) return null
    
    // Check for event featured image
    if (props.element.featured_event_image?.thumbImagePath) {
        return props.element.featured_event_image.thumbImagePath
    }
    
    // Check for regular thumb image
    if (props.element.thumbImagePath) {
        return props.element.thumbImagePath
    }
    
    return null
})
</script>