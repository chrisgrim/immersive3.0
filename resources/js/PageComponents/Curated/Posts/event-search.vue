<template>
    <div class="w-full m-auto">
        <div class="w-full relative" ref="dropdownRef" v-click-outside="handleClickOutside">
            <div class="w-full relative">
                <svg 
                    :class="{'rotate-90': dropdown}"
                    class="w-10 h-10 fill-black absolute z-10 right-4 top-8 transition-transform duration-200">
                    <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                </svg>
                <input 
                    ref="searchInputRef"
                    :class="[
                        'text-2xl relative p-8 w-full border rounded-3xl transition-all duration-200',
                        {
                            'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': true,
                            'focus:rounded-t-3xl focus:rounded-b-none': dropdown 
                        }
                    ]"
                    v-model="searchInput"
                    placeholder="Search for event"
                    @input="debounce"
                    @focus="onDropdown"
                    @keydown.enter.prevent="handleEnter"
                    autocomplete="off"
                    type="text">
                <ul 
                    class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border border-[#222222] shadow-focus-black max-h-[40rem]" 
                    v-if="dropdown && searchOptions.length > 0">
                    <li 
                        class="py-2 px-6 flex items-center gap-8 hover:bg-neutral-100 transition-colors duration-200" 
                        v-for="item in searchOptions"
                        :key="item.model.id + item.index_name"
                        @click="onSelect(item)"
                        @mousedown.stop.prevent>
                        <div class="w-16 h-16 min-w-[4rem] min-h-[4rem] rounded-2xl overflow-hidden flex-shrink-0">
                            <picture v-if="item.model.thumbImagePath" class="block w-20 h-20">       
                                <source 
                                    type="image/webp" 
                                    :srcset="`${imageUrl}${item.model.thumbImagePath}`"> 
                                <img 
                                    :src="`${imageUrl}${item.model.thumbImagePath.slice(0, -4)}jpg`"
                                    class="w-20 h-20 object-cover"
                                    style="aspect-ratio: 1/1;">
                            </picture>
                            <div v-else class="w-20 h-20 bg-gray-200 flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-400">
                                    <use xlink:href="/storage/website-files/icons.svg#ri-image-line"></use>
                                </svg>
                            </div>
                        </div>
                        <span class="text-xl">{{ item.model.name }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';

const emit = defineEmits(['select']);

const searchInput = ref('');
const searchOptions = ref([]);
const dropdown = ref(false);
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const timeout = ref(null);
const searchInputRef = ref(null);
const dropdownRef = ref(null);

const generateSearchList = async (isInitial = false) => {
    try {
        const response = await axios.get('/api/search/nav/events', { 
            params: { 
                keywords: isInitial ? '' : searchInput.value.trim(),
                limit: 10,
                type: 'events'
            }
        });
        searchOptions.value = response.data.filter(item => item.index_name === 'events');
    } catch (error) {
        console.error('Error fetching events:', error);
        searchOptions.value = [];
    }
};

const onSelect = (item) => {
    emit('select', item.model);
    dropdown.value = false;
    searchInput.value = '';
    searchInputRef.value?.blur();
};

const debounce = () => {
    if (timeout.value) clearTimeout(timeout.value);
    timeout.value = setTimeout(() => {
        generateSearchList();
    }, 300);
};

const onDropdown = async () => {
    dropdown.value = true;
    if (!searchInput.value && searchOptions.value.length === 0) {
        await generateSearchList(true);
    }
};

const closeDropdown = () => {
    dropdown.value = false;
};

const handleClickOutside = (event) => {
    const dropdownElement = dropdownRef.value;
    if (dropdownElement && !dropdownElement.contains(event.target)) {
        closeDropdown();
    }
};

const handleEnter = () => {
    if (searchOptions.value.length > 0) {
        onSelect(searchOptions.value[0]);
    }
};

onMounted(async () => {
    // Load initial events when component mounts
    await generateSearchList(true);
});
</script>

<style scoped>
.shadow-lg {
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}
</style>