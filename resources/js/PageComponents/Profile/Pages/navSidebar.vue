<template>
    <nav class="flex flex-col">
        <button
            v-for="item in items"
            :key="item.tab"
            @click="$emit('navigate', item.tab)"
            :class="[
                'text-left px-[16px] py-[16px] rounded-2xl transition-colors text-2.5xl items-center gap-4 font-normal text-neutral-700 hover:bg-neutral-50',
                // 'About me' is this whole mobile screen already — tapping it
                // there just navigates back to the page you're already on.
                // Desktop keeps it: it's a persistent sidebar showing every
                // section at once, not a list of links to jump elsewhere.
                item.tab === 'about' ? 'hidden md:flex' : 'flex',
                // md: only — desktop's sidebar is a persistent panel, so
                // highlighting the current one matches Airbnb's own desktop
                // sidebar. On mobile this list is just links to jump
                // elsewhere (see the chevron below), same as Airbnb's mobile
                // Account settings/View profile rows — none of which get a
                // filled background just for existing on the current screen.
                currentTab === item.tab ? 'md:bg-neutral-100 md:font-medium md:text-black' : ''
            ]"
        >
            <component :is="item.icon" class="w-6 h-6 flex-shrink-0" />
            <span class="flex-1">{{ item.label }}</span>
            <!-- Mobile only — desktop's sidebar is a persistent panel you're
                 always "in", not a list of links to somewhere else, so it
                 never had a chevron; on mobile this list is the only thing on
                 the Profile screen that navigates onward, same as Airbnb's own
                 Account settings/View profile rows. -->
            <RiArrowRightSLine class="w-6 h-6 flex-shrink-0 text-neutral-400 md:hidden" />
        </button>
    </nav>
</template>

<script setup>
import { RiUserLine, RiHeartLine, RiSearchLine, RiArrowRightSLine } from '@remixicon/vue';

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
