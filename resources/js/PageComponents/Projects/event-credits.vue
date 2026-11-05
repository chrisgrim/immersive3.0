<template>
    <div class="max-w-screen-md mx-auto px-8 py-16 space-y-12">
        <h1 class="text-4xl font-bold">Credits: {{ page.event.name }}</h1>

        <p v-if="error" class="text-red-600">{{ error }}</p>

        <section v-if="page.show_writers.length" class="space-y-2">
            <h2 class="text-2xl font-semibold">The show's writers</h2>
            <p v-for="writer in page.show_writers" :key="writer.id" class="text-xl">
                {{ writer.artist }} <span class="text-gray-600">{{ writer.role }}</span>
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-2xl font-semibold">This run</h2>
            <div v-for="credit in page.credits" :key="credit.id" class="flex items-center justify-between gap-4">
                <p class="text-xl min-w-0">
                    {{ credit.artist }}
                    <span class="text-gray-600">{{ credit.department }}, {{ credit.role }}</span>
                </p>
                <button
                    v-if="page.editable_departments.includes(credit.department)"
                    type="button"
                    class="px-4 py-3 border border-neutral-400 rounded-2xl flex-shrink-0"
                    :disabled="busy"
                    @click="remove(credit)"
                >
                    Remove
                </button>
            </div>
        </section>

        <section v-if="page.editable_departments.length" class="space-y-4">
            <h2 class="text-2xl font-semibold">Add someone</h2>
            <p v-if="draft.artist" class="text-xl">{{ draft.artist.name }}</p>
            <artist-picker v-else @picked="draft.artist = $event" />
            <select v-model="draft.department" class="w-full text-xl border border-neutral-400 rounded-2xl p-4">
                <option v-for="department in page.editable_departments" :key="department" :value="department">{{ department }}</option>
            </select>
            <input v-model="draft.role" type="text" placeholder="Role" class="w-full text-xl border border-neutral-400 rounded-2xl p-4" />
            <input v-model="draft.character" type="text" placeholder="Character (optional)" class="w-full text-xl border border-neutral-400 rounded-2xl p-4" />
            <select v-model="draft.kind" class="w-full text-xl border border-neutral-400 rounded-2xl p-4">
                <option v-for="kind in page.kinds" :key="kind" :value="kind">{{ kind }}</option>
            </select>
            <button type="button" class="px-6 py-3 bg-black text-white rounded-2xl disabled:opacity-50" :disabled="busy || !draft.artist" @click="add">
                Add
            </button>
        </section>

        <section v-if="page.can_copy && page.other_runs.length" class="space-y-4">
            <h2 class="text-2xl font-semibold">Copy from another run</h2>
            <p class="text-gray-600">Adds their people to this list. Nobody here is removed.</p>
            <select v-model="copyFrom" class="w-full text-xl border border-neutral-400 rounded-2xl p-4">
                <option v-for="run in page.other_runs" :key="run.id" :value="run.id">{{ run.name }}</option>
            </select>
            <button type="button" class="px-6 py-3 border border-neutral-400 rounded-2xl disabled:opacity-50" :disabled="busy || !copyFrom" @click="copy">
                Copy
            </button>
        </section>

        <section v-if="page.can_edit_thanks" class="space-y-4">
            <h2 class="text-2xl font-semibold">Special Thanks</h2>
            <p class="text-gray-600">One name per line.</p>
            <textarea v-model="thanks" rows="6" class="w-full text-xl border border-neutral-400 rounded-2xl p-4"></textarea>
            <button type="button" class="px-6 py-3 border border-neutral-400 rounded-2xl disabled:opacity-50" :disabled="busy" @click="saveThanks">
                Save
            </button>
        </section>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import ArtistPicker from './artist-picker.vue';

const props = defineProps({
    panel: { type: Object, required: true },
});

const page = ref(props.panel);
const busy = ref(false);
const error = ref('');
const thanks = ref(props.panel.special_thanks ?? '');
const copyFrom = ref(null);

const emptyDraft = () => ({
    artist: null,
    department: page.value.editable_departments[0] ?? null,
    role: '',
    character: '',
    kind: 'regular',
});
const draft = reactive(emptyDraft());

const base = () => `/hosting/event/${page.value.event.slug}/credits`;

const run = async (call) => {
    busy.value = true;
    error.value = '';
    try {
        const response = await call();
        page.value = response.data;
        return true;
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'That did not work. Please try again.';
        return false;
    } finally {
        busy.value = false;
    }
};

const add = async () => {
    const saved = await run(() => axios.post(base(), {
        artist_id: draft.artist.id,
        department: draft.department,
        role: draft.role,
        character: draft.character || null,
        kind: draft.kind,
    }));
    if (saved) {
        Object.assign(draft, emptyDraft());
    }
};

const remove = (credit) => run(() => axios.delete(`${base()}/${credit.id}`));
const copy = () => run(() => axios.post(`${base()}/copy`, { from_event_id: copyFrom.value }));
const saveThanks = () => run(() => axios.post(base(), { special_thanks: thanks.value }));
</script>
