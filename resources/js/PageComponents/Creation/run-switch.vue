<template>
    <!-- Airbnb's switch row: one line says what it is, one says what it does. -->
    <button type="button" role="switch" :aria-checked="modelValue" class="w-full flex items-center gap-4 p-[1.6rem] rounded-[1.6rem] border border-[#dddddd] hover:border-[#222222] text-left transition" data-duplicate-run @click="$emit('update:modelValue', !modelValue)">
        <span class="w-[4.4rem] h-[4.4rem] rounded-[1rem] bg-[#f7f7f7] flex-shrink-0 flex items-center justify-center">
            <svg class="w-[2.2rem] h-[2.2rem] text-[#222222]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><rect x="3" y="7" width="8" height="12" rx="1.5"/><rect x="13" y="5" width="8" height="12" rx="1.5"/></svg>
        </span>
        <span class="flex-1 min-w-0">
            <span class="block text-[1.5rem] leading-[2rem] font-semibold text-[#222222] break-words">{{ offer.title }}</span>
            <span class="block mt-[0.2rem] text-[1.3rem] leading-[1.8rem] text-[#6a6a6a] font-normal">{{ offer.line }}</span>
        </span>
        <span class="relative w-[4.8rem] h-[3.2rem] rounded-full flex-shrink-0 transition-colors" :class="modelValue ? 'bg-[#222222]' : 'bg-[#b0b0b0]'" data-duplicate-run-box :data-on="modelValue">
            <span class="absolute top-[0.4rem] left-[0.4rem] w-[2.4rem] h-[2.4rem] rounded-full bg-white shadow transition-transform" :class="modelValue ? 'translate-x-[1.6rem]' : ''"></span>
        </span>
    </button>
</template>

<script setup>
defineProps({
    offer: { type: Object, required: true },
    modelValue: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>
