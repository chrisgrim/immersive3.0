<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>What genres best describe your event?</h2>
                <p class="text-gray-500 font-normal mt-4">Tags will help users with the more finite categorization of your event.</p>
                
                <div class="w-full mt-14">
                    <Dropdown 
                        class="mt-4"
                        :list="genresList"
                        :creatable="true"
                        placeholder="Select or create genres"
                        @onSelect="itemSelected"
                        :error="showError"
                        :max-selections="10"
                        :max-input-length="50"
                    />
                    <p v-if="showError" 
                       class="text-red-500 text-1xl mt-2 px-4">
                        Please select at least one genre
                    </p>
                    <p v-if="showMaxError" 
                       class="text-red-500 text-1xl mt-2 px-4">
                        Maximum of 10 genres allowed
                    </p>
                    <List 
                        class="mt-6"
                        :item-height="'h-24'"
                        :selections="event.genres" 
                        @onSelect="itemRemoved"
                    />
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { required, minLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

const event = inject('event');
const errors = inject('errors');

const genresList = ref([]);

// Validation Rules
const rules = {
    genres: { 
        required,
        minLength: minLength(1)
    }
};

const $v = useVuelidate(rules, {
    genres: computed(() => event.genres)
});

const showError = computed(() => {
    return $v.value.$dirty && $v.value.$error;
});

const showMaxError = computed(() => {
    return event.genres && event.genres.length >= 10;
});

// Event Handlers
const itemSelected = async (item) => {
    if (!event.genres) event.genres = [];

    if (event.genres.length >= 10) {
        errors.value = { genres: ['Maximum of 10 genres allowed'] };
        return;
    }

    // If it's a new genre, create it first
    if (!item.id) {
        try {
            const response = await axios.post('/api/genres', { 
                name: item.name 
            });
            // Make sure we get a valid genre object back with an ID
            if (!response.data.id) {
                throw new Error('Invalid genre response');
            }
            item = response.data;
        } catch (error) {
            errors.value = { genres: ['Failed to create genre'] };
            return;
        }
    }

    // Only add the genre if it has a valid ID
    if (item && item.id) {
        const genre = {
            id: parseInt(item.id), // Ensure ID is a number
            name: item.name
        };
        
        // Check for duplicates
        if (!event.genres.some(g => g.id === genre.id)) {
            event.genres.push(genre);
            genresList.value = genresList.value.filter(g => g.id !== genre.id);
        }
    }
};

const itemRemoved = (item) => {
    event.genres = event.genres.filter(genre => genre.id !== item.id);
    genresList.value.push(item);
    genresList.value.sort((a, b) => a.name.localeCompare(b.name));
    
    if (event.genres.length === 0) {
        $v.value.$touch();
    }
};

// API Methods
const fetchGenres = async () => {
    try {
        const response = await axios.get('/api/genres');
        genresList.value = response.data.sort((a, b) => 
            a.name.localeCompare(b.name)
        );

        if (event.genres?.length > 0) {
            const selectedIds = event.genres.map(genre => genre.id);
            genresList.value = genresList.value.filter(genre => 
                !selectedIds.includes(genre.id)
            );
        }
    } catch (error) {
        errors.value = { genres: ['Failed to load genres'] };
    }
};

// Component API
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
