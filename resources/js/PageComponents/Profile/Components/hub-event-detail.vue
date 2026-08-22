<template>
    <div class="mt-8">
        <!-- Event summary card, links out to the full event page -->
        <div class="bg-white rounded-3xl shadow-custom-1 overflow-hidden mb-6">
            <a :href="`/events/${event.slug}`" class="flex gap-4 p-4 hover:bg-neutral-50 transition-colors">
                <div class="w-40 h-40 rounded-2xl overflow-hidden bg-neutral-100 flex-shrink-0">
                    <picture v-if="event.images?.length">
                        <source type="image/webp" :srcset="`${imageUrl}${event.images[0].large_image_path}`">
                        <img class="w-full h-full object-cover" :src="`${imageUrl}${event.images[0].large_image_path.slice(0, -4)}jpg`" :alt="event.name">
                    </picture>
                    <picture v-else-if="event.largeImagePath">
                        <source type="image/webp" :srcset="`${imageUrl}${event.largeImagePath}`">
                        <img class="w-full h-full object-cover" :src="`${imageUrl}${event.largeImagePath.slice(0, -4)}jpg`" :alt="event.name">
                    </picture>
                </div>
                <div class="min-w-0 flex flex-col justify-center">
                    <p class="text-2xl font-semibold truncate">{{ event.name }}</p>
                    <p v-if="event.organizer" class="text-lg text-neutral-500 truncate">Hosted by {{ event.organizer.name }}</p>
                    <p class="text-lg text-neutral-500 mt-1">{{ event.dateRangeLabel || event.remaining.label }}</p>
                </div>
            </a>
            <div class="border-t border-neutral-200"></div>
            <div class="p-3">
                <button
                    type="button"
                    class="w-full min-h-[4rem] flex items-center justify-center gap-2 py-3 rounded-full bg-[#f2f2f2] font-semibold text-xl hover:bg-neutral-200 transition-colors"
                    @click="share"
                >
                    <component :is="RiShareBoxLine" class="w-5 h-5" />
                    {{ shareMessage || 'Share event' }}
                </button>
            </div>
        </div>

        <!-- The actual Organizer/show.vue Profile Card component, not a redrawn copy. -->
        <organizer-profile-card v-if="event.organizer" :organizer="event.organizer" class="mt-6" />

        <!-- Notify toggle — scoped to this event/organizer specifically (backed
             by the favorite/follow rows, not the account-wide default). Turning
             it on also follows the organizer if not already followed, since the
             per-organizer override lives on that follow row; turning it off
             just disables the preference, it doesn't unfollow. -->
        <div v-if="event.organizer" class="flex items-center justify-between bg-white rounded-3xl shadow-custom-1 p-6 mt-4 gap-4">
            <div>
                <p class="text-lg font-semibold">Get updates</p>
                <p class="text-neutral-500">New events from {{ event.organizer.name }}, or new dates for this event</p>
            </div>
            <preference-toggle
                class="flex-shrink-0"
                :aria-label="`Get updates for ${event.name}`"
                :checked="notifyOn"
                :disabled="notifyPending"
                @change="toggleNotify"
            />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { RiShareBoxLine } from '@remixicon/vue';
import OrganizerProfileCard from '@/GlobalComponents/organizer-profile-card.vue';
import PreferenceToggle from '@/GlobalComponents/preference-toggle.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
});

// Hub/index.vue owns `selectedEvent` (this component's `event` prop) and the
// `events` list it's drawn from — notify-updated lets it apply the change to
// its own state instead of this component mutating the prop directly.
const emit = defineEmits(['notify-updated']);

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const shareMessage = ref('');

// Scoped to THIS event/organizer — the backend resolves the effective state
// per favorite/follow row (falling back to the account-wide default only
// when this specific item has never been toggled), so it already arrives
// correct with the event and needs no separate fetch — see
// FavoriteController::index()/updateNotify().
const notifyOn = ref(!!props.event.notifyUpdates);
const notifyPending = ref(false);

const toggleNotify = async () => {
    if (notifyPending.value) return;

    const next = !notifyOn.value;
    notifyOn.value = next; // optimistic
    emit('notify-updated', next);
    notifyPending.value = true;

    try {
        await axios.patch(`/api/hub/events/${props.event.slug}/notify-updates`, { enabled: next });
    } catch (error) {
        notifyOn.value = !next; // revert
        emit('notify-updated', !next);
        console.error('[hub-event-detail] failed to update notification preferences', error);
    } finally {
        notifyPending.value = false;
    }
};

const share = async () => {
    const url = `${window.location.origin}/events/${props.event.slug}`;

    if (navigator.share) {
        try {
            await navigator.share({ title: props.event.name, url });
        } catch (error) {
            // AbortError when the user cancels the native share sheet — not a failure.
        }
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        shareMessage.value = 'Link copied!';
        setTimeout(() => (shareMessage.value = ''), 2000);
    } catch (error) {
        console.error('[hub-event-detail] failed to copy link', error);
    }
};
</script>
