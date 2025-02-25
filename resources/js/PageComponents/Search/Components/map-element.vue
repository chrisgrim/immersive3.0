<template>
    <div class="w-full">
        <a :href="`/events/${data.slug}?name=${name}&lat=${lat}&lng=${lng}`" class="flex gap-4 p-6 items-stretch">
            <div class="w-28 aspect-[3/4] flex-shrink-0 rounded-xl overflow-hidden">
                <div 
                    class="w-full h-full bg-cover bg-center bg-no-repeat rounded-x-xl" 
                    :style="backgroundImage" 
                />
            </div>
            <div class="flex-1 flex flex-col justify-between py-2">
                <span class="text-2xl font-medium text-black line-clamp-2">{{ data.name }}</span>
                <span class="text-lg text-black">{{ formattedPriceRange }}</span>
            </div>
        </a>
    </div>
</template>
<script setup>
import { ref, onMounted, computed } from 'vue'

const props = defineProps({
    data: {
        type: Object,
        required: true
    }
})

const name = ref(new URL(window.location.href).searchParams.get("name"))
const lat = ref(new URL(window.location.href).searchParams.get("lat"))
const lng = ref(new URL(window.location.href).searchParams.get("lng"))

const canUseWebP = () => {
    const webp = document.createElement('canvas')
        .toDataURL('image/webp')
        .indexOf('data:image/webp') === 0
    
    return webp
}

const backgroundImage = computed(() => {
    const imageUrl = canUseWebP() 
        ? `${import.meta.env.VITE_IMAGE_URL}${props.data.thumbImagePath}`
        : `${import.meta.env.VITE_IMAGE_URL}${props.data.thumbImagePath.slice(0, -4)}jpg`
    
    return `background-image: url('${imageUrl}')`
})

const formattedPriceRange = computed(() => {
    if (!props.data.price_range) return ''
    return props.data.price_range.replace(/\.00/g, '')
})

onMounted(() => {
    canUseWebP()
})
</script>