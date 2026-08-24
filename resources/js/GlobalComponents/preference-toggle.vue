<template>
    <!-- `relative` on the label is load-bearing, not decorative — the
         checkbox below is `sr-only` (position: absolute, no top/left set,
         so it falls back to its "static position" — the browser's guess at
         where it'd sit in normal flow). With no positioned ancestor, that
         guess resolves against this component's nearest ACTUAL positioned
         ancestor, wherever that happens to be up the tree — usually
         harmless, but inside a deeply-nested flex/overflow-y-auto layout
         (found live on the saved-search editor's Notify toggle) it landed
         ~300px past all real content, silently adding that much dead
         scrollable space to the whole page. `relative` here gives the
         checkbox its own small, local containing block, independent of
         wherever this component happens to be embedded. -->
    <label class="relative inline-flex items-center" :class="disabled ? 'cursor-not-allowed' : 'cursor-pointer'">
        <input
            type="checkbox"
            class="sr-only peer"
            :aria-label="ariaLabel"
            :checked="checked"
            :disabled="disabled"
            @change="$emit('change')"
        >
        <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all peer-checked:bg-blue-600"></div>
    </label>
</template>

<script setup>
defineProps({
    checked: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    ariaLabel: { type: String, required: true },
});

defineEmits(['change']);
</script>
