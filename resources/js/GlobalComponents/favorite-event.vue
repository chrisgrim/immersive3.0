<template>
    <div class="absolute right-0 top-0 z-20 flex items-center justify-center w-16 h-16">
        <button
            type="button"
            aria-label="Favorite this event"
            :aria-pressed="favorited"
            :disabled="pending"
            class="border-none p-4"
            @click.stop.prevent="onClick"
        >
            <svg
                :class="[
                    'w-12 h-12 stroke-[2px] overflow-visible transition-transform hover:scale-125 md:w-10 md:h-10',
                    favorited ? 'fill-default-red stroke-default-red' : 'fill-[#0000006e] stroke-white md:fill-white md:stroke-black',
                ]"
            >
                <use xlink:href="/storage/website-files/icons.svg#ri-heart-3-line" />
            </svg>
        </button>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    user: { type: Object, default: null },
    event: { type: Object, required: true },
});

const emit = defineEmits(['change']);

// Not every page threads a real `user` prop down through its card components
// (the live search results page doesn't), so fall back to the site-wide
// window.Laravel.user convention (see CLAUDE.md) rather than trusting the prop
// alone — otherwise a logged-in visitor gets wrongly treated as a guest.
const currentUser = computed(() => (props.user?.id ? props.user : window.Laravel?.user) ?? null);

// Local optimistic copy of the server-provided flag so a click flips the heart
// instantly instead of waiting on the round trip.
const favorited = ref(!!props.event.isFavorited);
const pending = ref(false);

// Stay in sync if the parent swaps in a new event object (e.g. a fresh page of
// listings) — but don't fight our own optimistic update mid-request.
watch(() => props.event.isFavorited, (value) => {
    if (!pending.value) {
        favorited.value = !!value;
    }
});

const onClick = async () => {
    if (pending.value) return; // one in-flight request at a time — guards a double-click race

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
        } else {
            await axios.delete(`/api/events/${props.event.slug}/favorite`);
        }
        emit('change', next);
    } catch (error) {
        favorited.value = !next; // revert on failure (network error, 401, 419, etc.)
        console.error('[favorite-event] failed to update favorite state', error);
    } finally {
        pending.value = false;
    }
};
</script>
