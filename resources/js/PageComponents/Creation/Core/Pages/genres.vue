<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>What genres best describe your event?</h2>
                <p class="text-gray-500 font-normal mt-4">Tags will help users with the more finite categorization of your event.</p>
                
                <div class="w-full mt-14">
                    <div class="w-full relative" ref="genresDrop" v-click-outside="handleClickOutside">
                        <div class="w-full relative">
                            <!-- Dropdown Arrow -->
                            <svg 
                                :class="{'rotate-90': dropdown}"
                                class="w-10 h-10 fill-black absolute z-10 right-4 top-8">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                            </svg>

                            <!-- Search Input -->
                            <input 
                                ref="searchInput"
                                class="text-2xl relative p-8 w-full border mb-12 rounded-3xl focus:rounded-t-3xl focus:rounded-b-none h-24"
                                v-model="searchTerm"
                                placeholder="Select or create genres"
                                @input="filterGenres"
                                @focus="onDropdown"
                                @keydown.enter="createNewGenre"
                                autocomplete="off"
                                type="text">

                            <!-- Dropdown List -->
                            <ul 
                                class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border-[#e5e7eb] border max-h-[40rem]" 
                                v-if="dropdown">
                                <li v-if="searchTerm && !isExistingGenre"
                                    class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300 text-blue-600" 
                                    @click="createNewGenre"
                                    @mousedown.stop.prevent>
                                    Create "{{ searchTerm }}"
                                </li>
                                <li 
                                    v-for="genre in filteredGenres"
                                    :key="genre.id"
                                    class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300" 
                                    @click="selectGenre(genre)"
                                    @mousedown.stop.prevent>
                                    {{ genre.name }}
                                </li>
                            </ul>

                            <!-- Selected Genres -->
                            <div v-if="event.genres && event.genres.length > 0">
                                <p class="text-xl mt-8">Selected genres:</p>
                                <ul class="mt-4 flex flex-wrap gap-6 mx-0">
                                    <li 
                                        v-for="genre in event.genres"
                                        :key="genre.id"
                                        class="border h-24 border-[#e5e7eb] flex text-[#222222] px-6 pb-4 rounded-2xl relative flex flex-col justify-end hover:border-black hover:bg-gray-100 hover:shadow-[0_0_0_1.5px_black]"
                                        @mouseenter="hoveredGenre = genre.id"
                                        @mouseleave="hoveredGenre = null">
                                        <div 
                                            @click="removeGenre(genre.id)" 
                                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white">
                                            <component :is="hoveredGenre === genre.id ? RiCloseCircleFill : RiCloseCircleLine" />
                                        </div>
                                        <span class="mt-auto">{{ genre.name }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';

const event = inject('event');
const errors = inject('errors');

const searchTerm = ref('');
const dropdown = ref(false);
const genresList = ref([]);
const filteredGenres = ref([]);
const hoveredGenre = ref(null);
const genresDrop = ref(null);
const searchInput = ref(null);

const isExistingGenre = computed(() => {
    return filteredGenres.value.some(genre => 
        genre.name.toLowerCase() === searchTerm.value.toLowerCase()
    );
});

const fetchGenres = async () => {
    try {
        const response = await axios.get(`/api/genres`);
        genresList.value = response.data;

        // Filter out already selected genres
        if (event.genres && event.genres.length > 0) {
            const selectedIds = event.genres.map(genre => genre.id);
            genresList.value = genresList.value.filter(genre => !selectedIds.includes(genre.id));
        }

        filteredGenres.value = genresList.value;
    } catch (error) {
        console.error('Failed to fetch genres:', error);
    }
};

const filterGenres = () => {
    const searchTermLower = searchTerm.value.toLowerCase();
    filteredGenres.value = genresList.value.filter(item => 
        item.name.toLowerCase().includes(searchTermLower)
    );
};

const createNewGenre = async () => {
    if (!searchTerm.value || isExistingGenre.value) return;
    
    try {
        const response = await axios.post('/api/genres', { name: searchTerm.value });
        const newGenre = response.data;
        selectGenre(newGenre);
    } catch (error) {
        console.error('Failed to create genre:', error);
    }
};

const onDropdown = () => {
    dropdown.value = true;
};

const closeDropdown = () => {
    dropdown.value = false;
};

const handleClickOutside = (event) => {
    const dropdownElement = genresDrop.value;
    if (dropdownElement && !dropdownElement.contains(event.target)) {
        closeDropdown();
        if (searchTerm.value) {
            searchTerm.value = '';
            filteredGenres.value = genresList.value;
        }
    }
};

const selectGenre = (item) => {
    if (!event.genres) {
        event.genres = [];
    }

    if (event.genres.length >= 10) {
        errors.value = { genres: ['Maximum of 10 genres allowed'] };
        return;
    }

    const alreadySelected = event.genres.find(genre => genre.id === item.id);

    if (!alreadySelected) {
        event.genres.push(item);
        genresList.value = genresList.value.filter(genre => genre.id !== item.id);
        filterGenres();
    }

    searchTerm.value = '';
    dropdown.value = false;
    searchInput.value.blur();
};

const removeGenre = (id) => {
    const removedGenre = event.genres.find(genre => genre.id === id);
    event.genres = event.genres.filter(genre => genre.id !== id);
    if (removedGenre) {
        genresList.value.push(removedGenre);
        filterGenres();
    }
};

defineExpose({
    isValid: async () => {
        const isValid = event.genres && event.genres.length > 0;
        if (!isValid) {
            errors.value = { genres: ['At least one genre is required'] };
        }
        return isValid;
    },
    submitData: () => {
        // Format the genres array properly for submission
        const data = {
            genres: event.genres.map(genre => ({
                id: genre.id,
                name: genre.name
            }))
        };
        console.log('Submitting genres data:', data);
        return data;
    }
});

onMounted(fetchGenres);
</script>
