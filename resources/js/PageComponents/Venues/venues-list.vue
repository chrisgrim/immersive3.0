<template>
    <!-- Your venues, as the hosting events list: the whole row opens a venue (a
         draft where it was left, any other its edit page); on a computer a box
         on the left picks it and the side panel opens, and hovering shows Edit
         and, on a draft, Delete. Only a draft can be deleted: one sent to EI or
         live may have listings pointing at it. -->
    <div>
        <div class="relative hidden md:grid gap-8 py-4 md:-ml-[4rem] md:pl-[4rem] md:pr-4 h-36 items-center md:grid-cols-[9.6rem_minmax(0,1.4fr)_minmax(13rem,1fr)_16rem_10.4rem]">
            <button v-if="picked.size" type="button" class="absolute left-[0.4rem] top-1/2 -translate-y-1/2 w-[3.2rem] h-[3.2rem] flex items-center justify-center" :aria-label="allPicked ? 'Pick none' : 'Pick all'" data-pick-all @click="allPicked ? clear() : pickAll()">
                <span class="w-[2.4rem] h-[2.4rem] rounded-[0.6rem] bg-[#222222] flex items-center justify-center">
                    <svg v-if="allPicked" class="w-[1.6rem] h-[1.6rem] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span v-else class="w-[1.2rem] h-[0.25rem] rounded bg-white"></span>
                </span>
            </button>
            <h5 class="font-medium">Venues</h5>
            <div></div>
            <h5 class="font-medium">Status</h5>
            <h5 class="font-medium text-center">City</h5>
        </div>

        <div
            v-for="venue in venues"
            :key="venue.slug"
            class="group/row relative grid gap-6 md:gap-8 items-center rounded-2xl cursor-pointer transition-colors p-4 px-0 md:-ml-[4rem] md:pl-[4rem] md:pr-4 grid-cols-[7rem_minmax(0,1fr)_auto] md:grid-cols-[9.6rem_minmax(0,1.4fr)_minmax(13rem,1fr)_16rem_10.4rem]"
            :class="picked.has(venue.slug) ? 'bg-[#f7f7f7]' : 'md:hover:bg-[#f7f7f7]'"
            role="button"
            tabindex="0"
            :aria-label="`Open ${venue.name}`"
            :data-venue-row="venue.slug"
            @click="openRow(venue)"
            @keydown.enter.self="openRow(venue)"
        >
            <label class="hidden md:flex absolute z-10 left-[0.4rem] top-1/2 -translate-y-1/2 w-[3.2rem] h-[3.2rem] items-center justify-center cursor-pointer" :class="picked.has(venue.slug) || picked.size ? 'opacity-100' : 'opacity-0 group-hover/row:opacity-100 focus-within:opacity-100'" @click.stop>
                <input type="checkbox" class="sr-only peer" :checked="picked.has(venue.slug)" :aria-label="`Pick ${venue.name}`" data-venue-pick @change="toggle(venue)">
                <span class="w-[2.4rem] h-[2.4rem] rounded-[0.6rem] border-[1.5px] flex items-center justify-center transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-black peer-focus-visible:ring-offset-2" :class="picked.has(venue.slug) ? 'bg-[#222222] border-[#222222]' : 'bg-white border-[#b0b0b0] hover:border-[#222222]'">
                    <svg v-if="picked.has(venue.slug)" class="w-[1.6rem] h-[1.6rem] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
            </label>

            <div class="w-[7rem] h-[7rem] md:w-[9.6rem] md:h-[6.4rem] overflow-hidden rounded-2xl bg-gray-300">
                <img v-if="venue.image" :src="`${imageUrl}${venue.image}`" :alt="venue.name" class="h-full w-full object-cover" loading="lazy">
            </div>
            <div class="min-w-0">
                <p class="text-3xl md:text-2xl font-medium text-black break-words hyphens-auto">{{ venue.name }}</p>
                <p v-if="venue.owned" class="text-lg md:text-md leading-4 text-gray-500">Yours</p>
                <p class="md:hidden mt-1 flex items-center gap-2 text-[1.3rem] text-gray-500">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" :class="dot(venue)"></span>
                    <span class="truncate">{{ shortStatus(venue) }}{{ venue.place ? ` · ${venue.place}` : '' }}</span>
                </p>
            </div>
            <p class="hidden md:flex flex-row items-center">
                <span class="w-4 h-4 rounded-full" :class="dot(venue)"></span>
                <span class="text-lg font-medium text-gray-500 ml-4">{{ longStatus(venue) }}</span>
            </p>
            <div class="hidden md:flex justify-center items-center">
                <div v-if="venue.place" class="bg-gray-100 px-4 py-2 rounded-full font-medium">{{ venue.place }}</div>
                <div v-else class="text-gray-400">-</div>
            </div>

            <!-- Hovering: Edit, and Delete on a draft (or See it on the site once it is live). A phone: an arrow. -->
            <div class="flex items-center justify-end gap-3 md:pr-2">
                <div class="hidden md:group-hover/row:flex md:group-focus-within/row:flex items-center gap-3" data-venue-tools>
                    <button type="button" aria-label="Edit" title="Edit" :class="roundButton" data-venue-edit @click.stop="open(venue)">
                        <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button v-if="venue.draft" type="button" aria-label="Delete" title="Delete" :class="[roundButton, 'hover:!border-[#c13515] hover:text-[#c13515]']" data-venue-delete @click.stop="ask([venue])">
                        <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
                    </button>
                    <a v-else :href="venue.show_url" aria-label="See it on the site" title="See it on the site" :class="roundButton" data-venue-view @click.stop>
                        <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                </div>
                <svg class="md:hidden w-[2rem] h-[2rem] text-[#222222]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </div>
        </div>

        <!-- The side panel: one picked, Edit, See it on the site and Delete (a draft);
             several, Delete the drafts among them. -->
        <aside v-if="picked.size" class="hidden md:flex flex-col fixed right-0 bottom-0 w-[max(42rem,33.333vw)] bg-white border-l border-[#ebebeb] z-[300] overflow-y-auto" :style="{ top: `${panelTop}px` }" aria-label="Picked venues" data-venue-panel>
            <div class="flex items-start justify-between gap-6 px-[3.2rem] pt-[3.2rem]">
                <h2 class="text-[2.6rem] leading-[3.2rem] font-semibold text-[#222222]">{{ chosen.length === 1 ? 'Edit 1 venue' : `${chosen.length} venues picked` }}</h2>
                <button type="button" aria-label="Close" class="w-[4rem] h-[4rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7] flex-shrink-0" data-venue-panel-close @click="clear">
                    <svg class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>
            <p v-if="chosen.length === 1" class="px-[3.2rem] mt-1 text-[1.5rem] text-[#6a6a6a] font-normal break-words">{{ chosen[0].name }}</p>
            <nav class="mt-[2.4rem] px-[3.2rem] pb-[3.2rem]">
                <template v-if="chosen.length === 1">
                    <button type="button" :class="panelRow" data-venue-panel-edit @click="open(chosen[0])">{{ chosen[0].draft ? 'Finish adding it' : 'Edit' }}<svg :class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                    <a v-if="!chosen[0].draft" :href="chosen[0].show_url" :class="panelRow" data-venue-panel-view>See it on the site<svg :class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                    <button v-if="chosen[0].draft" type="button" :class="[panelRow, '!text-[#c13515]']" data-venue-panel-delete @click="ask(chosen)">Delete draft<svg :class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                </template>
                <template v-else>
                    <button v-if="drafts.length" type="button" :class="[panelRow, '!text-[#c13515]']" data-venue-panel-delete-all @click="ask(drafts)">Delete {{ drafts.length === 1 ? '1 draft' : `${drafts.length} drafts` }}<svg :class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                    <p class="py-[2.4rem] text-[1.4rem] text-[#6a6a6a] font-normal">{{ drafts.length ? 'Only drafts can be deleted.' : 'None of these are drafts, so none can be deleted.' }}</p>
                </template>
            </nav>
        </aside>

        <!-- Delete? Airbnb's pop-up: the picture and name (or how many), Delete and Cancel. -->
        <div v-if="asking" class="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[402]" @click="closeAsk">
            <div class="bg-white w-full md:w-[50rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl relative max-h-[90vh] overflow-y-auto px-[2.4rem] pt-[2.4rem] pb-[3.2rem]" role="alertdialog" aria-modal="true" aria-labelledby="venue-delete-title" data-venue-delete-ask @click.stop>
                <button type="button" aria-label="Close" class="w-[4rem] h-[4rem] -ml-[0.8rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" @click="closeAsk">
                    <svg class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
                <div class="flex flex-col items-center text-center mt-2">
                    <div v-if="asking.length === 1" class="w-[18.5rem] aspect-[3/2] rounded-[1.6rem] overflow-hidden bg-[#ebebeb]">
                        <img v-if="asking[0].image" :src="`${imageUrl}${asking[0].image}`" :alt="asking[0].name" class="w-full h-full object-cover">
                    </div>
                    <h2 id="venue-delete-title" class="mt-[2rem] text-[1.8rem] leading-[2.4rem] font-semibold text-[#222222] break-words hyphens-auto">{{ asking.length === 1 ? `Delete ${asking[0].name}?` : `Delete ${asking.length} drafts?` }}</h2>
                    <p class="mt-2 text-[1.5rem] text-[#6a6a6a] font-normal">This cannot be undone.</p>
                    <p v-if="error" class="mt-4 text-[1.4rem] text-[#c13515]" role="alert">{{ error }}</p>
                </div>
                <button type="button" :disabled="deleting" class="mt-[3.2rem] w-full h-[5.2rem] rounded-[1.2rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold flex items-center justify-center gap-3 disabled:opacity-50" data-venue-delete-yes @click="remove">
                    <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
                    {{ deleting ? 'Deleting…' : (asking.length === 1 ? 'Delete draft' : `Delete ${asking.length} drafts`) }}
                </button>
                <button type="button" :disabled="deleting" class="mt-[1.6rem] w-full h-[4.8rem] rounded-[1.2rem] text-[1.6rem] font-semibold text-[#222222] hover:bg-[#f7f7f7] disabled:opacity-50" @click="closeAsk">Cancel</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    // [{ name, slug, image, owned, draft, pending, place, open_url, show_url, delete_url }]
    venues: { type: Array, default: () => [] },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const roundButton = 'flex w-[4.4rem] h-[4.4rem] rounded-full items-center justify-center bg-white border border-[#dddddd] text-[#222222] hover:border-[#222222] hover:shadow-[0_2px_8px_rgba(0,0,0,0.12)] transition';
