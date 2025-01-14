<template>
    <teleport to="body">
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-4xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Categories</h2>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 pt-0 overflow-y-auto flex-1">
                    <div class="grid grid-cols-2 gap-4">
                        <button 
                            v-for="category in categories" 
                            :key="category.id"
                            class="flex items-center gap-4 p-4 rounded-full text-left border transition-all"
                            :class="{ 
                                'border-2 border-black bg-neutral-100': isSelected(category.id),
                                'border-gray-200 hover:bg-gray-50': !isSelected(category.id)
                            }"
                            @click="toggleCategory(category.id)"
                        >
                            <img 
                                :src="getCategoryIcon(category)"
                                :alt="category.name"
                                class="w-12 h-12 object-cover rounded-full"
                            >
                            <span class="text-1xl text-gray-800">{{ category.name }}</span>
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="$emit('close')"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="submitSelection"
                            class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                        >
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const imageUrl = import.meta.env.VITE_IMAGE_URL

const props = defineProps({
    categories: {
        type: Array,
        required: true
    },
    selectedCategories: {
        type: Array,
        required: true
    }
})

const emit = defineEmits(['close', 'update:selected'])

const localSelected = ref([...props.selectedCategories])

onMounted(() => {
    localSelected.value = [...new Set(props.selectedCategories)];
})

const toggleCategory = (categoryId) => {
    console.log('Toggling category:', categoryId); // Debug log
    console.log('Before toggle:', localSelected.value); // Debug log
    
    if (localSelected.value.includes(categoryId)) {
        // Remove if present
        localSelected.value = localSelected.value.filter(id => id !== categoryId);
    } else {
        // Add if not present
        localSelected.value.push(categoryId);
    }
    
    console.log('After toggle:', localSelected.value); // Debug log
}

const isSelected = (categoryId) => {
    return localSelected.value.includes(categoryId);
}

const submitSelection = () => {
    emit('update:selected', localSelected.value)
    emit('close')
}

const getCategoryIcon = (category) => {
    return category.images?.find(img => img.rank === 1)?.thumb_image_path 
        ? `${imageUrl}${category.images.find(img => img.rank === 1).thumb_image_path}`
        : ''
}
</script>