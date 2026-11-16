<template>
    <!-- "At a venue": type to find it among the venues EI knows; if it is not
         there, add it as a new one (its address comes next). -->
    <div>
        <div class="flex items-center gap-4 h-[5.6rem] px-6 rounded-full border transition-colors" :class="focused ? 'border-[#222222] shadow-focus-black' : 'border-neutral-300 hover:border-[#222222]'">
            <svg class="w-[2rem] h-[2rem] flex-shrink-0 text-[#222222]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>
            <input
                ref="input"
                v-model="query"
                autocomplete="off"
                type="text"
                maxlength="80"
                class="flex-1 min-w-0 bg-transparent text-[1.6rem] focus:outline-none"
                placeholder="Search venues, e.g. The Lowry Hotel"
                data-venue-search
                @input="onInput"
                @focus="focused = true"
                @blur="focused = false"
            >
        </div>

        <ul v-if="results.length || canAdd" class="mt-4 mx-0 w-full rounded-3xl border border-neutral-200 overflow-hidden list-none p-0 divide-y divide-neutral-100" data-venue-results>
            <li v-for="venue in results" :key="venue.id">
                <button type="button" class="w-full flex items-center gap-4 px-6 py-4 text-left hover:bg-[#f7f7f7]" @click="$emit('picked', venue)">
                    <span class="w-[4rem] h-[4rem] rounded-xl bg-[#f2f2f2] flex items-center justify-center flex-shrink-0">
                        <svg class="w-[2rem] h-[2rem] text-[#6a6a6a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[1.5rem] font-semibold text-[#222222] truncate">{{ venue.name }}<span v-if="venue.room" class="font-normal text-[#6a6a6a]">, {{ venue.room }}</span></span>
                        <span class="block text-[1.3rem] text-[#6a6a6a] truncate">{{ venue.address }}</span>
                    </span>
                </button>
            </li>
            <li v-if="canAdd">
                <button type="button" class="w-full flex items-center gap-4 px-6 py-4 text-left hover:bg-[#f7f7f7]" data-venue-add @click="$emit('add', query.trim())">
                    <span class="w-[4rem] h-[4rem] rounded-xl border border-dashed border-neutral-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-[1.8rem] h-[1.8rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    </span>
                    <span class="text-[1.5rem] text-[#222222]">{{ sameName ? 'A different' : 'Add' }} "<span class="font-semibold">{{ query.trim() }}</span>"{{ sameName ? ': add it as a new venue' : ' as a new venue' }}</span>
                </button>
            </li>
        </ul>
        <p v-if="searched && !results.length && !canAdd" class="mt-4 text-[1.4rem] text-[#6a6a6a]">Type at least two letters.</p>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

defineEmits(['picked', 'add']);

const props = defineProps({
    // What was typed before (going back from adding), to search again.
    initial: { type: String, default: '' },
});

const input = ref(null);
const query = ref(props.initial);
const results = ref([]);
const searched = ref('');
const focused = ref(false);
let timer = null;

// "Add as new" once something is typed, also when a venue of that name is
// listed: the same name can be another city's venue or another space there.
// The address step then asks "is it the same place?" if EI knows the address.
const canAdd = computed(() => {
    const typed = query.value.trim();
    return typed.length >= 2 && searched.value === typed;
});
const sameName = computed(() => results.value.some((venue) => venue.name.toLowerCase() === query.value.trim().toLowerCase()));

const search = async () => {
    const keywords = query.value.trim();
    if (keywords.length < 2) {
        results.value = [];
        searched.value = '';
        return;
    }
    let found = null;
    try {
        found = (await axios.get('/api/venues/search', { params: { keywords } })).data;
    } catch {
        found = null;
    }
    // A slower answer for an older search never replaces a newer one.
    if (query.value.trim() !== keywords) return;
    results.value = found ?? [];
    // Searched only when it answered: no "Add as new" after a failed search.
    searched.value = found === null ? '' : keywords;
};

const onInput = () => {
    clearTimeout(timer);
    timer = setTimeout(search, 300);
};

onMounted(() => {
    if (query.value) search();
    input.value?.focus();
});
onUnmounted(() => clearTimeout(timer));
</script>