const panelRow = 'w-full flex items-center justify-between gap-4 py-[2.4rem] border-b border-[#ebebeb] text-left text-[1.6rem] text-[#222222] hover:underline';
const chevron = 'w-[1.8rem] h-[1.8rem] flex-shrink-0';

const dot = (venue) => (venue.draft ? 'bg-orange-400' : venue.pending ? 'bg-yellow-300' : 'bg-green-500');
const shortStatus = (venue) => (venue.draft ? 'Draft' : venue.pending ? 'Waiting for EI' : 'Live');
const longStatus = (venue) => (venue.draft ? 'Draft: finish adding it' : venue.pending ? 'Waiting for EI' : 'Live');

// Picked rows: the side panel opens while any are.
const picked = ref(new Set());
const chosen = computed(() => props.venues.filter((venue) => picked.value.has(venue.slug)));
const drafts = computed(() => chosen.value.filter((venue) => venue.draft));
const allPicked = computed(() => props.venues.length > 0 && props.venues.every((venue) => picked.value.has(venue.slug)));
// The whole page (its title and Add a venue too) makes room for the panel.
const setPicked = (next) => {
    picked.value = next;
    document.getElementById('venues')?.classList.toggle('md:!pr-[calc(max(42rem,33.333vw)+4rem)]', next.size > 0);
};
const toggle = (venue) => {
    const next = new Set(picked.value);
    next.has(venue.slug) ? next.delete(venue.slug) : next.add(venue.slug);
    setPicked(next);
};
const clear = () => setPicked(new Set());
const pickAll = () => setPicked(new Set(props.venues.map((venue) => venue.slug)));

