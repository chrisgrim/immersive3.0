<template>
    <div class="relative w-full max-w-[52rem]" v-click-outside="() => dropdown = false">
        <div
            ref="search"
            class="w-full z-50">
            <div class="flex items-center w-full border border-neutral-300 rounded-full shadow-[0_4px_16px_rgba(0,0,0,0.15)]">
                <div class="relative flex-1">
                    <svg class="absolute top-1/2 -translate-y-1/2 left-8 w-8 h-8 fill-black z-50">
                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                    </svg>
                    <input
                        class="relative rounded-full p-7 pl-24 bg-transparent w-full font-normal z-40"
                        v-model="searchInput"
                        placeholder="Event and Organizer Search"
                        @input="debounce"
                        @focus="dropdown=true"
                        autocomplete="off"
                        onfocus="value = ''"
                        type="text">
                </div>
                <SearchFilterButton
                    :has-active-filters="hasActiveFilters"
                    @open="$emit('open-filters')"
                />
            </div>
            <ul
                class="absolute bg-white w-full mx-0 overflow-hidden mt-8 p-8 list-none rounded-5xl shadow-custom-7"
                v-if="dropdown">
                <li 
                    class="flex items-center gap-8 hover:bg-neutral-100 p-2" 
                    v-for="item in searchOptions"
                    :key="item.model.id + item.index_name"
                    @click="onSelect(item)">
                    <div 
                        :class="[
                            'w-20 flex-shrink-0 overflow-hidden flex justify-center items-center',
                            item.index_name === 'organizers' ? 'aspect-square rounded-full' : 'aspect-[3/4] rounded-2xl '
                        ]">
                        <picture v-if="item.model.thumbImagePath" class="w-full h-full">       
                            <source 
                                type="image/webp" 
                                :srcset="`${imageUrl}${item.model.thumbImagePath}`"> 
                            <img 
                                :src="`${imageUrl}${item.model.thumbImagePath.slice(0, -4)}jpg`"
                                class="w-full h-full object-cover">
                        </picture>
                        <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                            <p>{{item.model.name.slice(0, 1)}}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-1xl leading-6 font-semibold mt-2">
                            {{item.model.name}}
                        </p>
                        <p class="text-lg leading-6 font-normal mt-2">
                            {{item.model.tag_line}}
                        </p>
                    </div>
                </li>
            </ul>

            <!-- Same positioning language as the results dropdown above —
                 nested here (bubbles up to the root `position: relative`
                 div, which is also the whole bar's max-w-[52rem] bounds) so
                 `width: 100%` matches the bar's own real rendered width. -->
            <Filters
                v-if="showFilters"
                :model-value="filtersState"
                :show-price="isSearchPage"
                @close="$emit('close-filters')"
                @filter-change="$emit('filters-changed', $event)"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import SearchFilterButton from './search-filter-button.vue';
import Filters from './filters.vue';
import SearchStore from '@/Stores/SearchStore.vue';

defineProps({
    hasActiveFilters: {
        type: Boolean,
        default: false
    },
    showFilters: {
        type: Boolean,
        default: false
    }
});
defineEmits(['open-filters', 'close-filters', 'filters-changed']);

const filtersState = computed(() => SearchStore.state.filters);
const isSearchPage = computed(() => window.location.pathname.includes('/search'));

const searchInput = ref('');
const searchOptions = ref([]);
const dropdown = ref(false);
const imageUrl = import.meta.env.VITE_IMAGE_URL;
let timeout = null;

const generateSearchList = async () => {
    try {
        const response = await axios.get('/api/search/nav/names', { 
            params: { keywords: searchInput.value } 
        });
        searchOptions.value = response.data;
    } catch (error) {
        console.error('Error fetching search results:', error);
    }
};

const onSelect = (item) => {
    if (item.index_name === 'organizers') window.location.href = `/organizers/${item.model.slug}`;
    if (item.index_name === 'events') window.location.href = `/events/${item.model.slug}`;
};

const debounce = () => {
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => {
        generateSearchList();
    }, 200); // delay
};

onMounted(() => {
    generateSearchList();
});
</script>