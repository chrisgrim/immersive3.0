<template>
    <!-- One event in the hosting list, Airbnb's listings row: the whole row opens
         it; on a computer a box appears on the left on hover to pick it (the side
         panel opens) and Edit, Duplicate and Delete on the right. On a phone an arrow.
         Used alone and inside a show run's group. -->
    <div
        class="group/row relative grid gap-6 md:gap-8 items-center rounded-2xl cursor-pointer transition-colors grid-cols-[4.5rem_minmax(0,1fr)_auto] md:grid-cols-[6.4rem_36%_minmax(0,1fr)_12rem_16rem]"
        :class="[nested ? 'px-0 md:pl-[4rem] md:pr-4 py-2' : 'px-0 md:-ml-[4rem] md:pl-[4rem] md:pr-4 p-4', selected ? 'bg-[#f7f7f7]' : 'md:hover:bg-[#f7f7f7]']"
        role="button"
        tabindex="0"
        :aria-label="`Open ${event.name || 'this event'}`"
        :data-event-row="event.id"
        @click="$emit('open')"
        @keydown.enter.self="$emit('open')"
    >
        <!-- Pick it: inside the row on the left, in the page's margin as on Airbnb (the grey goes
             round it and the picture lines up with the title), seen on hover, or always while any is picked. -->
        <label
            class="hidden md:flex absolute z-10 top-1/2 -translate-y-1/2 w-[3.2rem] h-[3.2rem] items-center justify-center cursor-pointer"
            :class="['left-[0.4rem]', selected || picking ? 'opacity-100' : 'opacity-0 group-hover/row:opacity-100 focus-within:opacity-100']"
            @click.stop
        >
            <input type="checkbox" class="sr-only peer" :checked="selected" :aria-label="`Pick ${event.name || 'this event'}`" data-event-pick @change="$emit('pick')">
            <span class="w-[2.4rem] h-[2.4rem] rounded-[0.6rem] border-[1.5px] flex items-center justify-center transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-black peer-focus-visible:ring-offset-2" :class="selected ? 'bg-[#222222] border-[#222222]' : 'bg-white border-[#b0b0b0] hover:border-[#222222]'">
                <svg v-if="selected" class="w-[1.6rem] h-[1.6rem] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
        </label>

        <!-- The picture, with the name right beside it (Airbnb's listings). -->
        <div class="relative h-full flex items-center">
            <div class="aspect-[3/4] overflow-hidden rounded-2xl" :class="nested ? 'h-20 md:h-[6.4rem]' : 'h-24 md:h-[8rem]'">
                <img v-if="image" :src="image" :alt="`${event.name} Immersive Event`" class="h-full w-full object-cover rounded-2xl" />
                <div v-else class="h-full w-full rounded-2xl bg-gray-300"></div>
            </div>
        </div>

        <div class="min-w-0">
            <p class="flex items-center gap-2">
                <span class="font-medium break-words hyphens-auto" :class="nested ? 'text-2xl md:text-xl' : 'text-3xl md:text-2xl'">{{ event.name || 'No name yet' }}</span>
                <svg v-if="event.status === 'r'" class="h-5 w-5 text-gray-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </p>
            <p class="text-lg md:text-md leading-4 text-gray-500">last edited: {{ edited }}</p>
            <!-- Inside a group: a copy that joins once approved. -->
            <span v-if="nested && waiting" class="inline-flex mt-2 px-3 py-1 rounded-full bg-[#fff4e5] text-md font-medium text-[#b45309]">Waiting to join</span>
            <!-- Part of a show run that is not grouped here (only one of its runs in this list): a small tag. -->
            <a
                v-if="runOf && !nested"
                :href="runOfHref"
                class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 rounded-full bg-[#f2f2f2] hover:bg-[#ebebeb] text-md font-medium text-[#222222]"
                @click.stop
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h10"/></svg>
                {{ runOf.waiting ? 'Joining' : 'Run of' }} {{ runOf.name }}
            </a>
            <a v-if="runOf && !nested && runOf.kept && runOf.asking" :href="`/hosting/show-runs/${runOf.slug}/edit`" class="inline-flex mt-2 ml-2 px-3 py-1 rounded-full bg-[#fff4e5] text-md font-medium text-[#b45309] hover:underline" @click.stop>{{ runOf.asking }} asking to join</a>
            <p class="flex md:hidden flex-row items-start mt-2">
                <span :class="status.color" class="w-4 h-4 mt-[0.3rem] rounded-full flex-shrink-0"></span>
                <span class="text-lg font-medium text-gray-500 ml-4">{{ status.progress }}</span>
            </p>
        </div>

        <p class="hidden md:flex flex-row items-center justify-self-start">
            <span :class="status.color" class="w-4 h-4 rounded-full"></span>
            <span class="text-lg font-medium text-gray-500 ml-4">{{ status.progress }}</span>
        </p>

        <div class="hidden md:flex justify-center items-center">
            <div v-if="event.total_clicks" class="bg-gray-100 px-4 py-2 rounded-full font-medium">{{ event.total_clicks }}</div>
            <div v-else class="text-gray-400">-</div>
        </div>

        <!-- On a computer, Edit, Duplicate and Delete while hovering (or tabbing through); on a phone an arrow. -->
        <div class="flex items-center justify-end gap-2 md:gap-3 md:pr-2">
            <div v-if="!event.is_deleted" class="hidden md:group-hover/row:flex md:group-focus-within/row:flex items-center gap-3" data-row-tools>
                <button type="button" aria-label="Edit" title="Edit" class="flex w-[4.4rem] h-[4.4rem] rounded-full items-center justify-center bg-white border border-[#dddddd] text-[#222222] hover:border-[#222222] hover:shadow-[0_2px_8px_rgba(0,0,0,0.12)] transition" data-row-edit @click.stop="$emit('edit')">
                    <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button type="button" aria-label="Duplicate" title="Duplicate" class="flex w-[4.4rem] h-[4.4rem] rounded-full items-center justify-center bg-white border border-[#dddddd] text-[#222222] hover:border-[#222222] hover:shadow-[0_2px_8px_rgba(0,0,0,0.12)] transition" data-row-duplicate @click.stop="$emit('duplicate')">
                    <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="8" y="8" width="12" height="12" rx="2"/><path stroke-linecap="round" d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"/></svg>
                </button>
                <button type="button" aria-label="Delete" title="Delete" class="flex w-[4.4rem] h-[4.4rem] rounded-full items-center justify-center bg-white border border-[#dddddd] text-[#222222] hover:border-[#c13515] hover:text-[#c13515] hover:shadow-[0_2px_8px_rgba(0,0,0,0.12)] transition" data-row-delete @click.stop="$emit('delete')">
                    <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
                </button>
            </div>
            <svg class="w-[2rem] h-[2rem] text-[#222222] transition-opacity" :class="event.is_deleted ? 'md:opacity-0 md:group-hover/row:opacity-100' : 'md:hidden'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    event: { type: Object, required: true },
    // { color, progress } from the dashboard's getStatusInfo()
    status: { type: Object, required: true },
    edited: { type: String, default: '' },
    // Shown inside a show run's group: a little smaller, no "Run of" tag.
    nested: { type: Boolean, default: false },
    // Inside a group: not a run yet, only asking to join (shows "Waiting to join").
    waiting: { type: Boolean, default: false },
    // { id, name, slug, kept } when this event is a run of a show run
    runOf: { type: Object, default: null },
    // Picked for the side panel, and whether any row is (then every box shows).
    selected: { type: Boolean, default: false },
    picking: { type: Boolean, default: false },
});

defineEmits(['open', 'pick', 'edit', 'duplicate', 'delete']);

const imageUrl = import.meta.env.VITE_IMAGE_URL;

const image = computed(() => {
    const path = props.event.images?.[0]?.large_image_path ?? props.event.thumbImagePath ?? null;
    return path ? `${imageUrl}${path}` : null;
});

// A show run this company keeps opens its edit page; one kept by another company, its public page.
const runOfHref = computed(() => (props.runOf?.kept ? `/hosting/show-runs/${props.runOf.slug}/edit` : `/events/${props.runOf?.slug}`));
</script>
