<template>
    <!-- On a phone this is a bottom sheet teleported to <body> — the site's
         standard mobile format (see the hosting page's Filter Events sheet):
         dimmed backdrop, title, close cross. On desktop it stays a popover
         rendered in place under the caption. -->
    <teleport to="body" :disabled="!isMobile">
        <div :class="isMobile ? 'fixed inset-0 z-[1001] flex items-end justify-center bg-black bg-opacity-50' : 'contents'">
            <div
                v-click-outside="close"
                role="dialog"
                aria-label="Choose a currency"
                :class="isMobile
                    ? 'flex h-[60vh] w-full flex-col rounded-t-2xl bg-white shadow-xl'
                    : 'absolute left-0 z-50 mt-2 flex w-[42rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-neutral-300 bg-white shadow-xl'"
                @keydown.esc.prevent="close"
            >
                <div v-if="isMobile" class="flex items-center justify-between px-8 pb-4 pt-8">
                    <h3 class="text-2xl font-medium">Currency</h3>
                    <button type="button" aria-label="Close" @click="close">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <div :class="isMobile ? 'px-8 pb-4' : 'border-b border-neutral-200 p-3'">
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        placeholder="Search by name or code"
                        autocomplete="off"
                        aria-label="Search currencies"
                        class="w-full rounded-xl border border-neutral-300 px-4 py-3 text-lg placeholder-neutral-400 focus:border-black focus:outline-none"
                        @input="highlighted = null"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="choose(highlightedCode)"
                    />
                </div>

                <div
                    ref="listEl"
                    role="listbox"
                    :class="isMobile ? 'min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-8' : 'max-h-[40vh] overflow-y-auto overscroll-contain py-1'"
                >
                    <template v-for="group in groups" :key="group.label">
                        <p class="px-4 pb-1 pt-3 text-sm font-medium uppercase tracking-wide text-neutral-500">{{ group.label }}</p>
                        <button
                            v-for="code in group.codes"
                            :key="`${group.label}-${code}`"
                            type="button"
                            role="option"
                            :aria-selected="code === modelValue"
                            :data-code="code"
                            :data-row="`${group.label}-${code}`"
                            class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left"
                            :class="`${group.label}-${code}` === highlightedRow ? 'bg-neutral-100' : 'hover:bg-neutral-50'"
                            @click="choose(code)"
                            @mousemove="highlighted = `${group.label}-${code}`"
                        >
                            <span class="w-20 shrink-0 font-semibold">{{ code }}</span>
                            <span class="flex-1 truncate" :class="{ 'font-semibold': code === modelValue }">{{ currencyName(code) }}</span>
                            <span class="shrink-0 text-neutral-500">{{ currencySymbol(code) }}</span>
                        </button>
                    </template>
                    <p v-if="!visible.length" class="px-4 py-6 text-neutral-500">No currency matches “{{ query }}”.</p>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
/**
 * The ticket step's currency picker: a searchable list of every current ISO
 * 4217 currency, with a short "Suggested" group on top (the venue's
 * currency, then the ones most events here are priced in). Names and
 * symbols come from the browser's own locale data (useCurrency.js), so
 * there is no list to maintain — a currency exists the moment ICU knows it.
 *
 * Replaced a fixed dropdown of twelve hand-picked symbols that grew by one
 * every time someone emailed to ask.
 */
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import {
    CURRENCY_CODES,
    DEFAULT_CURRENCY,
    currencyName,
    currencySymbol,
    isCurrencyCode,
} from '@/composables/useCurrency';

const props = defineProps({
    modelValue: { type: String, default: DEFAULT_CURRENCY },
    suggested: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'close']);

// Below Tailwind's md breakpoint the picker is a bottom sheet (see the
// template). Tracked live so rotating a tablet swaps the layout.
const mobileQuery = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
    ? window.matchMedia('(max-width: 767px)')
    : null;
const isMobile = ref(mobileQuery?.matches ?? false);
const onMediaChange = (event) => { isMobile.value = event.matches; };

const query = ref('');
const searchInput = ref(null);
const listEl = ref(null);

// Built once: the full list, alphabetical by NAME (people look for
// "Singapore", not "SGD"), with a lower-cased haystack to search.
const entries = CURRENCY_CODES
    .map((code) => ({ code, name: currencyName(code), symbol: currencySymbol(code) }))
    .sort((a, b) => a.name.localeCompare(b.name))
    .map((entry) => ({ ...entry, haystack: `${entry.code} ${entry.name} ${entry.symbol}`.toLowerCase() }));

const allCodes = entries.map((entry) => entry.code);

const suggestedCodes = computed(() => [...new Set(props.suggested)].filter(isCurrencyCode));

const visible = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return allCodes;

    return entries.filter((entry) => entry.haystack.includes(q)).map((entry) => entry.code);
});

const groups = computed(() => {
    const searching = query.value.trim() !== '';
    const out = [];

    if (!searching && suggestedCodes.value.length) {
        out.push({ label: 'Suggested', codes: suggestedCodes.value });
    }
    if (visible.value.length) {
        out.push({ label: searching ? 'Matches' : 'All currencies', codes: visible.value });
    }

    return out;
});

// Every row the arrow keys walk, in display order.
const walkable = computed(() =>
    groups.value.flatMap((group) => group.codes.map((code) => ({ row: `${group.label}-${code}`, code })))
);

// A row key ("Suggested-USD"), not a code: a suggested currency appears
// twice, and the arrow keys have to be able to walk past both copies.
// Starts on the current currency (its first copy, if suggested) — set here,
// synchronously, rather than in onMounted, so nothing typed in the meantime
// is overridden.
const highlighted = ref(walkable.value.find((entry) => entry.code === props.modelValue)?.row ?? null);

// A computed fallback rather than a watcher on `query`: the highlight is
// whatever was last hovered or arrowed to, as long as it is still on
// screen, otherwise the first thing that is. Typing clears it (the @input
// handler above), so Enter always takes the top match of a fresh search.
const highlightedRow = computed(() =>
    walkable.value.some((entry) => entry.row === highlighted.value) ? highlighted.value : walkable.value[0]?.row ?? null
);

const highlightedCode = computed(() =>
    walkable.value.find((entry) => entry.row === highlightedRow.value)?.code ?? null
);

const scrollTo = (row, block = 'nearest') => {
    nextTick(() => listEl.value?.querySelector(`[data-row="${row}"]`)?.scrollIntoView?.({ block }));
};

const move = (delta) => {
    const rows = walkable.value;
    if (!rows.length) return;

    const index = rows.findIndex((entry) => entry.row === highlightedRow.value);
    const next = rows[(index + delta + rows.length) % rows.length];
    highlighted.value = next.row;
    scrollTo(next.row);
};

const choose = (code) => {
    if (!code) return;

    emit('update:modelValue', code);
    emit('close');
};

const close = () => emit('close');

onMounted(() => {
    mobileQuery?.addEventListener?.('change', onMediaChange);

    nextTick(() => {
        // Not on a phone: focusing the search there raises the keyboard over
        // half the sheet before the suggested currencies have been seen.
        if (!isMobile.value) searchInput.value?.focus({ preventScroll: true });
        if (highlighted.value) scrollTo(highlighted.value, 'center');
    });
});

onUnmounted(() => mobileQuery?.removeEventListener?.('change', onMediaChange));
</script>
