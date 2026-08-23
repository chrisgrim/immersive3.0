<template>
    <div>
        <!-- Sizes measured off Airbnb's live page: H2 26px/600, sub-section
             H3 22px/600, row label 16px/600, row description 14px/400 grey,
             links 14px/500. -->
        <h2 class="text-4.5xl font-semibold mb-10">Notifications</h2>

        <div v-if="loading" class="py-40 text-center text-neutral-500 text-1xl">Loading…</div>

        <div v-else-if="loadError" class="py-40 text-center">
            <p class="text-neutral-500 text-1xl mb-4">Could not load your notification settings.</p>
            <button
                type="button"
                class="px-6 py-2 rounded-full border border-black text-1xl font-medium hover:bg-black hover:text-white transition-colors"
                @click="fetchCounts"
            >
                Try again
            </button>
        </div>

        <template v-else>
            <!-- Not a persistent toggle — see ClearAllNotificationsAction. Every
                 saved event / followed organizer already has its own "Get
                 updates" toggle (Liked Events → the event), on by default.
                 This is a one-time bulk action for silencing all of them at
                 once instead of hunting down each one individually — clicking
                 it doesn't unsave or unfollow anything, and anything saved or
                 followed afterward still notifies by default until this is
                 clicked again. -->
            <div class="border-b border-neutral-200 pb-8 mb-8">
                <h3 class="text-4xl font-semibold mb-1">Saved content updates</h3>
                <p class="text-1xl text-neutral-500 mb-6">
                    You get email updates by default for events you've saved and organizers you follow. Each one has its own "Get updates" toggle on the Liked Events page — this clears all of them at once instead of turning them off one by one.
                </p>

                <a :href="likedEventsUrl" class="block text-1xl text-neutral-600 mb-6 hover:text-black hover:underline w-fit">
                    You currently have notifications on for <strong>{{ followedOrganizersCount }}</strong> followed {{ followedOrganizersCount === 1 ? 'organizer' : 'organizers' }} and <strong>{{ savedEventsCount }}</strong> saved {{ savedEventsCount === 1 ? 'event' : 'events' }}.
                </a>

                <button
                    type="button"
                    class="px-6 py-3 rounded-full border border-black text-1xl font-medium hover:bg-black hover:text-white transition-colors disabled:opacity-50"
                    :disabled="clearing"
                    @click="clearAll"
                >
                    {{ clearing ? 'Clearing…' : 'Clear all notifications' }}
                </button>

                <p v-if="clearedMessage" class="text-1xl text-green-700 mt-4">{{ clearedMessage }}</p>
                <p v-if="clearError" class="text-red-600 text-1xl mt-4">{{ clearError }}</p>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const loading = ref(true);
const loadError = ref(false);
const clearing = ref(false);
const clearError = ref('');
const clearedMessage = ref('');

const savedEventsCount = ref(0);
const followedOrganizersCount = ref(0);

const likedEventsUrl = computed(() => `/users/${window.Laravel.user.id}/events`);

const fetchCounts = async () => {
    loading.value = true;
    loadError.value = false;

    try {
        const { data } = await axios.get('/api/hub/notification-preferences/counts');
        savedEventsCount.value = data.saved_events_count;
        followedOrganizersCount.value = data.followed_organizers_count;
    } catch (error) {
        console.error('[account-settings-notifications] failed to load counts', error);
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

const clearAll = async () => {
    if (clearing.value) return;

    clearing.value = true;
    clearError.value = '';
    clearedMessage.value = '';

    try {
        const { data } = await axios.post('/api/hub/notification-preferences/clear-all');
        savedEventsCount.value = data.saved_events_count;
        followedOrganizersCount.value = data.followed_organizers_count;
        clearedMessage.value = 'Notifications cleared for everything you\'ve saved and followed.';
    } catch (error) {
        clearError.value = 'Could not clear notifications. Please try again.';
        console.error('[account-settings-notifications] failed to clear', error);
    } finally {
        clearing.value = false;
    }
};

onMounted(fetchCounts);
</script>
