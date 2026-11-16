<template>
    <!-- On the venue page: what it has, each with its icon (a tick for the
         venue's own words), two columns, as Airbnb's "What this place offers". -->
    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5 list-none p-0 m-0" data-venue-amenities>
        <li v-for="(item, index) in shown" :key="index" class="flex items-center gap-4">
            <component :is="amenityIcon(item)" class="w-[2.4rem] h-[2.4rem] text-[#222222] flex-shrink-0" />
            <span class="text-[1.6rem] leading-[2rem] text-[#222222] font-normal break-words min-w-0">{{ amenityLabel(item) }}</span>
        </li>
    </ul>
</template>

<script setup>
import { computed } from 'vue';
import { amenityIcon, amenityLabel } from './amenity-icons.js';

const props = defineProps({
    // Known keys, and the venue's own words as {group, label}.
    keys: { type: Array, default: () => [] },
});

// Only what the list still knows, and the venue's own words.
const shown = computed(() => (props.keys ?? []).filter((item) => amenityLabel(item)));
</script>
