<template>
    <div class="filter">
        <div class="w-full z-30 relative p-8 md:py-8 md:px-8">
            <div class="flex gap-2 items-center w-full">
                <button class="border border-gray-300 py-4 px-8 rounded-2xl" @click="onSearch">Filters</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineEmits, defineExpose } from 'vue'

const emit = defineEmits(['update', 'clear'])

const props = defineProps({
    modelValue: Object,
    events: Array,
    filter: String,
    tags: Array,
    categories: Array,
    searchedCategories: Array,
    inPersonCategories: Array
})

const onSearch = async () => {
    console.log('onSearch')
}

const onNext = async (page) => {
    try {
        // Add your pagination logic here
        // For example:
        const response = await fetch(`/your-search-endpoint?page=${page}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(props.modelValue)
        })
        const data = await response.json()
        emit('update', data)
    } catch (error) {
        console.error('Error in nav pagination:', error)
    }
}

// Expose methods to parent component
defineExpose({
    onSearch,
    onNext
})
</script>
