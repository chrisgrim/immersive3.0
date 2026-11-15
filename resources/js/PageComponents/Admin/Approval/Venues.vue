<template>
    <!-- Approval Queue > Venues, in the Organizers screen's style: a grid of round
         pictures with names. Venues teams added, waiting for a look; claims from
         the real venue are under Requests. -->
    <div class="p-6 bg-white">
        <h1 class="text-2xl font-bold mb-6">Venues Ready for Review</h1>

        <div v-if="loading" class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
        </div>

        <div v-else-if="pending.length === 0" class="text-center text-gray-500">
            No venues ready for review
        </div>

        <div v-else class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
            <div
                v-for="venue in pending"
                :key="venue.id"
                @click="!opening && select(venue)"
                :class="[
                    'bg-white transition-opacity duration-200 cursor-pointer flex flex-col items-center',
                    opening ? 'opacity-50 cursor-wait' : 'hover:opacity-75'
                ]"
            >
                <div class="aspect-square w-1/2 mb-4">
                    <img v-if="venue.thumbImagePath" :src="`${imageUrl}${venue.thumbImagePath}`" :alt="venue.name" class="w-full h-full object-cover rounded-full" />
                    <div v-else class="w-full h-full rounded-full bg-gray-200 flex items-center justify-center">
                        <svg class="w-1/3 h-1/3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5" stroke-width="2"/></svg>
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="mt-2 mb-4 text-2xl text-black font-medium leading-tight line-clamp-2 break-words hyphens-auto">{{ venue.name }}</h3>
                </div>
            </div>
        </div>

        <!-- More than one page waiting: step through them, so no venue is out of reach. -->
        <div v-if="lastPage > 1" class="flex items-center justify-center gap-6 mt-10">
            <button type="button" class="px-6 py-3 rounded-lg border border-gray-300 disabled:opacity-40" :disabled="page <= 1 || loading" @click="load(page - 1)">Previous</button>
            <span class="text-xl text-gray-600">Page {{ page }} of {{ lastPage }}</span>
            <button type="button" class="px-6 py-3 rounded-lg border border-gray-300 disabled:opacity-40" :disabled="page >= lastPage || loading" @click="load(page + 1)">Next</button>
        </div>

        <div v-if="toast" class="fixed bottom-4 right-4 bg-gray-800 text-white px-6 py-3 rounded-lg shadow-lg">{{ toast }}</div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const emit = defineEmits(['select-venue', 'update-counts']);

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const pending = ref([]);
const loading = ref(true);
const opening = ref(false);
const toast = ref('');

const say = (text) => {
    toast.value = text;
    setTimeout(() => { toast.value = ''; }, 3000);
};

const page = ref(1);
const lastPage = ref(1);

const load = async (to = 1) => {
    loading.value = true;
    try {
        const response = await axios.get('/api/admin/venues', { params: { status: 'pending', page: to } });
        pending.value = response.data.data ?? [];
        page.value = response.data.current_page ?? to;
        lastPage.value = response.data.last_page ?? 1;
    } catch {
        say('Failed to load venues');
    } finally {
        loading.value = false;
    }
};

// As on Organizers: clicking opens the review, where Approve and Ask a question live.
const select = async (venue) => {
    opening.value = true;
    try {
        const response = await axios.get(`/api/admin/venues/${venue.slug}`);
        if (response.data?.id) {
            emit('select-venue', response.data);
        }
    } catch {
        say('Could not open that venue');
    } finally {
        opening.value = false;
    }
};

onMounted(() => load(1));
</script>
