<template>
    <div class="flex flex-col">
        <h1 class="text-2xl font-bold mb-6">Venues</h1>
        <div class="flex gap-4 mb-6">
            <input v-model="keywords" name="venue_search" autocomplete="off" placeholder="Search by name..." class="min-w-[25rem] px-4 py-2 border border-gray-300 rounded-lg" @input="load(1)">
            <select v-model="status" class="px-4 py-2 border border-gray-300 rounded-lg" @change="load(1)">
                <option value="">All</option>
                <option value="pending">Proposed</option>
                <option value="approved">Approved</option>
            </select>
        </div>

        <p v-if="message" class="mb-4 text-red-600">{{ message }}</p>

        <div class="overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="bg-neutral-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500">Venue</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500">City</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="venue in venues" :key="venue.id" class="border-t border-neutral-200">
                        <td class="px-6 py-3">{{ venue.name }}<span v-if="venue.room" class="text-gray-500">, {{ venue.room }}</span></td>
                        <td class="px-6 py-3">{{ venue.city }}</td>
                        <td class="px-6 py-3">{{ venue.status }}</td>
                        <td class="px-6 py-3 text-right space-x-2">
                            <button v-if="venue.status !== 'approved'" class="px-4 py-2 bg-blue-500 text-white rounded-lg" @click="approve(venue)">Approve</button>
                            <button class="px-4 py-2 border border-gray-300 rounded-lg" @click="open(venue)">Edit</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editing" class="mt-8 p-6 border border-neutral-200 rounded-xl space-y-4" data-venue-editor>
            <h2 class="text-xl font-bold">{{ editing.name }}</h2>
            <label class="block">Name <input v-model="form.name" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg"></label>
            <label class="block">Room <input v-model="form.room" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg"></label>
            <label class="block">About <textarea v-model="form.description" rows="3" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg"></textarea></label>
            <label class="block">How to get there <textarea v-model="form.directions" rows="4" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg"></textarea></label>
            <label class="block">Picture
                <span class="block text-gray-500">{{ editing.largeImagePath ? 'Replaces the current picture.' : 'No picture yet.' }}</span>
                <input type="file" accept="image/*" class="block mt-1" data-venue-image @change="upload">
            </label>
            <div class="flex gap-4">
                <button class="px-4 py-2 bg-blue-500 text-white rounded-lg" @click="save">Save</button>
                <button class="px-4 py-2" @click="editing = null">Close</button>
            </div>
            <div>
                Merge this venue into
                <input v-model="mergeSearch" placeholder="Find the venue to keep" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg" @input="findVenues">
                <div class="flex flex-wrap gap-2 mt-2">
                    <button v-for="venue in mergeResults" :key="venue.id" class="px-3 py-1 border border-gray-300 rounded-lg" @click="merge(venue)">{{ venue.name }}, {{ venue.city }}</button>
                </div>
            </div>
        </div>

        <div v-if="lastPage > 1" class="flex gap-4 mt-6">
            <button :disabled="page <= 1" @click="load(page - 1)">Previous</button>
            <span>{{ page }} / {{ lastPage }}</span>
            <button :disabled="page >= lastPage" @click="load(page + 1)">Next</button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const venues = ref([]);
const keywords = ref('');
const status = ref('');
const page = ref(1);
const lastPage = ref(1);
const editing = ref(null);
const message = ref('');
const form = reactive({ name: '', room: '', description: '', directions: '' });
const mergeSearch = ref('');
const mergeResults = ref([]);

const load = async (to = 1) => {
    const response = await axios.get('/api/admin/venues', { params: { page: to, keywords: keywords.value, status: status.value } });
    venues.value = response.data.data;
    page.value = response.data.current_page;
    lastPage.value = response.data.last_page;
};

const act = async (call) => {
    message.value = '';
    try {
        await call();
        editing.value = null;
        await load(page.value);
    } catch (failure) {
        message.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0] ?? failure.response?.data?.message ?? 'That did not work.';
    }
};

const open = (venue) => {
    editing.value = venue;
    mergeResults.value = [];
    mergeSearch.value = '';
    Object.assign(form, { name: venue.name, room: venue.room ?? '', description: venue.description ?? '', directions: venue.directions ?? '' });
};

const upload = (change) => {
    const file = change.target.files[0];
    if (!file) return;

    const data = new FormData();
    data.append('image', file);
    act(() => axios.post(`/api/admin/venues/${editing.value.slug}/image`, data));
};

const approve = (venue) => act(() => axios.post(`/api/admin/venues/${venue.slug}/approve`));
const save = () => act(() => axios.patch(`/api/admin/venues/${editing.value.slug}`, form));

const findVenues = async () => {
    const term = mergeSearch.value.trim();
    const results = term ? (await axios.get('/api/admin/venues', { params: { keywords: term } })).data.data : [];
    mergeResults.value = results.filter((venue) => venue.id !== editing.value.id);
};

const merge = (venue) => {
    if (confirm(`Merge ${editing.value.name} into ${venue.name}? Its listings and address move across.`)) {
        act(() => axios.post(`/api/admin/venues/${editing.value.slug}/merge`, { into_venue_id: venue.id }));
    }
};

onMounted(load);
</script>
