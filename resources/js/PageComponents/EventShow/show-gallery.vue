<template>
    <div class="fixed inset-0 bg-white z-50 overflow-y-auto">
        <!-- Header with back and share buttons -->
        <div class="sticky top-0 bg-white px-10 py-6 flex justify-between items-center border-b border-neutral-200">
            <!-- Back Button -->
            <button 
                @click="$emit('close')"
                class="w-14 h-14 bg-white rounded-full flex items-center justify-center shadow-lg"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </button>

            <h1 class="text-2xl font-semibold">Photos</h1>

            <!-- Share Button -->
            <button 
                @click="handleShare"
                class="w-14 h-14 bg-white rounded-full flex items-center justify-center shadow-lg"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0l-4 4m4-4v12"/>
                </svg>
            </button>
        </div>

        <!-- Photos Grid - Skip first image -->
        <div class="p-10 space-y-10">
            <div v-for="(image, index) in images.slice(1)" :key="index" class="w-full">
                <picture>
                    <source type="image/webp" :srcset="imageUrl + image.large_image_path">
                    <img 
                        class="w-full object-cover rounded-lg"
                        :src="imageUrl + image.large_image_path.replace('.webp', '.jpg')"
                        :alt="eventName + ' Immersive Event - Photo ' + (index + 1)"
                    >
                </picture>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    eventName: String,
    images: Array,
    imageUrl: {
        type: String,
        default: () => window.config.app.image_url
    }
});

const handleShare = async () => {
    if (navigator.share) {
        try {
            await navigator.share({
                title: props.eventName,
                text: 'Check out these photos',
                url: window.location.href
            });
        } catch (err) {
            window.dispatchEvent(new CustomEvent('showShareModalFallback'));
        }
    } else {
        window.dispatchEvent(new CustomEvent('showShareModalFallback'));
    }
};

window.addEventListener('showShareModalFallback', () => {
    alert('Sharing options will appear here!');
});
</script>

<style scoped>
button {
    transition: transform 0.2s;
}
button:active {
    transform: scale(0.95);
}
</style>