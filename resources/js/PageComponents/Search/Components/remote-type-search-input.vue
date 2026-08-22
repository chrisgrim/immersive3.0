<template>
    <div class="relative border border-slate-300 rounded-full" :class="{ 'bg-neutral-200': open }" v-click-outside="close">
        <svg class="remote-type-search-icon absolute top-1/2 -translate-y-1/2 left-8 w-8 h-8 fill-black z-[1002]" :class="{ 'is-compact': compact }">
            <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
        </svg>
        <input
            ref="inputEl"
            class="remote-type-search-input relative text-1xl rounded-full bg-transparent w-full font-bold z-40 py-6 placeholder-black"
            :class="{ 'is-compact': compact }"
            :value="modelValue"
            :placeholder="placeholder"
            @input="handleInput($event.target.value)"
            @focus="handleFocus"
            autocomplete="off"
            type="text"
        >

        <ul
            class="absolute top-full left-0 bg-white w-full mx-0 overflow-hidden mt-2 p-8 list-none rounded-5xl shadow-custom-7 z-50"
            v-if="open"
            @click.stop
        >
            <RecentSearchesList v-if="showRecentSearches" :searches="recentSearches" />
            <li
                class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100 group"
                v-for="type in types"
                :key="type.id"
                @click.stop="select(type)"
            >
                <div class="w-20 h-20 flex items-center justify-center bg-neutral-100 rounded-xl">
                    <svg class="w-14 h-14 transition-all duration-500 group-hover:fill-black group-hover:stroke-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                        <path d="M8 10h8M8 14h5"></path>
                    </svg>
                </div>
                <span class="transition-all group-hover:font-medium">{{ type.name }}</span>
            </li>
            <li v-if="!types.length" class="py-4 px-8 text-neutral-500">
                No matching types
            </li>
        </ul>
    </div>
</template>

<script setup>
// Remote-location-type search for the Profile saved-search editor's picker
// (saved-search-remote-location-picker.vue) — same reasoning as
// city-search-input.vue: built to match the nav's own at-home-search.vue as
// closely as possible, but nav does NOT import this component, it has its
// own separate inline implementation kept in sync by hand. This component
// only owns the input + dropdown + /api/remotelocations/public fetch/
// selection mechanic, not any of nav's other concerns (dates, compact-mode
// pill, filters, submit/redirect, recent-searches fetching).
import { ref } from 'vue';
import axios from 'axios';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective';
import RecentSearchesList from '@/PageComponents/Nav/Components/recent-searches-list.vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search At Home Type',
    },
    compact: {
        type: Boolean,
        default: false,
    },
    showRecentSearches: {
        type: Boolean,
        default: false,
    },
    recentSearches: {
        type: Array,
        default: () => [],
    },
});

// select carries the full { id, name, slug } record on a real pick.
const emit = defineEmits(['update:modelValue', 'select', 'focus', 'input']);

const vClickOutside = ClickOutsideDirective;

const open = ref(false);
const types = ref([]);
const inputEl = ref(null);
let searchTimeout = null;

const fetchTypes = async (search) => {
    try {
        const { data } = await axios.get('/api/remotelocations/public', {
            params: search ? { search } : {},
        });
        types.value = data || [];
    } catch (error) {
        console.error('[remote-type-search-input] failed to fetch remote location types', error);
        types.value = [];
    }
};

const handleInput = (value) => {
    emit('update:modelValue', value);
    emit('input', value);
    open.value = true;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchTypes(value), 200);
};

const select = (type) => {
    open.value = false;
    emit('update:modelValue', type.name);
    emit('select', { id: type.id, name: type.name, slug: type.slug });
};

const handleFocus = (event) => {
    open.value = true;
    event.target.select();
    fetchTypes('');
    emit('focus');
};

const close = () => {
    open.value = false;
};

defineExpose({ close });
</script>

<style scoped>
.remote-type-search-input {
    padding-left: 6rem;
    transition: padding-left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.remote-type-search-input.is-compact {
    padding-left: 4rem;
    pointer-events: none;
}
.remote-type-search-input.is-compact:not(:focus) {
    font-size: 1.4rem !important;
}
.remote-type-search-icon {
    transition: left 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.remote-type-search-icon.is-compact {
    left: 1.4rem;
}

@media (prefers-reduced-motion: reduce) {
    .remote-type-search-input,
    .remote-type-search-icon {
        transition: none;
    }
}
</style>
