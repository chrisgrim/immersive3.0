<template>
    <div>
        <div v-if="loading" class="py-40 text-center text-neutral-500">Loading…</div>

        <div v-else-if="!searches.length" class="py-40 text-center text-neutral-500">
            Nothing saved yet — save a search to get a quick shortcut back to it here.
        </div>

        <div v-else class="flex flex-col gap-4">
            <saved-search-card
                v-for="item in searches"
                :key="item.id"
                :search="item"
                :selected="selectedSearch?.id === item.id"
                @remove-requested="$emit('remove-requested', $event)"
                @pin-toggled="$emit('pin-toggled', $event)"
                @select="$emit('select', $event)"
            />
        </div>
    </div>
</template>

<script setup>
// Presentational — Hub/index.vue owns fetching, the authoritative list
// array, and selection (same split as Events.vue/hub-event-card.vue, since
// the right-pane editor needs the same data the list renders from).
import SavedSearchCard from '../Components/saved-search-card.vue';

defineProps({
    searches: {
        type: Array,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    selectedSearch: {
        type: Object,
        default: null,
    },
});

defineEmits(['select', 'pin-toggled', 'remove-requested']);
</script>
