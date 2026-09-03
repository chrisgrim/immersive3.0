<template>
    <div
        v-click-outside="close"
        class="absolute left-0 z-50 mt-2 flex w-[42rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-neutral-300 bg-white shadow-xl"
        role="dialog"
        aria-label="Choose a currency"
        @keydown.esc.prevent="close"
    >
        <div class="border-b border-neutral-200 p-3">
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

        <div ref="listEl" class="max-h-[40vh] overflow-y-auto overscroll-contain py-1" role="listbox">
            <template v-for="group in groups" :key="group.label">
                <p class="px-4 pb-1 pt-3 text-sm font-medium uppercase tracking-wide text-neutral-500">{{ group.label }}</p>
                <button
                    v-for="code in group.codes"
                    :key="`${group.label}-${code}`"
                    type="button"
                    role="option"
                    :aria-selected="code === modelValue"
                    :data-code="code"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left"
                    :class="code === highlightedCode ? 'bg-neutral-100' : 'hover:bg-neutral-50'"
                    @click="choose(code)"
                    @mousemove="highlighted = code"
                >
                    <span class="w-14 shrink-0 font-semibold">{{ code }}</span>
                    <span class="flex-1 truncate" :class="{ 'font-semibold': code === modelValue }">{{ currencyName(code) }}</span>
                    <span class="shrink-0 text-neutral-500">{{ currencySymbol(code) }}</span>
                </button>
            </template>
            <p v-if="!visible.length" class="px-4 py-6 text-neutral-500">No currency matches “{{ query }}”.</p>
        </div>
    </div>
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
import { computed, nextTick, onMounted, ref } from 'vue';
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

const query = ref('');
const searchInput = ref(null);
const listEl = ref(null);
const highlighted = ref(props.modelValue);

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

// What the arrow keys walk, in display order.
const walkable = computed(() => groups.value.flatMap((group) => group.codes));

// A computed fallback rather than a watcher on `query`: the highlight is
// whatever was last hovered or arrowed to, as long as it is still on
// screen, otherwise the first thing that is. Typing clears it (the @input
// handler above), so Enter always takes the top match of a fresh search.
const highlightedCode = computed(() =>
    walkable.value.includes(highlighted.value) ? highlighted.value : walkable.value[0] ?? null
);

const scrollTo = (code, block = 'nearest') => {
    nextTick(() => listEl.value?.querySelector(`[data-code="${code}"]`)?.scrollIntoView?.({ block }));
};

const move = (delta) => {
    const list = walkable.value;
    if (!list.length) return;

    const index = list.indexOf(highlightedCode.value);
    highlighted.value = list[(index + delta + list.length) % list.length];
    scrollTo(highlighted.value);
};

const choose = (code) => {
    if (!code) return;

    emit('update:modelValue', code);
    emit('close');
};

const close = () => emit('close');

onMounted(() => {
    nextTick(() => {
        searchInput.value?.focus({ preventScroll: true });
        scrollTo(props.modelValue, 'center');
    });
});
</script>
