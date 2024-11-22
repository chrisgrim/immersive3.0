<template>
    <div class="w-full min-h-[25rem]">
        <a :href="`/events/${data.slug}?name=${name}&lat=${lat}&lng=${lng}`">
            <div 
                class="h-80 bg-cover bg-no-repeat border-t-2xl" 
                :style="backgroundImage" />
            <div class="p-4 flex flex-col">
                <span class="text-2xl font-bold m-0 text-black">{{ data.name }}</span>
                <span class="text-1xl m-0 text-black">{{ data.price_range ? data.price_range : '' }}</span>
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

onMounted(() => {
    canUseWebP()
})
</script>