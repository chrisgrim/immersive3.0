<template>
    <!-- Sizes measured off Airbnb's live sidebar: selected item is 16px/500,
         not the bolder 600 I guessed at first. Row padding is exactly 16px
         on all sides (56px total row height) and rows sit flush against
         each other with no gap — also measured, not eyeballed. -->
    <nav class="flex flex-col">
        <button
            v-for="item in visibleItems"
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
import { computed } from 'vue';
import { RiUserLine, RiShieldKeyholeLine, RiEyeOffLine, RiNotification3Line, RiKey2Line } from '@remixicon/vue';

defineProps({
    currentTab: {
        type: String,
        default: null
    }
});

defineEmits(['navigate']);

const items = [
    { tab: 'personal-info', label: 'Personal information', icon: RiUserLine },
    { tab: 'login-security', label: 'Login & security', icon: RiShieldKeyholeLine },
    { tab: 'privacy', label: 'Privacy', icon: RiEyeOffLine },
    { tab: 'notifications', label: 'Notifications', icon: RiNotification3Line },
    // Moderator/admin only — matches the /settings/api-tokens server-side
    // gate (EnsureCanManageApiTokens), which is the real enforcement; this
    // is just a UX nicety so non-moderators don't see a dead-end tab.
    { tab: 'api-keys', label: 'API keys', icon: RiKey2Line, moderatorOnly: true },
];

const visibleItems = computed(() => {
    // Same OR as the server-side gate (EnsureCanManageApiTokens): a
    // moderator/admin always sees it, and everyone does once
    // MCP_PUBLIC opens the feature generally.
    const canManageApiTokens = !!window.Laravel?.user?.isModerator || !!window.Laravel?.mcpPublic;

    return items.filter((item) => !item.moderatorOnly || canManageApiTokens);
});
</script>
