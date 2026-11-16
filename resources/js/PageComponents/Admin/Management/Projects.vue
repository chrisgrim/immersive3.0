<template>
    <div class="flex flex-col">
        <h1 class="text-2xl font-bold mb-6">Show runs</h1>
        <input v-model="keywords" name="project_search" autocomplete="off" placeholder="Search by name..." class="w-auto max-w-[25rem] mb-6 px-4 py-2 border border-gray-300 rounded-lg" @input="load(1)">

        <p v-if="message" class="mb-4 text-red-600">{{ message }}</p>

        <div class="overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="bg-neutral-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500">Show run</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500">Made by</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500">Keeps the page</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500">Runs</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="show in shows" :key="show.id" class="border-t border-neutral-200">
                        <td class="px-6 py-3"><a :href="show.public_url" target="_blank" class="underline">{{ show.name }}</a></td>
                        <td class="px-6 py-3">{{ show.organizer?.name }}</td>
                        <td class="px-6 py-3">{{ show.editor?.name ?? show.organizer?.name }}</td>
                        <td class="px-6 py-3">{{ show.runs_count }}</td>
                        <td class="px-6 py-3 text-right"><button class="px-4 py-2 border border-gray-300 rounded-lg" @click="open(show)">Edit</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editing" class="mt-8 p-6 border border-neutral-200 rounded-xl space-y-4" data-project-editor>
            <h2 class="text-xl font-bold">{{ editing.name }}</h2>

            <label class="block">Name
                <input v-model="form.name" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg">
            </label>
            <label class="block">Address
                <input v-model="form.slug" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg">
            </label>
            <div>
                Made by: {{ form.maker?.name }}
                <input v-model="makerSearch" placeholder="Find an organizer" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg" @input="findOrganizers('maker')">
                <div class="flex flex-wrap gap-2 mt-2">
                    <button v-for="organizer in makerResults" :key="organizer.id" class="px-3 py-1 border border-gray-300 rounded-lg" @click="form.maker = organizer; makerResults = []; makerSearch = ''">{{ organizer.name }}</button>
                </div>
            </div>
            <div>
                Keeps the page: {{ form.keeper?.name ?? 'the maker' }}
                <button v-if="form.keeper" class="ml-2 underline" @click="form.keeper = null">Give back to the maker</button>
                <input v-model="keeperSearch" placeholder="Find an organizer" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg" @input="findOrganizers('keeper')">
                <div class="flex flex-wrap gap-2 mt-2">
                    <button v-for="organizer in keeperResults" :key="organizer.id" class="px-3 py-1 border border-gray-300 rounded-lg" @click="form.keeper = organizer; keeperResults = []; keeperSearch = ''">{{ organizer.name }}</button>
                </div>
            </div>
            <div class="flex gap-4">
                <button class="px-4 py-2 bg-blue-500 text-white rounded-lg" :disabled="busy" @click="save">Save</button>
                <a :href="`/hosting/show-runs/${editing.slug}/edit`" target="_blank" class="px-4 py-2 border border-gray-300 rounded-lg">Page, writers and runs</a>
                <button class="px-4 py-2 border border-gray-300 rounded-lg" :disabled="busy" @click="dissolve">Dissolve</button>
                <button class="px-4 py-2" @click="editing = null">Close</button>
            </div>

            <div>
                Merge this show run into
                <input v-model="mergeSearch" placeholder="Find the show run to keep" class="block w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg" @input="findShows">
                <div class="flex flex-wrap gap-2 mt-2">
                    <button v-for="show in mergeResults" :key="show.id" class="px-3 py-1 border border-gray-300 rounded-lg" :disabled="busy" @click="merge(show)">{{ show.name }}</button>
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

const shows = ref([]);
const keywords = ref('');
const page = ref(1);
const lastPage = ref(1);
const editing = ref(null);
const busy = ref(false);
const message = ref('');
const form = reactive({ name: '', slug: '', maker: null, keeper: null });
const makerSearch = ref('');
const makerResults = ref([]);
const keeperSearch = ref('');
const keeperResults = ref([]);
const mergeSearch = ref('');
const mergeResults = ref([]);

const reason = (failure) => Object.values(failure.response?.data?.errors ?? {}).flat()[0]
    ?? failure.response?.data?.message
    ?? 'That did not work.';

const load = async (to = 1) => {
    const response = await axios.get('/api/admin/projects', { params: { page: to, keywords: keywords.value } });
    shows.value = response.data.data;
    page.value = response.data.current_page;
    lastPage.value = response.data.last_page;
};

const open = (show) => {
    editing.value = show;
    message.value = '';
    Object.assign(form, { name: show.name, slug: show.slug, maker: show.organizer, keeper: show.editor });
};

const findOrganizers = async (which) => {
    const term = which === 'maker' ? makerSearch.value : keeperSearch.value;
    const results = term.trim() ? (await axios.get('/api/admin/manage/organizers', { params: { search: term } })).data.data : [];
    (which === 'maker' ? makerResults : keeperResults).value = results;
};

const findShows = async () => {
    const term = mergeSearch.value.trim();
    const results = term ? (await axios.get('/api/admin/projects', { params: { keywords: term } })).data.data : [];
    mergeResults.value = results.filter((show) => show.id !== editing.value.id);
};

const act = async (call) => {
    busy.value = true;
    message.value = '';
    try {
        await call();
        editing.value = null;
        await load(page.value);
    } catch (failure) {
        message.value = reason(failure);
    } finally {
        busy.value = false;
    }
};

const save = () => act(() => axios.patch(`/api/admin/projects/${editing.value.slug}`, {
    name: form.name,
    slug: form.slug,
    organizer_id: form.maker?.id,
    editor_organizer_id: form.keeper?.id ?? null,
}));

const dissolve = () => {
    if (confirm(`Dissolve ${editing.value.name}? Its one run takes the address.`)) {
        act(() => axios.post(`/api/admin/projects/${editing.value.slug}/dissolve`));
    }
};

const merge = (show) => {
    if (confirm(`Merge ${editing.value.name} into ${show.name}? Its runs and addresses move across.`)) {
        act(() => axios.post(`/api/admin/projects/${editing.value.slug}/merge`, { into_project_id: show.id }));
    }
};

onMounted(load);
</script>
