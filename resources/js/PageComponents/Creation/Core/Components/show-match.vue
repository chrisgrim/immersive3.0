<template>
    <div class="mt-4 mb-6 space-y-6">
        <!-- Already asking to join -->
        <div v-if="event.link_request" class="p-5 rounded-2xl border border-neutral-300" data-joining>
            <div class="flex items-center justify-between gap-4">
                <p class="text-2.5xl">This will join <span class="font-semibold">{{ event.link_request.show }}</span></p>
                <button type="button" class="px-5 py-2.5 rounded-xl border border-gray-300 text-2xl hover:bg-gray-50" :disabled="busy" @click="remove">
                    Remove
                </button>
            </div>
        </div>

        <!-- The match question -->
        <div v-else-if="asking && matches.length" class="p-5 rounded-2xl border border-neutral-300" data-match-question>
            <p class="text-2.5xl font-semibold">Is this a new run of one of these? A new city, new dates, or a remount.</p>
            <div class="mt-4 space-y-3">
                <button
                    v-for="match in matches"
                    :key="`${match.type}-${match.id}`"
                    type="button"
                    class="w-full text-left px-5 py-3 rounded-xl border border-gray-300 text-2xl hover:border-black"
                    :disabled="busy"
                    @click="join(match)"
                >
                    {{ match.name }}
                    <span v-if="match.organizer" class="block text-xl text-gray-500">{{ match.organizer.name }}</span>
                </button>
            </div>
            <button type="button" class="mt-4 text-2xl underline" @click="dismissed = true">No, this is a new show</button>
            <p v-if="error" class="text-red-500 text-1xl mt-2">{{ error }}</p>
        </div>

        <!-- A run's own details, inside this step -->
        <div v-if="showRunFields" class="space-y-4" data-run-fields>
            <div>
                <p class="text-neutral-500 font-normal">This run's own name (optional)</p>
                <input v-model="event.edition" type="text" maxlength="80" placeholder="Holiday Edition" class="text-2.5xl border border-neutral-300 rounded-2xl p-4 w-full mt-2">
            </div>
            <div>
                <p class="text-neutral-500 font-normal">Run type (optional)</p>
                <select v-model="event.run_type" class="text-2.5xl border border-neutral-300 rounded-2xl p-4 w-full mt-2">
                    <option :value="null">None</option>
                    <option value="workshop">Workshop</option>
                    <option value="world_premiere">World premiere</option>
                    <option value="us_premiere">US premiere</option>
                    <option value="regional_premiere">Regional premiere</option>
                </select>
            </div>
            <div>
                <p class="text-neutral-500 font-normal">Festival (optional)</p>
                <input v-model="event.festival" type="text" maxlength="120" placeholder="SXSW" class="text-2.5xl border border-neutral-300 rounded-2xl p-4 w-full mt-2">
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, inject, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';

const event = inject('event');

const matches = ref([]);
const dismissed = ref(false);
const busy = ref(false);
const error = ref('');
let timer = null;

const isDraft = computed(() => !['r', 'p', 'e'].includes(event.status) && !event.project_id);
const asking = computed(() => isDraft.value && !dismissed.value);
const showRunFields = computed(() => Boolean(event.link_request || event.project_id));

const search = async () => {
    const keywords = event.name?.trim();
    if (!keywords || keywords.length < 3 || event.link_request) {
        matches.value = [];
        return;
    }
    const response = await axios.get('/api/projects/suggest', { params: { keywords, except: event.id } });
    matches.value = response.data;
};

watch(() => event.name, () => {
    dismissed.value = false;
    clearTimeout(timer);
    if (isDraft.value) timer = setTimeout(search, 500);
}, { immediate: true });

onUnmounted(() => clearTimeout(timer));

const join = async (match) => {
    busy.value = true;
    error.value = '';
    try {
        await axios.post(`/hosting/event/${event.slug}/link-request`,
            match.type === 'project' ? { project_id: match.id } : { source_event_id: match.id });
        // What was typed first, then a fresh page: the form is filled in from the show.
        await axios.post(`/api/hosting/event/${event.slug}`, {
            name: event.name,
            ...(event.tag_line ? { tag_line: event.tag_line } : {}),
        });
        window.location.reload();
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'That did not work. Please try again.';
        busy.value = false;
    }
};

const remove = async () => {
    busy.value = true;
    try {
        await axios.delete(`/hosting/event/${event.slug}/link-request`);
        event.link_request = null;
        dismissed.value = true;
    } finally {
        busy.value = false;
    }
};
</script>
