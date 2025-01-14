<template>
    <teleport to="body">
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-4xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Tags</h2>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 pt-0 overflow-y-auto flex-1">
                    <div class="grid grid-cols-2 gap-4">
                        <button 
                            v-for="genre in genres" 
                            :key="genre.id"
                            class="flex items-center gap-4 p-4 rounded-full text-left border transition-all"
                            :class="{ 
                                'border-2 border-black bg-neutral-100': isSelected(genre.id),
                                'border-gray-200 hover:bg-gray-50': !isSelected(genre.id)
                            }"
                            @click="toggleTag(genre.id)"
                        >
                            <span class="text-1xl text-gray-800">{{ genre.name }}</span>
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

const props = defineProps({
    genres: {
        type: Array,
        required: true
    },
    selectedTags: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close', 'update:selected'])

const localSelected = ref([])

onMounted(() => {
    localSelected.value = props.selectedTags ? [...new Set(props.selectedTags)] : [];
})

const toggleTag = (genreId) => {
    if (localSelected.value.includes(genreId)) {
        // Remove if present
        localSelected.value = localSelected.value.filter(id => id !== genreId);
    } else {
        // Add if not present
        localSelected.value.push(genreId);
    }
}

const isSelected = (genreId) => {
    return localSelected.value.includes(genreId);
}

const submitSelection = () => {
    emit('update:selected', localSelected.value)
    emit('close')
}
</script>