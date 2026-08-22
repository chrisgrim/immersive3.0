<template>
    <div
        class="group relative flex w-full min-w-0 bg-white rounded-3xl shadow-custom-1 hover:shadow-custom-2 transition-shadow p-3"
        :class="{ 'opacity-50': isPast }"
    >
        <!-- Hover-revealed only from `lg` up (a real mouse/hover device) —
             below that there's no hover to discover it behind, so it stays
             always visible instead (the only way to remove a liked event on
             touch, previously missing entirely there). -->
        <button
            type="button"
            aria-label="Remove from Liked Events"
            class="absolute -right-2 -top-2 z-20 w-12 h-12 rounded-full bg-white border-2 border-black shadow-custom-1 flex items-center justify-center opacity-100 lg:opacity-0 lg:group-hover:opacity-100 focus-visible:opacity-100 transition-opacity hover:bg-neutral-100"
            @click.stop.prevent="$emit('remove-requested', event)"
        >
            <component :is="RiCloseLine" class="w-7 h-7" />
        </button>
        <button type="button" class="flex w-full min-w-0 text-left" @click="$emit('select', event)">
            <div class="w-40 h-40 rounded-2xl overflow-hidden flex-shrink-0 bg-neutral-100">
                <picture v-if="event.images?.length">
                    <source type="image/webp" :srcset="`${imageUrl}${event.images[0].large_image_path}`">
                    <img
                        loading="lazy"
                        class="h-full w-full object-cover"
                        :src="`${imageUrl}${event.images[0].large_image_path.slice(0, -4)}jpg`"
                        :alt="`${event.name} event`"
                    >
                </picture>
                <picture v-else-if="event.largeImagePath">
                    <source type="image/webp" :srcset="`${imageUrl}${event.largeImagePath}`">
                    <img
                        loading="lazy"
                        class="h-full w-full object-cover"
                        :src="`${imageUrl}${event.largeImagePath.slice(0, -4)}jpg`"
                        :alt="`${event.name} event`"
                    >
                </picture>
            </div>

            <div class="flex-1 min-w-0 pl-4 pr-8 flex flex-col justify-between py-1">
                <div>
                    <h3 class="text-2.5xl font-semibold mb-1 line-clamp-2">{{ event.name }}</h3>
                    <p
                        class="text-lg leading-none"
                        :class="event.remaining.type === 'ended' ? 'text-neutral-400' : 'text-neutral-600'">
                        {{ event.dateRangeLabel || event.remaining.label }}
                    </p>
                </div>
                <div v-if="event.organizer" class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full overflow-hidden bg-neutral-200 flex-shrink-0 flex items-center justify-center border border-white shadow-sm">
                        <template v-if="event.organizer.thumbImagePath">
                            <img class="w-full h-full object-cover" :src="`${imageUrl}${event.organizer.thumbImagePath.slice(0, -4)}jpg`" :alt="event.organizer.name">
                        </template>
                        <span v-else class="text-lg font-bold text-neutral-500">{{ event.organizer.name?.[0]?.toUpperCase() }}</span>
                    </div>
                    <p class="text-lg text-neutral-500 truncate">{{ event.organizer.name }}</p>
                </div>
            </div>
        </button>
    </div>
</template>

<script setup>
import { RiCloseLine } from '@remixicon/vue';

defineProps({
    event: { type: Object, required: true },
    isPast: { type: Boolean, default: false },
});

// The actual removal (DELETE call) is deferred — Hub/index.vue owns the undo
// window, so it decides when (or whether) this ever hits the server. This
// component just reports the request and disappears immediately when the
// parent drops it from the list.
defineEmits(['remove-requested', 'select']);

const imageUrl = import.meta.env.VITE_IMAGE_URL;
</script>
