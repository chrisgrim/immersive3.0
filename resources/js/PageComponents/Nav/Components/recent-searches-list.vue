<template>
    <li
        v-for="search in searches"
        :key="search.id"
        class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100 group rounded-3xl cursor-pointer"
        @click="handleSelect(search)"
    >
        <!-- Solid black + a history icon (not the grey pin/magnifying-glass
             boxes the default city/type rows use below) — these are past
             searches, not places, and need to read as a different kind of
             row at a glance, not just a same-shaped row with different text. -->
        <div class="w-20 h-20 flex items-center justify-center bg-black rounded-xl flex-shrink-0">
            <component :is="RiHistoryLine" class="w-10 h-10 text-white" />
        </div>
        <div class="flex-1 min-w-0">
            <span class="block truncate transition-all group-hover:font-medium">{{ search.name }}</span>
            <span class="block truncate text-sm text-neutral-500">{{ summaryFor(search) }}</span>
        </div>
        <button
            type="button"
            :aria-label="search.pinned ? 'Unpin search' : 'Pin search'"
            class="flex-shrink-0 p-4 rounded-full hover:bg-neutral-200"
            @click.stop="togglePin(search)"
        >
            <component
                :is="search.pinned ? RiPushpinFill : RiPushpinLine"
                class="w-8 h-8"
                :class="search.pinned ? 'text-black' : 'text-neutral-400'"
            />
        </button>
        <button
            type="button"
            class="flex-shrink-0 text-lg font-semibold text-neutral-500 hover:text-black px-2"
            @click.stop="goToEdit(search)"
        >
            Edit
        </button>
    </li>
</template>

<script setup>
// Renders as bare <li> rows (no wrapping <ul>) so the host pill can drop it
// straight into its own existing dropdown <ul> — same positioning/styling,
// prepended above the default city/type list when the input hasn't been
// typed in yet and the user has saved searches (see location-search.vue /
// at-home-search.vue). Self-contained: pin and Edit both act directly
// (PATCH / navigate) rather than bubbling events up, since neither needs
// anything from the host pill.
import axios from 'axios';
import moment from 'moment-timezone';
import { RiHistoryLine, RiPushpinFill, RiPushpinLine } from '@remixicon/vue';

defineProps({
    searches: {
        type: Array,
        required: true
    }
});

const handleSelect = (search) => {
    window.location.href = search.url;
};

// Second line: a short human summary of the criteria (same field set
// BuildSearchUrlAction reads, mirroring saved-search-card.vue's version on
// the Hub page) plus when it was last searched — updated_at, not
// created_at, since the overwrite-in-place slot's criteria/name get
// replaced on every search (see SaveSearchAction), so created_at can be a
// long time out of date relative to what's actually showing.
const summaryFor = (search) => {
    const criteria = search.criteria || {};
    const parts = [];

    if (criteria.city) parts.push(criteria.city);
    if (criteria.searchType === 'inPerson') parts.push('In-person');
    if (criteria.searchType === 'atHome') {
        // criteria.remoteLocation is a slug (e.g. "web-site") — title-cased
        // as a cheap stand-in for the real display name, same trade-off
        // saved-search-card.vue makes.
        parts.push(criteria.remoteLocation
            ? criteria.remoteLocation.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
            : 'Remote');
    }
    // criteria.live is only ever true for a custom-map-bounds search (see
    // BuildSearchUrlAction) — not a "happening now" date filter, which this
    // criteria schema doesn't have.
    if (criteria.live) parts.push('Custom map area');
    if (criteria.categories?.length) {
        parts.push(`${criteria.categories.length} ${criteria.categories.length === 1 ? 'category' : 'categories'}`);
    }
    if (criteria.tags?.length) {
        parts.push(`${criteria.tags.length} ${criteria.tags.length === 1 ? 'tag' : 'tags'}`);
    }
    const [minPrice, maxPrice] = criteria.price || [];
    if (minPrice > 0 && maxPrice) {
        parts.push(`$${minPrice}-$${maxPrice}`);
    } else if (maxPrice) {
        parts.push(`Up to $${maxPrice}`);
    } else if (minPrice > 0) {
        parts.push(`$${minPrice}+`);
    }

    const summary = parts.length ? parts.join(' · ') : 'All events';
    const when = search.updated_at || search.created_at;

    return when ? `${summary} · ${moment(when).fromNow()}` : summary;
};

// Mutates the passed-in search object directly rather than emitting an
// update — it's the same reactive object the host's own `searches` array
// holds, so this is picked up there for free. No client-side re-sort (see
// the plan) — pinned-first ordering corrects itself next time this list is
// re-fetched.
const togglePin = async (search) => {
    try {
        const { data } = await axios.patch(`/api/hub/saved-searches/${search.id}/pin`);
        search.pinned = data.search.pinned;
    } catch (error) {
        console.error('[recent-searches] failed to toggle pin', error);
    }
};

const goToEdit = (search) => {
    // Recent Searches only ever shows for a logged-in user (see the fetch
    // that populates it), so window.Laravel.user is always present here.
    window.location.href = `/users/${window.Laravel.user.id}/search-preferences/${search.id}`;
};
</script>
