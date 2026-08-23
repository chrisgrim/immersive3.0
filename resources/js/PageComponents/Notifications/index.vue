<template>
    <div class="mx-auto w-full max-w-screen-md px-8 pt-20 pb-40">
        <div class="flex items-center justify-between mb-10">
            <h1 class="text-4xl font-semibold">Notifications</h1>
            <div class="flex items-center gap-4">
                <button
                    v-if="hasUnread"
                    type="button"
                    class="text-sm font-semibold underline hover:no-underline"
                    @click="markAllRead"
                >
                    Mark all as read
                </button>
                <a
                    href="/account-settings/notifications"
                    aria-label="Notification settings"
                    class="w-20 h-20 rounded-full flex items-center justify-center hover:bg-neutral-100 transition-colors flex-shrink-0"
                >
                    <component :is="RiSettings3Line" class="w-10 h-10 text-neutral-700" />
                </a>
            </div>
        </div>

        <div v-if="loading" class="py-32 text-center text-neutral-500">Loading…</div>

        <div v-else-if="loadError" class="py-32 text-center">
            <p class="text-neutral-500 mb-4">Could not load your notifications.</p>
            <button type="button" class="px-6 py-2 rounded-full border border-black text-sm font-medium hover:bg-black hover:text-white transition-colors" @click="fetchNotifications">
                Try again
            </button>
        </div>

        <!-- Truthful empty state — matches the reference design's copy exactly. -->
        <div v-else-if="!notifications.length" class="flex flex-col items-center text-center py-32">
            <div class="w-24 h-24 rounded-full bg-neutral-100 flex items-center justify-center mb-8">
                <component :is="RiNotification3Line" class="w-12 h-12 text-neutral-400" />
            </div>
            <h2 class="text-2xl font-semibold mb-3">No notifications yet</h2>
            <p class="text-neutral-500 max-w-sm">
                You've got a blank slate (for now). We'll let you know when updates arrive.
            </p>
        </div>

        <div v-else class="divide-y divide-neutral-200">
            <a
                v-for="notification in notifications"
                :key="notification.id"
                :href="`/events/${notification.data.event_slug}`"
                class="flex items-center gap-4 py-5 hover:bg-neutral-50 -mx-4 px-4 rounded-xl transition-colors"
                @click="markRead(notification)"
            >
                <div class="w-14 h-14 rounded-xl overflow-hidden bg-neutral-100 flex-shrink-0">
                    <template v-if="notification.data.event_image">
                        <picture>
                            <source type="image/webp" :srcset="`${imageUrl}${notification.data.event_image}`">
                            <img class="w-full h-full object-cover" :src="`${imageUrl}${notification.data.event_image.slice(0, -4)}jpg`" :alt="notification.data.event_name">
                        </picture>
                    </template>
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <component :is="RiNotification3Line" class="w-6 h-6 text-neutral-400" />
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="font-medium">{{ messageFor(notification) }}</p>
                    <p class="text-sm text-neutral-500">{{ timeAgo(notification.created_at) }}</p>
                </div>

                <div v-if="!notification.read_at" class="w-2.5 h-2.5 rounded-full bg-blue-600 flex-shrink-0"></div>
            </a>

            <div v-if="hasMorePages" class="pt-8 text-center">
                <button
                    type="button"
                    :disabled="loadingMore"
                    class="px-6 py-2 rounded-full border border-black text-sm font-medium hover:bg-black hover:text-white transition-colors disabled:opacity-50"
                    @click="loadMore"
                >
                    {{ loadingMore ? 'Loading…' : 'Load more' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RiNotification3Line, RiSettings3Line } from '@remixicon/vue';

const imageUrl = import.meta.env.VITE_IMAGE_URL;

const loading = ref(true);
const loadError = ref(false);
const notifications = ref([]);
const nextPageUrl = ref(null);
const loadingMore = ref(false);

const hasMorePages = computed(() => !!nextPageUrl.value);

const hasUnread = computed(() => notifications.value.some((n) => !n.read_at));

const messageFor = (notification) => {
    const { type, event_name: eventName, organizer_name: organizerName } = notification.data;

    if (type === 'saved_event_new_dates') {
        return `New dates were added to "${eventName}"`;
    }
    if (type === 'followed_organizer_new_event') {
        return `${organizerName || 'An organizer you follow'} posted a new event: "${eventName}"`;
    }
    return eventName || 'You have a new update';
};

const timeAgo = (isoDate) => {
    const seconds = Math.floor((Date.now() - new Date(isoDate).getTime()) / 1000);
    const units = [
        ['year', 31536000], ['month', 2592000], ['week', 604800],
        ['day', 86400], ['hour', 3600], ['minute', 60],
    ];
    for (const [unit, secondsInUnit] of units) {
        const count = Math.floor(seconds / secondsInUnit);
        if (count >= 1) return `${count} ${unit}${count > 1 ? 's' : ''} ago`;
    }
    return 'Just now';
};

const fetchNotifications = async () => {
    loading.value = true;
    loadError.value = false;

    try {
        const { data } = await axios.get('/api/notifications');
        notifications.value = data.notifications.data;
        nextPageUrl.value = data.notifications.next_page_url;
    } catch (error) {
        console.error('[notifications] failed to load', error);
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

const loadMore = async () => {
    if (!nextPageUrl.value || loadingMore.value) return;

    loadingMore.value = true;
    try {
        const { data } = await axios.get(nextPageUrl.value);
        notifications.value.push(...data.notifications.data);
        nextPageUrl.value = data.notifications.next_page_url;
    } catch (error) {
        console.error('[notifications] failed to load more', error);
    } finally {
        loadingMore.value = false;
    }
};

const markRead = (notification) => {
    if (notification.read_at) return;
    const previous = notification.read_at;
    notification.read_at = new Date().toISOString(); // optimistic
    axios.post(`/api/notifications/${notification.id}/read`).catch((error) => {
        notification.read_at = previous; // revert
        console.error('[notifications] failed to mark read', error);
    });
};

const markAllRead = async () => {
    const previouslyUnread = notifications.value.filter((n) => !n.read_at);
    notifications.value.forEach((n) => { if (!n.read_at) n.read_at = new Date().toISOString(); }); // optimistic

    try {
        await axios.post('/api/notifications/read-all');
    } catch (error) {
        previouslyUnread.forEach((n) => { n.read_at = null; }); // revert
        console.error('[notifications] failed to mark all read', error);
    }
};

onMounted(fetchNotifications);
</script>
