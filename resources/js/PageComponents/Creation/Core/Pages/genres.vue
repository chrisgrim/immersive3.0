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
                                :class="{'rotate-90': state.dropdown}"
                                class="w-10 h-10 fill-black absolute z-10 right-4 top-8">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                            </svg>

                            <!-- Search Input -->
                            <input 
                                ref="searchInput"
                                :class="{ 'border-red-500': showError }"
                                class="text-2xl relative p-8 w-full border mb-12 rounded-3xl focus:rounded-t-3xl focus:rounded-b-none h-24"
                                v-model="state.searchTerm"
                                placeholder="Select or create genres"
                                @input="filterGenres"
                                @focus="onDropdown"
                                @keydown.enter="createNewGenre"
                                autocomplete="off"
                                type="text">

                            <!-- Error Message -->
                            <p v-if="showError" class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                                Please select at least one genre
                            </p>

                            <!-- Dropdown List -->
                            <ul 
                                class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border-[#e5e7eb] border max-h-[40rem]" 
                                v-if="state.dropdown">
                                <li v-if="state.searchTerm && !isExistingGenre"
                                    class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300 text-blue-600" 
                                    @click="createNewGenre"
                                    @mousedown.stop.prevent>
                                    Create "{{ state.searchTerm }}"
                                </li>
                                <li 
                                    v-for="genre in state.filteredGenres"
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
                                        @mouseenter="state.hoveredGenre = genre.id"
                                        @mouseleave="state.hoveredGenre = null">
                                        <div 
                                            @click="removeGenre(genre.id)" 
                                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white">
                                            <component :is="state.hoveredGenre === genre.id ? RiCloseCircleFill : RiCloseCircleLine" />
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
import useVuelidate from '@vuelidate/core';
import { required, minLength } from '@vuelidate/validators';

const event = inject('event');
const errors = inject('errors');

const state = ref({
    searchTerm: '',
    dropdown: false,
    genresList: [],
    filteredGenres: [],
    hoveredGenre: null
});

const genresDrop = ref(null);
const searchInput = ref(null);

const isExistingGenre = computed(() => {
    return state.value.filteredGenres.some(genre => 
        genre.name.toLowerCase() === state.value.searchTerm.toLowerCase()
    );
});

const showError = computed(() => {
    return $v.value.$dirty && $v.value.$error;
});

const rules = {
    genres: { 
        required,
        minLength: minLength(1)
    }
};

const $v = useVuelidate(rules, {
    genres: computed(() => event.genres)
});

const fetchGenres = async () => {
    try {
        const response = await axios.get(`/api/genres`);
        state.value.genresList = response.data;

        if (event.genres?.length > 0) {
            const selectedIds = event.genres.map(genre => genre.id);
            state.value.genresList = state.value.genresList.filter(genre => 
                !selectedIds.includes(genre.id)
            );
        }

        state.value.filteredGenres = state.value.genresList;
    } catch (error) {
        errors.value = { genres: ['Failed to load genres'] };
    }
};

const filterGenres = () => {
    const searchTermLower = state.value.searchTerm.toLowerCase();
    state.value.filteredGenres = state.value.genresList.filter(item => 
        item.name.toLowerCase().includes(searchTermLower)
    );
};

const createNewGenre = async () => {
    if (!state.value.searchTerm || isExistingGenre.value) return;
    
    try {
        const response = await axios.post('/api/genres', { 
            name: state.value.searchTerm 
        });
        selectGenre(response.data);
    } catch (error) {
        errors.value = { genres: ['Failed to create genre'] };
    }
};

const selectGenre = (item) => {
    if (!event.genres) event.genres = [];

    if (event.genres.length >= 10) {
        errors.value = { genres: ['Maximum of 10 genres allowed'] };
        return;
    }

    if (!event.genres.find(genre => genre.id === item.id)) {
        event.genres.push(item);
        state.value.genresList = state.value.genresList.filter(genre => 
            genre.id !== item.id
        );
        filterGenres();
        $v.value.$reset();
    }

    state.value.searchTerm = '';
    state.value.dropdown = false;
    searchInput.value?.blur();
};

const removeGenre = (id) => {
    const removedGenre = event.genres.find(genre => genre.id === id);
    event.genres = event.genres.filter(genre => genre.id !== id);
    
    if (removedGenre) {
        state.value.genresList.push(removedGenre);
        filterGenres();
    }
    
    if (event.genres.length === 0) {
        $v.value.$touch();
    }
};

const handleClickOutside = (event) => {
    const dropdownElement = genresDrop.value;
    if (dropdownElement && !dropdownElement.contains(event.target)) {
        state.value.dropdown = false;
        if (state.value.searchTerm) {
            state.value.searchTerm = '';
            state.value.filteredGenres = state.value.genresList;
        }
    }
};

const onDropdown = () => {
    state.value.dropdown = true;
};

defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { genres: ['At least one genre is required'] };
        }

        return isValid;
    },
    submitData: () => ({
        genres: event.genres.map(genre => ({
            id: genre.id,
            name: genre.name
        }))
    })
});

onMounted(fetchGenres);
</script>
