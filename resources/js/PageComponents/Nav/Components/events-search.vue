<template>
    <div style="width:100%" v-click-outside="() => dropdown = false">
        <div 
            ref="search"
            class="w-full z-50">
            <div class="w-full m-auto">
                <svg class="absolute top-8 left-8 w-8 h-8 fill-black z-50">
                    <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                </svg>
                <input 
                    class="relative rounded-full p-7 pl-24 border border-neutral-300 w-full font-normal z-40 focus:border-none focus:rounded-full focus:shadow-custom-7"
                    v-model="searchInput"
                    placeholder="Event and Organizer Search"
                    @input="debounce"
                    @focus="dropdown=true"
                    autocomplete="off"
                    onfocus="value = ''" 
                    type="text">
            </div>
            <ul 
                class="bg-white relative w-full m-auto overflow-hidden mt-8 p-8 list-none rounded-5xl shadow-custom-7"
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
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

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