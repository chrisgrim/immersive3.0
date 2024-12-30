<template>
    <button 
        class="p-2.5 text-gray-600 hover:text-black rounded-lg hover:bg-gray-100"
        :class="{ 'bg-gray-100 text-black': isActive && isActive() }"
        @click="action"
        :title="title">
        <svg class="w-8 h-8">
            <use :xlink:href="`/storage/website-files/icons.svg#ri-${icon}`" />
        </svg>
    </button>
</template>

<script setup>
defineProps({
    icon: {
        type: String,
        required: true
    },
    title: {
        type: String,
        required: true
    },
    action: {
        type: Function,
        required: true
    },
    isActive: {
        type: Function,
        default: null
    }
})
</script>