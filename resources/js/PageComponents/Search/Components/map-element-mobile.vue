<template>
    <div class="w-full">
        <a :href="`/events/${data.slug}?name=${name}&lat=${lat}&lng=${lng}`" class="flex gap-4">
            <div class="w-32 aspect-[3/4] flex-shrink-0">
                <div 
                    class="w-full h-full bg-cover bg-center bg-no-repeat rounded-x-xl" 
                    :style="backgroundImage" 
                />
            </div>
            <div class="flex flex-col justify-between p-6">
                <span class="text-2xl font-bold text-black line-clamp-2">{{ data.name }}</span>
                <span class="text-md text-black">{{ data.tag_line }}</span>
                <span class="text-base text-black font-bold">{{ data.price_range ? data.price_range : '' }}</span>
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

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>