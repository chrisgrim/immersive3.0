<template>
    <div class="relative flex items-center gap-8">
        <button
            type="button"
            class="flex items-center gap-2 text-lg font-medium text-black hover:underline"
            @click="$emit('share')"
        >
            <component :is="RiShareBoxLine" class="w-8 h-8 md:w-6 md:h-6" />
            <span class="hidden md:inline">Share</span>
        </button>

        <button
            type="button"
            :aria-pressed="favorited"
            :disabled="pending"
            class="flex items-center gap-2 text-lg font-medium text-black hover:underline"
            @click="onLikeClick"
        >
            <component :is="favorited ? RiHeart3Fill : RiHeart3Line" class="w-8 h-8 md:w-6 md:h-6" :class="favorited && 'text-default-red'" />
            <span class="hidden md:inline">{{ favorited ? 'Saved' : 'Save' }}</span>
        </button>

        <!-- "Show has been liked!" confirmation + get-updates toggle — only
             shown right after a fresh like, not on every subsequent visit
             (matches Airbnb's Save/Unsave being a silent toggle otherwise). -->
        <div
            v-if="showLikedModal"
            v-click-outside="closeLikedModal"
            class="absolute right-0 top-full mt-2 w-[32rem] max-w-[90vw] bg-white border border-neutral-200 rounded-3xl shadow-custom-1 p-8 z-50"
        >
            <button
                type="button"
                aria-label="Close"
                class="absolute top-4 right-4 p-2 rounded-full hover:bg-neutral-100"
                @click="closeLikedModal"
            >
                <component :is="RiCloseLine" class="w-6 h-6" />
            </button>

            <p class="text-3xl md:text-2.5xl font-semibold">Show has been liked!</p>

            <div v-if="event.organizer" class="flex items-center justify-between gap-4 mt-6">
                <div>
                    <p class="text-2.5xl md:text-2xl font-semibold">Get updates</p>
                    <p class="text-2xl md:text-1xl leading-snug text-neutral-500">New events from {{ event.organizer.name }}, or new dates for this event</p>
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
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { RiShareBoxLine, RiHeart3Line, RiHeart3Fill, RiCloseLine } from '@remixicon/vue';
import PreferenceToggle from '@/GlobalComponents/preference-toggle.vue';

const props = defineProps({
    event: { type: Object, required: true },
    user: { type: Object, default: null },
});

// Same window.Laravel fallback convention as favorite-event.vue — the show
// page's `user` prop isn't guaranteed to be populated for every viewer.
const currentUser = computed(() => (props.user?.id ? props.user : window.Laravel?.user) ?? null);

const favorited = ref(!!props.event.isFavorited);
const pending = ref(false);
const showLikedModal = ref(false);
// Starts OFF, and the server agrees: these notifications are OPT-IN. A
// fresh favorite row is created with notify_new_dates = null, and null is
// read as "do not email" everywhere (FavoriteController::mapEvent,
// SavedEventNewDatesNotification::via()). Saving an event is not by itself a
// request to be emailed about it — someone has to actively turn this on.
//
// Worth stating because the pairing is the whole point: this switch and that
// server-side default have to move together. It previously read off while
// the server treated null as "notify", so the user was already subscribed
// and the single click meant to opt OUT sent `enabled: true` — subscribing
// them harder and following the organizer too (UpdateFavoriteNotifyAction).
// Flipping only one side reintroduces exactly that.
const notifyOn = ref(false);
const notifyPending = ref(false);

const closeLikedModal = () => {
    showLikedModal.value = false;
};

const onLikeClick = async () => {
    if (pending.value) return;

    if (!currentUser.value?.id) {
        window.dispatchEvent(new CustomEvent('open-login-modal'));
        return;
    }

    const next = !favorited.value;
    favorited.value = next; // optimistic
    pending.value = true;

    try {
        if (next) {
            await axios.post(`/api/events/${props.event.slug}/favorite`);
            // Fresh like only — unliking just toggles the heart back, no modal.
            // Reset to the server's default for the row just created
            // (null -> not subscribed) — see notifyOn above.
            notifyOn.value = false;
            showLikedModal.value = true;
        } else {
            await axios.delete(`/api/events/${props.event.slug}/favorite`);
            showLikedModal.value = false;
        }
    } catch (error) {
        favorited.value = !next; // revert
        console.error('[event-actions] failed to update favorite state', error);
    } finally {
        pending.value = false;
    }
};

const toggleNotify = async () => {
    if (notifyPending.value) return;

    const next = !notifyOn.value;
    notifyOn.value = next; // optimistic
    notifyPending.value = true;

    try {
        // Requires the favorite row created by onLikeClick() above to
        // already exist — see FavoriteController::updateNotify().
        await axios.patch(`/api/hub/events/${props.event.slug}/notify-updates`, { enabled: next });
    } catch (error) {
        notifyOn.value = !next; // revert
        console.error('[event-actions] failed to update notification preferences', error);
    } finally {
        notifyPending.value = false;
    }
};
</script>
