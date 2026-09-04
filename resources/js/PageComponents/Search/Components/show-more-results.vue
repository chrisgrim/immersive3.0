<template>
    <!-- Replaces the numbered pages under the results. Renders nothing once
         everything is on the page; the results header carries the total. -->
    <div v-if="hasMore || limitReached" class="flex flex-col items-center gap-6 pt-10 text-center">
        <p class="text-lg text-neutral-500">Showing {{ shown }} of {{ total }} events</p>

        <!-- The depth cap: say what to do about the rest instead of vanishing. -->
        <p v-if="limitReached" class="text-lg text-neutral-700 max-w-lg">
            That's as deep as one search goes. Narrow it down — a smaller map area, dates or a category — to see the rest.
        </p>
        <button
            v-else
            type="button"
            :disabled="loading"
            class="px-10 py-4 rounded-full border-2 border-black text-xl font-medium hover:bg-black hover:text-white transition-colors disabled:opacity-50 disabled:hover:bg-transparent disabled:hover:text-black"
            @click="$emit('more')"
        >
            {{ loading ? 'Loading…' : 'Show more' }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    // Cards on the page so far.
    shown: { type: Number, required: true },
    // Matches in the whole search.
    total: { type: Number, required: true },
    // The server's word (`has_more`), not `shown < total`: it is false at
    // the last page AND at the deepest list the server will restore on a
    // cold load, so the URL can never claim a depth a refresh would lose.
    hasMore: { type: Boolean, required: true },
    // The server's `limit_reached`: the second of those two reasons.
    limitReached: { type: Boolean, default: false },
    // While the next window is in flight the button is disabled, so a
    // second click can't ask for the same depth again.
    loading: { type: Boolean, default: false },
})

defineEmits(['more'])
</script>
