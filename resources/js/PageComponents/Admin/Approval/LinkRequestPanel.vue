<template>
    <div v-if="request || matches.length" class="p-8 shadow-custom-1 rounded-3xl">
        <h3 class="text-xl font-semibold mb-4">{{ request ? 'Asked to join a show' : 'Looks like a run of' }}</h3>

        <div v-if="request" class="mb-6">
            <p class="text-2xl font-medium">{{ requestedName }}</p>
            <p class="text-gray-600">Made by {{ requestedMaker ?? 'nobody yet' }}</p>
            <p v-if="request.requested_by" class="text-gray-600">Asked by {{ request.requested_by.name }}</p>
            <p v-if="request.note" class="text-gray-600 whitespace-pre-line">{{ request.note }}</p>

            <div class="flex flex-wrap gap-6 mt-6">
                <label v-for="option in requestOptions" :key="option.value" class="flex items-center gap-2 text-xl">
                    <input v-model="action" type="radio" :value="option.value" />
                    {{ option.label }}
                </label>
            </div>
        </div>

        <div v-if="choosing" class="space-y-4">
            <div v-if="matches.length" class="flex flex-wrap gap-2">
                <button
                    v-for="match in matches"
                    :key="`${match.type}-${match.id}`"
                    type="button"
                    class="px-4 py-2 border rounded-2xl text-xl"
                    :class="isChosen(match) ? 'border-black bg-neutral-100' : 'border-neutral-300'"
                    @click="choose(match)"
                >
                    {{ match.name }}
                    <span class="text-gray-500">{{ match.organizer?.name }}</span>
                </button>
            </div>

            <input
                v-model="showSearch"
                type="text"
                placeholder="Search shows"
                class="w-full text-xl border border-neutral-400 rounded-2xl p-4"
                @input="searchShows"
            />
            <div v-if="showResults.length" class="flex flex-wrap gap-2">
                <button
                    v-for="show in showResults"
                    :key="show.id"
                    type="button"
                    class="px-4 py-2 border border-neutral-300 rounded-2xl text-xl"
                    @click="choose({ ...show, type: 'project' })"
                >
                    {{ show.name }}
                </button>
            </div>
        </div>

        <div v-if="joining" class="mt-6 space-y-4">
            <p class="text-gray-600">
                {{ targetName }}. Made by {{ maker?.name ?? targetMaker ?? 'nobody yet' }}.
                <span v-if="makesProject">Approving makes the show's page from that listing.</span>
            </p>

            <input
                v-model="makerSearch"
                type="text"
                placeholder="Correct who made it"
                class="w-full text-xl border border-neutral-400 rounded-2xl p-4"
                @input="searchMakers"
            />
            <div v-if="makerSearch" class="flex flex-wrap gap-2">
                <button
                    v-for="organizer in makerResults"
                    :key="organizer.id"
                    type="button"
                    class="px-4 py-2 border border-neutral-300 rounded-2xl text-xl"
                    @click="pickMaker(organizer)"
                >
                    {{ organizer.name }}
                </button>
                <button
                    type="button"
                    class="px-4 py-2 border border-dashed border-neutral-400 rounded-2xl text-xl"
                    @click="createBareMaker"
                >
                    Add "{{ makerSearch }}" as a new company
                </button>
            </div>

            <input
                v-if="makesProject"
                v-model="slug"
                type="text"
                placeholder="Address, only if the plain name is another company's show"
                class="w-full text-xl border border-neutral-400 rounded-2xl p-4"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    event: { type: Object, required: true },
});

const requestOptions = [
    { value: 'confirm', label: 'Join this show' },
    { value: 'change', label: 'A different show' },
    { value: 'reject', label: 'The listing alone' },
];

const request = computed(() => props.event?.link_request ?? null);
const matches = computed(() => props.event?.name_matches ?? []);

const action = ref(request.value ? 'confirm' : 'change');
const target = ref(null);
const maker = ref(null);
const slug = ref('');
const showSearch = ref('');
const showResults = ref([]);
const makerSearch = ref('');
const makerResults = ref([]);

const requestedName = computed(() => request.value?.project?.name ?? request.value?.source_event?.name);
const requestedMaker = computed(() => (request.value?.project ?? request.value?.source_event)?.organizer?.name);

const choosing = computed(() => action.value === 'change');
const joining = computed(() => action.value === 'confirm' || (choosing.value && target.value !== null));
const targetName = computed(() => (choosing.value ? target.value?.name : requestedName.value));
const targetMaker = computed(() => (choosing.value ? target.value?.organizer?.name : requestedMaker.value));
const makesProject = computed(() => (choosing.value ? target.value?.type === 'listing' : Boolean(request.value?.source_event)));

const isChosen = (match) => target.value?.type === match.type && target.value?.id === match.id;
const choose = (match) => {
    target.value = isChosen(match) ? null : match;
};

const searchShows = async () => {
    if (!showSearch.value.trim()) {
        showResults.value = [];
        return;
    }
    const response = await axios.get('/api/admin/projects', { params: { keywords: showSearch.value } });
    showResults.value = response.data.data;
};

const searchMakers = async () => {
    if (!makerSearch.value.trim()) {
        makerResults.value = [];
        return;
    }
    const response = await axios.get('/api/admin/manage/organizers', { params: { search: makerSearch.value } });
    makerResults.value = response.data.data;
};

const pickMaker = (organizer) => {
    maker.value = organizer;
    makerSearch.value = '';
    makerResults.value = [];
};

const createBareMaker = async () => {
    const response = await axios.post('/api/admin/organizers/bare', { name: makerSearch.value });
    pickMaker(response.data.organizer);
};

// What the approve call sends. Null means no link decision at all.
const decision = computed(() => {
    if (action.value === 'reject') {
        return { action: 'reject' };
    }
    if (!joining.value) {
        return null;
    }

    const link = { action: action.value };
    if (choosing.value) {
        link[target.value.type === 'listing' ? 'source_event_id' : 'project_id'] = target.value.id;
    }
    if (maker.value) {
        link.organizer_id = maker.value.id;
    }
    if (makesProject.value && slug.value.trim()) {
        link.slug = slug.value.trim();
    }
    return link;
});

defineExpose({ decision });
</script>
