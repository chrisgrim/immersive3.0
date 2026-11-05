<template>
    <div class="max-w-screen-md mx-auto px-8 py-16 space-y-12">
        <div>
            <h1 class="text-4xl font-bold">{{ page.name }}</h1>
            <p class="text-gray-600">Made by {{ page.organizer?.name }}</p>
        </div>

        <p v-if="error" class="text-red-600">{{ error }}</p>

        <section v-if="page.link_requests.length" class="space-y-4">
            <h2 class="text-2xl font-semibold">Asking to join</h2>
            <div
                v-for="request in page.link_requests"
                :key="request.id"
                class="p-6 border border-neutral-300 rounded-2xl flex items-center justify-between gap-4"
            >
                <div class="min-w-0">
                    <p class="text-xl font-medium">{{ request.event?.name }}</p>
                    <p class="text-gray-600">{{ request.event?.organizer?.name }}</p>
                    <p v-if="request.event?.status === 'r'" class="text-gray-600">Waiting for EI to approve the listing first.</p>
                </div>
                <div class="flex gap-2 flex-shrink-0">
                    <button
                        type="button"
                        class="px-6 py-3 bg-black text-white rounded-2xl disabled:opacity-50"
                        :disabled="busy || !['p', 'e'].includes(request.event?.status)"
                        @click="decide(request, 'approve')"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="px-6 py-3 border border-neutral-400 rounded-2xl disabled:opacity-50"
                        :disabled="busy"
                        @click="decide(request, 'reject')"
                    >
                        Decline
                    </button>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">About the show</h2>
            <input v-model="form.name" type="text" class="w-full text-xl border border-neutral-400 rounded-2xl p-4" />
            <textarea v-model="form.description" rows="6" class="w-full text-xl border border-neutral-400 rounded-2xl p-4"></textarea>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Writers</h2>
            <div v-for="(writer, index) in form.writers" :key="writer.id ?? `new-${index}`" class="flex items-center gap-2">
                <span class="text-xl flex-1 min-w-0 truncate">{{ writer.artist }}</span>
                <input v-model="writer.role" type="text" placeholder="Role" class="text-xl border border-neutral-400 rounded-2xl p-3" />
                <button type="button" class="px-4 py-3 border border-neutral-400 rounded-2xl" @click="form.writers.splice(index, 1)">
                    Remove
                </button>
            </div>
            <artist-picker @picked="addWriter" />
        </section>

        <button type="button" class="px-6 py-3 bg-black text-white rounded-2xl disabled:opacity-50" :disabled="busy" @click="save">
            Save
        </button>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Runs</h2>
            <div v-for="run in page.runs" :key="run.id" class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <a :href="run.public_url" class="text-xl underline">{{ run.name }}</a>
                    <span class="text-gray-600 ml-3">{{ run.organizer?.name }}</span>
                </div>
                <button
                    type="button"
                    class="px-4 py-3 border border-neutral-400 rounded-2xl flex-shrink-0 disabled:opacity-50"
                    :disabled="busy"
                    @click="unlink(run)"
                >
                    Take off this show
                </button>
            </div>
        </section>

        <section v-if="page.aggregate.length" class="space-y-2">
            <h2 class="text-2xl font-semibold">Everyone across its runs</h2>
            <p v-for="person in page.aggregate" :key="person.artist_id" class="text-xl">
                {{ person.artist }}
                <span class="text-gray-600">
                    {{ person.runs }} {{ person.runs === 1 ? 'run' : 'runs' }},
                    {{ person.first_year === person.last_year ? person.first_year : `${person.first_year} to ${person.last_year}` }}
                </span>
            </p>
        </section>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import ArtistPicker from './artist-picker.vue';

const props = defineProps({
    project: { type: Object, required: true },
});

const page = ref(props.project);
const busy = ref(false);
const error = ref('');

const formFrom = (project) => ({
    name: project.name,
    description: project.description ?? '',
    writers: project.writers.map((writer) => ({ ...writer })),
});
const form = reactive(formFrom(props.project));

const addWriter = (artist) => {
    form.writers.push({ artist_id: artist.id, artist: artist.name, role: 'Writer' });
};

const run = async (call) => {
    busy.value = true;
    error.value = '';
    try {
        const response = await call();
        page.value = response.data;
        Object.assign(form, formFrom(response.data));
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'That did not work. Please try again.';
    } finally {
        busy.value = false;
    }
};

const base = () => `/hosting/projects/${page.value.slug}`;

const save = () => run(() => axios.post(base(), form));
const decide = (request, verdict) => run(() => axios.post(`${base()}/link-requests/${request.id}/${verdict}`));
const unlink = (listing) => {
    if (confirm(`Take "${listing.name}" off this show? It becomes a plain listing at its own address.`)) {
        run(() => axios.delete(`${base()}/instances/${listing.slug}`));
    }
};
</script>
