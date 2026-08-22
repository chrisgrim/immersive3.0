<template>
    <nav class="flex flex-col">
        <button
            v-for="item in items"
            :key="item.tab"
            @click="$emit('navigate', item.tab)"
            :class="[
                'text-left px-[16px] py-[16px] rounded-2xl transition-colors text-2.5xl flex items-center gap-4',
                currentTab === item.tab
                    ? 'bg-neutral-100 font-medium text-black'
                    : 'font-normal text-neutral-700 hover:bg-neutral-50'
            ]"
        >
            <component :is="item.icon" class="w-6 h-6 flex-shrink-0" />
            {{ item.label }}
        </button>
    </nav>
</template>

<script setup>
import { RiUserLine, RiHeartLine, RiSearchLine } from '@remixicon/vue';

defineProps({
    currentTab: {
        type: String,
        default: 'about'
    }
});

defineEmits(['navigate']);

const items = [
    { tab: 'about', label: 'About me', icon: RiUserLine },
    { tab: 'events', label: 'Liked events', icon: RiHeartLine },
    { tab: 'search-preferences', label: 'Saved searches', icon: RiSearchLine },
];
</script>
