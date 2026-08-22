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
                @click="fetchPreferences"
            >
                Try again
            </button>
        </div>

        <template v-else>
            <!-- Category group, Airbnb's "Offers and updates" pattern — EI only
                 has one category so far since there are only 2 real triggers.
                 Each row's toggle is inline (no edit-modal step) — same
                 pattern as the Privacy tab's toggles, just one Email channel
                 to flip rather than a picker of channels to open first. -->
            <div class="border-b border-neutral-200 pb-8 mb-8">
                <h3 class="text-4xl font-semibold mb-1">Saved content updates</h3>
                <p class="text-1xl text-neutral-500 mb-6">
                    Updates about the events you've saved and the organizers you follow.
                </p>

                <div class="divide-y divide-neutral-200">
                    <div v-for="item in items" :key="item.key" class="flex items-start justify-between py-6 gap-4">
                        <div class="flex items-start gap-4 min-w-0">
                            <component :is="item.icon" class="w-6 h-6 text-neutral-500 flex-shrink-0 mt-1" />
                            <div class="min-w-0">
                                <p class="text-2.5xl font-semibold text-black mb-1">{{ item.label }}</p>
                                <p class="text-1xl text-neutral-500">{{ item.description }}</p>
                            </div>
                        </div>
                        <!-- checked is the INVERSE of preferences[item.key]: the stored
                             value means "mail enabled" (true = will send, matching the
                             backend's opt-in contract in wantsNotification()), but the
                             label reads as the "Turn off" action, so the switch must show
                             ON when mail is OFF to match what the label says is happening. -->
                        <preference-toggle
                            :aria-label="item.label"
                            :checked="!preferences[item.key]"
                            :disabled="saving"
                            @change="toggle(item.key)"
                        />
                    </div>
                </div>
            </div>

            <p v-if="saveError" class="text-red-600 text-1xl mb-4">{{ saveError }}</p>

            <p class="text-1xl text-neutral-400">
                These save right away and take effect immediately.
            </p>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RiCalendarEventLine, RiTeamLine } from '@remixicon/vue';
import PreferenceToggle from '@/GlobalComponents/preference-toggle.vue';

const loading = ref(true);
const loadError = ref(false);
const saving = ref(false);
const saveError = ref('');

const preferences = ref({
    saved_event_new_dates: false,
    followed_organizer_new_event: false,
});

// These are master switches, not just defaults for new saves/follows — see
// SavedEventNewDatesNotification::via() / FollowedOrganizerNewEventNotification::via().
// Turning one off here overrides every per-event/per-organizer "Get updates"
// toggle of that type, even ones a user explicitly turned on individually;
// turning it on lets those per-item toggles work normally as fine-grained
// mutes for specific events/organizers.
// Copy deliberately doesn't say "get" — this switch never turns individual
// emails ON. Opting in happens per-item, on a saved event's own detail page
// (Liked Events → the event) — one combined "Get updates" toggle there
// covers both that event's new dates and its organizer's new events (see
// hub-event-detail.vue). This account-wide switch is only a bulk kill
// switch, so you don't have to hunt down and individually turn off every
// "Get updates" toggle you've ever flipped on.
const items = [
    {
        key: 'saved_event_new_dates',
        label: 'Turn off new dates added to saved events',
        description: 'Silences email updates for every saved event at once. To get updates for a specific event, use its "Get updates" toggle on the Liked Events page — this can\'t turn individual ones back on, only stop them all.',
        icon: RiCalendarEventLine,
    },
    {
        key: 'followed_organizer_new_event',
        label: 'Turn off new events from followed organizers',
        description: 'Silences email updates for every followed organizer at once. To get updates from a specific organizer, use the "Get updates" toggle on one of their saved events, on the Liked Events page — this can\'t turn individual ones back on, only stop them all.',
        icon: RiTeamLine,
    },
];

const fetchPreferences = async () => {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await axios.get('/api/hub/notification-preferences');
        const prefs = response.data.notification_preferences;
        preferences.value.saved_event_new_dates = !!prefs.saved_event_new_dates;
        preferences.value.followed_organizer_new_event = !!prefs.followed_organizer_new_event;
    } catch (error) {
        console.error('[hub-notifications] failed to load preferences', error);
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

// Always sends the complete two-key state (not just the toggled key), and
// `saving` disables the switch while a save is in flight, so at most one
// PATCH is ever outstanding.
const toggle = async (key) => {
    if (saving.value) return;

    const previous = { ...preferences.value };
    preferences.value[key] = !preferences.value[key];
    saving.value = true;
    saveError.value = '';

    try {
        await axios.patch('/api/hub/notification-preferences', { ...preferences.value });
    } catch (error) {
        preferences.value = previous; // revert
        saveError.value = 'Could not save that change. Please try again.';
        console.error('[hub-notifications] failed to save preferences', error);
    } finally {
        saving.value = false;
    }
};

onMounted(fetchPreferences);
</script>
