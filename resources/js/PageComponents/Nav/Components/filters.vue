<template>
    <div
        v-click-outside="handleClose"
        class="filters-dropdown absolute bg-white overflow-hidden rounded-5xl shadow-custom-7 flex flex-col z-40"
        @click.stop
    >
                <!-- Header -->
                <div class="flex justify-center items-center p-4 h-24 min-h-24 flex-shrink-0 border-b border-neutral-200">
                    <p class="text-2xl font-bold">Filters</p>
                    <button 
                        @click="$emit('close')" 
                        class="absolute top-4 z-20 right-8 items-center justify-center rounded-full p-0 w-16 h-16 flex bg-white"
                    >
                        <svg class="w-8 h-8 text-red-500">
                            <use xlink:href="/storage/website-files/icons.svg#ri-close-line" />
                        </svg>
                    </button>
                </div>

                <!-- Price/Categories/Tags body — extracted into search-filter-fields.vue,
                     shared with the Hub saved-search editor. -->
                <search-filter-fields
                    v-model="fieldsModel"
                    :max-price="selectedFilters.maxPrice"
                    :at-home="selectedFilters.atHome"
                    :show-price="showPrice"
                />

                <!-- Footer -->
                <div class="w-full bg-white p-8 flex-shrink-0 mt-auto">
                    <div class="flex justify-between items-center">
                        <button 
                            @click="clearAll" 
                            class="underline text-neutral-600 hover:text-neutral-800"
                        >
                            Clear all
                        </button>
                        <div class="flex space-x-4">
                            <button 
                                @click="$emit('close')" 
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="submitSelection" 
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-neutral-800 text-xl"
                            >
                                Apply filters
                            </button>
                        </div>
                    </div>
                </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective'
import SearchFilterFields from '@/PageComponents/Search/Components/search-filter-fields.vue'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
        default: () => ({
            categories: [],
            tags: [],
            price: [0, null],
            atHome: false
        })
    },
    showPrice: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close', 'update:modelValue', 'filter-change'])

// One-time snapshot of modelValue, unchanged from before this file's body
// was extracted into search-filter-fields.vue — that child is now a
// controlled view onto categories/tags/price via fieldsModel below; this
// still owns the full picture (maxPrice/atHome/remoteLocation/searchingByPrice)
// for clearAll/submitSelection/hasActiveFilters.
const selectedFilters = ref({
    ...props.modelValue
})

// The child only needs to see/change categories, tags, and price — everything
// else it either doesn't touch (atHome/maxPrice, passed as separate props) or
// doesn't know about at all (remoteLocation/searchingByPrice).
const fieldsModel = computed({
    get: () => ({
        categories: selectedFilters.value.categories,
        tags: selectedFilters.value.tags,
        price: selectedFilters.value.price,
    }),
    set: (value) => {
        selectedFilters.value = { ...selectedFilters.value, ...value }
    },
})

const submitSelection = () => {
    // Check if price slider has been adjusted from default values
    const isPriceAdjusted = selectedFilters.value.price[0] !== 0 || 
                           selectedFilters.value.price[1] !== props.maxPrice;
    
    selectedFilters.value = {
        ...selectedFilters.value,
        searchingByPrice: isPriceAdjusted,
        price: isPriceAdjusted 
            ? selectedFilters.value.price 
            : [0, props.maxPrice]
    };
    
    // Emit the final filter state when Apply is clicked
    emit('filter-change', selectedFilters.value);
    // Close the modal
    emit('close');
}

const clearAll = () => {
    const currentMaxPrice = selectedFilters.value.maxPrice; // Store current maxPrice
    selectedFilters.value = {
        categories: [],
        tags: [],
        price: [0, currentMaxPrice], // Use stored maxPrice
        maxPrice: currentMaxPrice, // Preserve maxPrice
        searchingByPrice: false,
        // Preserve, don't reset — atHome reflects which search tab the user
        // is on (see the removed Remote Events toggle), not a filter this
        // panel controls. Resetting it to false would silently switch a
        // user out of an At Home search just for clicking "Clear all".
        atHome: selectedFilters.value.atHome,
        // Same reasoning — the specific remote-location type isn't
        // something this panel controls either now, so it shouldn't be
        // dropped just because this object is being rebuilt from scratch.
        remoteLocation: selectedFilters.value.remoteLocation
    };
}

// Add directive
const vClickOutside = ClickOutsideDirective

const handleClose = () => {
    emit('close')
}

// Add a computed property for hasActiveFilters
const hasActiveFilters = computed(() => {
    // atHome isn't a discretionary filter anymore (no toggle to set it here
    // — see the removed Remote Events section) — it just reflects which
    // search tab the user is on, so it shouldn't make the filter button
    // read as "active" on its own.
    const hasActiveFilter =
        selectedFilters.value.categories?.length > 0 ||
        selectedFilters.value.tags?.length > 0;

    // Check price filters
    const hasPriceFilter = selectedFilters.value.price[0] !== 0 || 
                          selectedFilters.value.price[1] !== selectedFilters.value.maxPrice;
    
    return hasActiveFilter || hasPriceFilter;
});

// Expose hasActiveFilters for parent components
defineExpose({ hasActiveFilters });
</script>

<style scoped>
/* Same positioning language as the other nav dropdowns
   (.location-search-calendar, the location suggestions list) rather than
   the old full-screen modal — nested directly inside the pill itself
   (a `position: relative` ancestor, see location-search.vue /
   events-search.vue) so `width: 100%` matches the pill's own real
   rendered width, not a separately-maintained value. */
.filters-dropdown {
    top: 100%;
    left: 0;
    width: 100%;
    margin-top: 0.8rem;
    max-height: 60vh;
}
</style>