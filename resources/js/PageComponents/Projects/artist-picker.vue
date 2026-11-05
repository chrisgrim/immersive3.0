<template>
    <div>
        <input
            v-model="keywords"
            type="text"
            placeholder="Find a person"
            class="w-full text-xl border border-neutral-400 rounded-2xl p-4"
            @input="search"
        />
        <div v-if="keywords.trim()" class="flex flex-wrap gap-2 mt-2">
            <button
                v-for="artist in results"
                :key="artist.id"
                type="button"
                class="px-4 py-2 border border-neutral-300 rounded-2xl text-xl"
                @click="pick(artist)"
            >
                {{ artist.name }}
            </button>
            <button
                type="button"
                class="px-4 py-2 border border-dashed border-neutral-400 rounded-2xl text-xl"
                @click="propose"
            >
                Add "{{ keywords.trim() }}"
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const emit = defineEmits(['picked']);

const keywords = ref('');
const results = ref([]);

const search = async () => {
    if (!keywords.value.trim()) {
        results.value = [];
        return;
    }
    const response = await axios.get('/api/artists/search', { params: { keywords: keywords.value } });
    results.value = response.data;
};

const pick = (artist) => {
    emit('picked', artist);
    keywords.value = '';
    results.value = [];
};

const propose = async () => {
    const response = await axios.post('/api/artists', { name: keywords.value.trim() });
    pick(response.data.artist);
};
</script>
