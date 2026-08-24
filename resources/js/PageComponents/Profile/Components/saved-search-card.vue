<template>
    <div
        class="group relative flex w-full min-w-0 bg-white rounded-3xl shadow-custom-1 hover:shadow-custom-2 transition-shadow p-3"
        :class="{ 'ring-2 ring-black': selected }"
    >
        <button
            type="button"
            aria-label="Remove saved search"
            class="absolute -right-2 -top-2 z-20 w-12 h-12 rounded-full bg-white border-2 border-black shadow-custom-1 flex items-center justify-center opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-opacity hover:bg-neutral-100"
            @click.stop.prevent="$emit('remove-requested', search)"
        >
            <component :is="RiCloseLine" class="w-7 h-7" />
        </button>
        <!-- Selects for editing (opens the right-pane editor) — same
             button-wraps-content pattern as hub-event-card.vue's own
             @click="$emit('select', ...)". Running the search itself is now
             the separate "View results" link below, since selecting and
             executing are different actions once there's an editor to open. -->
        <button type="button" class="flex flex-1 min-w-0 text-left" @click="$emit('select', search)">
            <div class="w-40 h-40 rounded-2xl overflow-hidden flex-shrink-0 bg-neutral-100 flex items-center justify-center">
                <component :is="RiSearchLine" class="w-12 h-12 text-neutral-400" />
            </div>

            <div class="flex-1 min-w-0 pl-4 pr-8 flex flex-col justify-center">
                <h3 class="text-2.5xl font-semibold mb-1 line-clamp-2">{{ search.name }}</h3>
                <p class="text-lg text-neutral-600 truncate">{{ summary }}</p>
            </div>
        </button>
        <a
            :href="search.url"
            :aria-label="`View results for ${search.name}`"
            class="flex-shrink-0 self-center p-4 rounded-full hover:bg-neutral-100"
            @click.stop
        >
            <component :is="RiExternalLinkLine" class="w-8 h-8 text-neutral-500" />
        </a>
        <!-- Always visible (unlike the hover-only remove button above) —
             pinned state should be scannable at a glance across the whole
             list, not discovered by hovering each card. -->
        <button
            type="button"
            :aria-label="search.pinned ? 'Unpin search' : 'Pin search'"
            class="flex-shrink-0 mr-4 p-4 rounded-full hover:bg-neutral-100"
            @click.stop.prevent="$emit('pin-toggled', search)"
        >
            <component
                :is="search.pinned ? RiPushpinFill : RiPushpinLine"
                class="w-9 h-9"
                :class="search.pinned ? 'text-black' : 'text-neutral-400'"
            />
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RiCloseLine, RiSearchLine, RiExternalLinkLine, RiPushpinFill, RiPushpinLine } from '@remixicon/vue';
import { formatSearchDateRangeLabel } from '@/composables/useSavedSearchDateRange';

const props = defineProps({
    search: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

// Removal (DELETE) and the pin toggle (PATCH) are handled by the parent —
// same deferred-action pattern as hub-event-card.vue, so the undo window
// and any list re-sort live in one place. select opens this search in the
// right-pane editor (Hub/index.vue owns selectedSearch).
defineEmits(['remove-requested', 'pin-toggled', 'select']);

// A saved search has no photo of its own, so the card leans on a short
// human summary built from the same criteria the backend stored instead —
// matches BuildSearchUrlAction's own field set, just formatted for reading.
const summary = computed(() => {
    const criteria = props.search.criteria || {};
    const parts = [];

    if (criteria.city) parts.push(criteria.city);
    if (criteria.searchType === 'inPerson') parts.push('In-person');
    if (criteria.searchType === 'atHome') {
        // criteria.remoteLocation is a slug (e.g. "web-site") — no separate
        // display name is stored in criteria, so a light title-case pass is
        // the cheapest way to get close to the real name without another
        // fetch; the card's real name (the h3 above) is already accurate
        // since it's saved with the resolved name at save time.
        parts.push(criteria.remoteLocation
            ? criteria.remoteLocation.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
            : 'Remote');
    }
    // criteria.start/end (see NormalizeSavedSearchCriteriaAction) — already
    // captured on every auto-saved search and already carried through to
    // this row's replay `url` (BuildSearchUrlAction); this just makes that
    // existing data visible without opening the editor.
    const dateLabel = formatSearchDateRangeLabel(criteria.start, criteria.end);
    if (dateLabel) parts.push(dateLabel);
    // criteria.live is only ever true for a custom-map-bounds search (see
    // BuildSearchUrlAction).
    if (criteria.live) parts.push('Custom map area');
    if (criteria.categories?.length) {
        parts.push(`${criteria.categories.length} ${criteria.categories.length === 1 ? 'category' : 'categories'}`);
    }
    if (criteria.tags?.length) {
        // Stored as "tags" in criteria (see BuildSearchUrlAction/SearchStore),
        // but every user-facing label calls this field Genres (see
        // SavedSearchesEdit.vue's own "Genres" heading) — "tag" is the
        // backend name, not something a user should ever see.
        parts.push(`${criteria.tags.length} ${criteria.tags.length === 1 ? 'genre' : 'genres'}`);
    }

    return parts.length ? parts.join(' · ') : 'All events';
});
</script>
