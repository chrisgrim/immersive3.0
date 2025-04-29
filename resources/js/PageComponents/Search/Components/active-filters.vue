<template>
    <div v-if="hasActiveFilters" class="mb-8">
        <div class="flex flex-wrap items-center">
            <span class="text-2xl font-medium mr-2">Results filtered by:</span>
            <div class="flex flex-wrap items-center">
                <!-- Categories Pills -->
                <template v-for="(categoryId, index) in activeFilters.categories" :key="`category-${categoryId}`">
                    <div 
                        class="px-1 text-2xl cursor-pointer underline"
                        @click="openFilters"
                    >
                        {{ getCategoryName(categoryId) }}
                    </div>
                    <span v-if="index < activeFilters.categories.length - 1 || activeFilters.tags.length > 0 || activeFilters.atHome || hasPriceFilter" class="text-2xl">,&nbsp;</span>
                </template>
                
                <!-- Tags Pills -->
                <template v-for="(tagId, index) in activeFilters.tags" :key="`tag-${tagId}`">
                    <div 
                        class="px-1 text-2xl cursor-pointer underline"
                        @click="openFilters"
                    >
                        {{ getTagName(tagId) }}
                    </div>
                    <span v-if="index < activeFilters.tags.length - 1 || activeFilters.atHome || hasPriceFilter" class="text-2xl">,&nbsp;</span>
                </template>
                
                <!-- Remote Filter Pill (if active) -->
                <template v-if="activeFilters.atHome">
                    <div 
                        class="px-1 text-2xl cursor-pointer underline"
                        @click="openFilters"
                    >
                        Remote Events
                    </div>
                    <span v-if="hasPriceFilter" class="text-2xl">,&nbsp;</span>
                </template>
                
                <!-- Price Filter Pill (if active) -->
                <div 
                    v-if="hasPriceFilter"
                    class="px-1 text-2xl cursor-pointer underline"
                    @click="openFilters"
                >
                    ${{ activeFilters.price[0] }} - ${{ activeFilters.price[1] }}{{ activeFilters.price[1] === activeFilters.maxPrice ? '+' : '' }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import SearchStore from '@/Stores/SearchStore.vue';
import axios from 'axios';

// Add refs for categories and tags
const categories = ref([]);
const tags = ref([]);

// Get current active filters from the SearchStore
const activeFilters = computed(() => SearchStore.state.filters);

// Check if we have any active filters
const hasActiveFilters = computed(() => {
    return (
        activeFilters.value.categories?.length > 0 || 
        activeFilters.value.tags?.length > 0 || 
        activeFilters.value.atHome === true ||
        hasPriceFilter.value
    );
});

// Check if price filter is active
const hasPriceFilter = computed(() => {
    return (
        activeFilters.value.price?.[0] > 0 || 
        (activeFilters.value.price?.[1] !== undefined && 
         activeFilters.value.price?.[1] < (activeFilters.value.maxPrice || 1000) &&
         activeFilters.value.maxPrice > 0)
    );
});

// Fetch categories and tags for displaying names
const fetchCategoriesAndTags = async () => {
    try {
        const [categoriesResponse, tagsResponse] = await Promise.all([
            axios.get('/api/categories/active/cached'),
            axios.get('/api/genres/active/cached')
        ]);
        
        categories.value = categoriesResponse.data || [];
        tags.value = tagsResponse.data || [];
    } catch (error) {
        console.error('Error fetching categories and tags:', error);
    }
};

// Helper methods to get names from IDs
const getCategoryName = (id) => {
    const category = categories.value.find(cat => cat.id === id);
    return category ? category.name : `Category ${id}`;
};

const getTagName = (id) => {
    const tag = tags.value.find(tag => tag.id === id);
    return tag ? tag.name : `Tag ${id}`;
};

// Function to open the filters modal
const openFilters = () => {
    // Dispatch a global event that the nav component can listen for
    window.dispatchEvent(new CustomEvent('open-filters'));
};

// Fetch data on mount
onMounted(() => {
    fetchCategoriesAndTags();
});
</script>
