<template>
    <div class="mt-4 mb-6 space-y-6">
        <!-- Which show run it joins (and changing it) lives in the bottom bar's picker. -->
        <!-- The match question -->
        <div v-if="asking && matches.length && !event.link_request" class="p-5 rounded-2xl border border-neutral-300" data-match-question>
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

        <!-- A run's own name, run type and festival used to be asked here. Tags cover
             them, so they are gone from the form; anything saved earlier stays in the data. -->
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

const search = async () => {
    const keywords = event.name?.trim();
    if (!keywords || keywords.length < 3 || event.link_request) {
        matches.value = [];
        return;
    }
    try {
        const response = await axios.get('/api/projects/suggest', { params: { keywords, except: event.id } });
        // A slower answer for an older name never replaces a newer one.
        if (event.name?.trim() === keywords) matches.value = response.data;
    } catch {
        if (event.name?.trim() === keywords) matches.value = [];
    }
};

// The name is typed in name.vue, a sibling step component, so this one
// follows it here (the one watcher on this step).
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
            // They just said which show this is; a same-named one elsewhere is not a question.
            acknowledge_duplicate: true,
        });
        window.location.reload();
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'That did not work. Please try again.';
        busy.value = false;
    }
};
</script>
