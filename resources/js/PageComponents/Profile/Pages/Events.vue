<template>
    <div>
        <hub-event-detail v-if="selectedEvent" :event="selectedEvent" @notify-updated="$emit('notify-updated', $event)" />

        <template v-else>
            <div v-if="loading" class="py-40 text-center text-neutral-500">Loading…</div>

            <div v-else-if="!events.data.length" class="py-40 text-center text-neutral-500">
                Nothing saved yet — the heart on an event card saves it here.
            </div>

            <template v-else>
                <div class="flex flex-col gap-4">
                    <hub-event-card
                        v-for="item in liveEvents"
                        :key="item.id"
                        :event="item"
                        @remove-requested="$emit('remove-requested', $event)"
                        @select="$emit('select', item)"
                    />
                </div>

                <div v-if="liveEvents.length && pastEvents.length" class="mt-10 mb-6 border-t border-neutral-200"></div>

                <button
                    v-if="pastEvents.length"
                    type="button"
                    class="flex items-center gap-2 mb-4 text-2xl font-semibold"
                    :aria-expanded="pastExpanded"
                    @click="pastExpanded = !pastExpanded"
                >
                    Past events
                    <component :is="RiArrowDownSLine" class="w-6 h-6 transition-transform" :class="{ '-rotate-180': pastExpanded }" />
                </button>
                <div v-if="pastEvents.length && pastExpanded" class="flex flex-col gap-4">
                    <hub-event-card
                        v-for="item in pastEvents"
                        :key="item.id"
                        :event="item"
                        is-past
                        @remove-requested="$emit('remove-requested', $event)"
                        @select="$emit('select', item)"
                    />
                </div>

                <div class="mt-8">
                    <pagination
                        v-if="showPagination"
                        :pagination="events"
                        @paginate="$emit('paginate', $event)"
                    />
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { RiArrowDownSLine } from '@remixicon/vue';
import pagination from '@/GlobalComponents/pagination.vue';
import HubEventCard from '../Components/hub-event-card.vue';
import HubEventDetail from '../Components/hub-event-detail.vue';

const props = defineProps({
    events: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    selectedEvent: {
        type: Object,
        default: null,
    },
});

defineEmits(['paginate', 'remove-requested', 'select', 'notify-updated']);

const showPagination = computed(() => props.events.total > props.events.per_page);

// Grouped, not just styled inline — favorited-order otherwise interleaves
// ended and current events, so a divider between them wouldn't land in a
// sensible spot. Both groups keep the page's original favorited-order.
const liveEvents = computed(() => props.events.data.filter((item) => item.remaining.type !== 'ended'));
const pastEvents = computed(() => props.events.data.filter((item) => item.remaining.type === 'ended'));

// Starts collapsed — past events are the least useful thing to see first.
const pastExpanded = ref(false);
</script>
