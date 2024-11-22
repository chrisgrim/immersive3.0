<template>
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

        <!-- Modal panel -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full max-w-3xl">
                <!-- Content -->
                <div class="bg-white px-8 py-6">
                    <div class="grid grid-cols-2 gap-4">
                        <button 
                            v-for="category in categories" 
                            :key="category.id"
                            class="flex items-center gap-4 p-4 rounded-lg hover:bg-gray-50 text-left"
                        >
                            <div class="w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center">
                                <!-- Add checkmark if selected -->
                            </div>
                            <span class="text-lg text-gray-800">{{ category.name }}</span>
                        </button>
                    </div>

                    <!-- Footer -->
                    <div class="mt-8 flex justify-between">
                        <button 
                            @click="$emit('close')"
                            class="text-lg text-gray-600 hover:text-gray-800"
                        >
                            Cancel
                        </button>
                        <button 
                            class="px-8 py-2 bg-[#E94362] text-white rounded-full text-lg hover:bg-[#d33d5a]"
                        >
                            Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    categories: {
        type: Array,
        required: true
    }
})

const selectedCategories = ref([])

const toggleCategory = (categoryId) => {
    const index = selectedCategories.value.indexOf(categoryId)
    if (index === -1) {
        selectedCategories.value.push(categoryId)
    } else {
        selectedCategories.value.splice(index, 1)
    }
}
</script>