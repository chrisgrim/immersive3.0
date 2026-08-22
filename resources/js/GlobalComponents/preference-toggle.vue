<template>
    <label class="inline-flex items-center" :class="disabled ? 'cursor-not-allowed' : 'cursor-pointer'">
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