const open = (venue) => { window.location.href = venue.open_url; };
// While any are picked, a click picks or unpicks the row instead, as on Airbnb.
const openRow = (venue) => (picked.value.size ? toggle(venue) : open(venue));

// The panel starts under the site's top bar.
const panelTop = ref(80);
const measureTop = () => {
    const bar = document.querySelector('header, nav');
    panelTop.value = Math.max(0, Math.round(bar?.getBoundingClientRect().bottom ?? 80));
};
onMounted(() => {
    measureTop();
    window.addEventListener('resize', measureTop);
});
onUnmounted(() => window.removeEventListener('resize', measureTop));

// Delete drafts, after the pop-up asks; one after another, then the page again.
const asking = ref(null);
const deleting = ref(false);
const error = ref('');
const ask = (venues) => {
    error.value = '';
    asking.value = venues.filter((venue) => venue.draft);
};
// Some deleted and some not: say so, and show the list again once it is closed.
let reloadOnClose = false;
const closeAsk = () => {
    if (deleting.value) return;
    asking.value = null;
    if (reloadOnClose) window.location.reload();
};
const remove = async () => {
    if (deleting.value || !asking.value?.length) return;
    deleting.value = true;
    error.value = '';
    let failed = 0;
    for (const venue of asking.value) {
        try {
            await axios.delete(venue.delete_url);
        } catch {
            failed += 1;
        }
    }
    if (failed) {
        deleting.value = false;
        reloadOnClose = failed < asking.value.length;
        error.value = failed === asking.value.length ? 'That did not delete. Please try again.' : `${failed} could not be deleted; the others were.`;
        return;
    }
    window.location.reload();
};
</script>
