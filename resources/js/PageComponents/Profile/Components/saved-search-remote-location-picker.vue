<template>
    <!-- Search/Components/remote-type-search-input.vue — built to match the
         nav's own at-home-search.vue as closely as possible, but nav has its
         own separate inline implementation, not this component; the two are
         kept in sync by hand. -->
    <RemoteTypeSearchInput v-model="query" @select="select" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import RemoteTypeSearchInput from '@/PageComponents/Search/Components/remote-type-search-input.vue';

const props = defineProps({
    // The stored remoteLocation slug (e.g. "zoom"), or null.
    modelValue: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');

// Only commits on picking a real prediction — same as the nav's own
// at-home-search.vue, not on every keystroke.
const select = (type) => {
    query.value = type.name;
    emit('update:modelValue', type.slug);
};

// Resolve the stored slug to its real display name on load, rather than
// title-casing it as a stand-in — limit:20 covers the entire curated set
// (see EventAttributesController::publicRemoteLocations), the default
// limit of 10 could miss one that sorts past the major platforms.
//
// publicRemoteLocations() only returns types with a currently-bookable
// event, though — a saved search can reference one that's since gone quiet
// (every event of that type closed). Same title-cased fallback
// saved-search-card.vue's summary already uses for exactly this case,
// rather than leaving the field looking blank/unset.
const titleCase = (slug) => slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

onMounted(async () => {
    if (!props.modelValue) return;

    try {
        const { data } = await axios.get('/api/remotelocations/public', { params: { limit: 20 } });
        const match = (data || []).find((type) => type.slug === props.modelValue);
        query.value = match ? match.name : titleCase(props.modelValue);
    } catch (error) {
        console.error('[saved-search-remote-location-picker] failed to resolve slug', error);
        query.value = titleCase(props.modelValue);
    }
});
</script>
