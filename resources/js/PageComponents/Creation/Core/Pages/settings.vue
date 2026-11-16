<template>
    <!-- The event's settings, opened from the card above Name in the editor's
         sidebar, as cards like Airbnb's: Duplicate, its show run (add it to
         one, or take it out) and Delete. Each does its own thing at once;
         there is nothing here for Update to save. -->
    <main class="w-full" data-event-settings>
        <h2 class="text-black">Settings</h2>

        <!-- Airbnb's action cards: an icon, a short name, one plain line. -->
        <div class="mt-[3.2rem] flex flex-col gap-4">
            <button type="button" :disabled="busy" :class="card" data-settings-duplicate @click="duplicate">
                <span :class="badge"><svg class="w-[2.2rem] h-[2.2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><rect x="8" y="8" width="12" height="12" rx="2"/><path stroke-linecap="round" d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"/></svg></span>
                <span class="flex-1 min-w-0">
                    <span :class="title">Duplicate event</span>
                    <span :class="line">Copy this event and all its details.</span>
                </span>
                <svg :class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- Its show run: the one it is part of or waiting to join, or pick one. -->
            <div v-if="current" :class="[card, 'cursor-default hover:shadow-none hover:border-[#dddddd]']" data-settings-run>
                <span :class="badge"><svg class="w-[2.2rem] h-[2.2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10"/></svg></span>
                <span class="flex-1 min-w-0">
                    <span :class="title">{{ current.joined ? 'In a show run' : 'Waiting to join a show run' }}</span>
                    <span :class="line" class="truncate">{{ current.name }}</span>
                </span>
                <button v-if="current.removable" type="button" :disabled="busy" class="flex-shrink-0 h-[3.6rem] px-[1.6rem] rounded-full border border-[#dddddd] hover:border-[#222222] text-[1.4rem] font-semibold text-[#222222] disabled:opacity-50" data-settings-remove-run @click="removeRun">{{ runBusy ? 'Removing…' : 'Remove' }}</button>
            </div>
            <!-- Add to one only once this company has a show run (they load as the page opens);
                 the first one starts from Duplicate ("a new run of the same show?"). -->
            <div v-else-if="choices?.length" :class="[runsOpen ? 'rounded-[1.6rem] border border-[#222222] shadow-[0_6px_20px_rgba(0,0,0,0.08)]' : '']">
                <button type="button" :class="[card, runsOpen ? '!border-0 !shadow-none' : '']" :aria-expanded="runsOpen" data-settings-add-run @click="toggleRuns">
                    <span :class="badge"><svg class="w-[2.2rem] h-[2.2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 12h10M4 18h7M17 15v6M14 18h6"/></svg></span>
                    <span class="flex-1 min-w-0">
                        <span :class="title">Add to a show run</span>
                        <span :class="line">Group it with your other runs of the same show.</span>
                    </span>
                    <svg :class="[chevron, runsOpen ? 'rotate-90' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div v-if="runsOpen" class="px-[2.4rem] pb-[2rem]">
                    <button v-for="choice in choices" :key="choice.id" type="button" :disabled="busy" class="w-full flex items-center gap-4 p-3 text-left rounded-[1.2rem] hover:bg-[#f7f7f7] disabled:opacity-50" data-settings-run-choice @click="joinRun(choice)">
                        <span class="w-[4.4rem] h-[4.4rem] rounded-[1rem] overflow-hidden bg-[#ebebeb] flex-shrink-0"><img v-if="choice.image" :src="`${imageUrl}${choice.image}`" alt="" class="w-full h-full object-cover"></span>
                        <span class="flex-1 min-w-0 text-[1.5rem] font-medium text-[#222222] truncate">{{ choice.name }}</span>
                        <span class="text-[1.4rem] font-semibold underline flex-shrink-0">Join</span>
                    </button>
                    <p class="mt-3 text-[1.3rem] text-[#6a6a6a] font-normal">The show run's owner, or EI, says yes before it joins.</p>
                </div>
            </div>
            <p v-if="runError" class="text-[1.4rem] text-[#c13515]" role="alert">{{ runError }}</p>

            <button type="button" :disabled="busy" :class="card" data-settings-delete @click="asking = true">
                <span :class="[badge, '!bg-[#fff1f0] !text-[#c13515]']"><svg class="w-[2.2rem] h-[2.2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/></svg></span>
                <span class="flex-1 min-w-0">
                    <span :class="[title, '!text-[#c13515]']">Delete event</span>
                </span>
                <svg :class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        <DuplicateAsk v-if="duplicateAsking" :event="event" @close="duplicateAsking = false" />

        <!-- Delete? The same pop-up as the hosting list's. -->
        <Teleport to="body">
            <div v-if="asking" class="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[1003]" @click="close">
                <div class="bg-white w-full md:w-[50rem] md:mx-4 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl relative max-h-[90vh] overflow-y-auto px-[2.4rem] pt-[2.4rem] pb-[3.2rem]" role="alertdialog" aria-modal="true" aria-labelledby="settings-delete-title" data-settings-delete-ask @click.stop>
                    <button type="button" aria-label="Close" class="w-[4rem] h-[4rem] -ml-[0.8rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" @click="close">
                        <svg class="w-[1.8rem] h-[1.8rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                    <div class="flex flex-col items-center text-center mt-2">
                        <div class="w-[18.5rem] aspect-square rounded-[1.6rem] overflow-hidden bg-[#ebebeb]">
                            <img v-if="picture" :src="picture" :alt="event.name || ''" class="w-full h-full object-cover">
                        </div>
                        <h2 id="settings-delete-title" class="mt-[2rem] text-[1.8rem] leading-[2.4rem] font-semibold text-[#222222] break-words hyphens-auto">Delete {{ event.name || 'this event' }}?</h2>
                        <p v-if="place" class="mt-1 text-[1.6rem] text-[#6a6a6a] font-normal">{{ place }}</p>
                                                <p v-if="deleteError" class="mt-4 text-[1.4rem] text-[#c13515]" role="alert">{{ deleteError }}</p>
                    </div>
                    <button type="button" :disabled="deleting" class="mt-[3.2rem] w-full h-[5.2rem] rounded-[1.2rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold flex items-center justify-center gap-3 disabled:opacity-50" data-settings-delete-yes @click="remove">
                        <svg class="w-[2rem] h-[2rem]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
                        {{ deleting ? 'Deleting…' : 'Delete event' }}
                    </button>
                    <button type="button" :disabled="deleting" class="mt-[1.6rem] w-full h-[4.8rem] rounded-[1.2rem] text-[1.6rem] font-semibold text-[#222222] hover:bg-[#f7f7f7] disabled:opacity-50" @click="close">Cancel</button>
                </div>
            </div>
        </Teleport>
    </main>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import axios from 'axios';
import DuplicateAsk from '../../duplicate-ask.vue';

const event = inject('event');
const imageUrl = import.meta.env.VITE_IMAGE_URL;

const card = 'w-full flex items-center gap-[1.6rem] p-[2rem] md:p-[2.4rem] rounded-[1.6rem] border border-[#dddddd] bg-white text-left transition hover:border-[#222222] hover:shadow-[0_6px_20px_rgba(0,0,0,0.08)] disabled:opacity-50';
const badge = 'w-[4.8rem] h-[4.8rem] rounded-full bg-[#f7f7f7] text-[#222222] flex items-center justify-center flex-shrink-0';
const title = 'block text-[1.6rem] leading-[2.2rem] font-semibold text-[#222222]';
const line = 'block mt-[0.2rem] text-[1.4rem] leading-[2rem] text-[#6a6a6a] font-normal';
const chevron = 'w-[1.8rem] h-[1.8rem] flex-shrink-0 text-[#222222] transition-transform';

const runBusy = ref(false);
const deleting = ref(false);
const busy = computed(() => runBusy.value || deleting.value);
const failure = (error, fallback) => Object.values(error.response?.data?.errors ?? {}).flat()[0] ?? error.response?.data?.message ?? fallback;

// Duplicate: its own pop-up asks first (and whether the copy is a new run).
const duplicateAsking = ref(false);
const duplicate = () => {
    if (!busy.value) duplicateAsking.value = true;
};

// Its show run. Part of one: out only for whoever keeps that show run.
// Asking to join one: the request can always be withdrawn.
const current = computed(() => {
    if (event.run_of) return { joined: true, name: event.run_of.name, removable: Boolean(event.run_of.kept) };
    if (event.link_request) return { joined: false, name: event.link_request.show, removable: true };
    return null;
});
const runsOpen = ref(false);
const choices = ref(null);
const runError = ref('');
const toggleRuns = () => {
    runsOpen.value = !runsOpen.value;
    runError.value = '';
};
// The show runs it could join, loaded as the page opens: none, no Add card.
onMounted(async () => {
    if (current.value) return;
    try {
        const response = await axios.get(`/hosting/event/${event.slug}/show-runs`);
        choices.value = response.data.choices ?? [];
    } catch {
        choices.value = [];
    }
});
// Joining can fill empty parts from the show, so the page reloads to show them.
const joinRun = async (choice) => {
    if (busy.value) return;
    runBusy.value = true;
    runError.value = '';
    try {
        await axios.post(`/hosting/event/${event.slug}/link-request`, { project_id: choice.id });
        window.location.reload();
    } catch (error) {
        runBusy.value = false;
        runError.value = failure(error, 'That did not work. Please try again.');
    }
};
const removeRun = async () => {
    if (busy.value || !current.value?.removable) return;
    runBusy.value = true;
    runError.value = '';
    try {
        await axios.delete(event.run_of ? `/hosting/show-runs/${event.run_of.slug}/instances/${event.slug}` : `/hosting/event/${event.slug}/link-request`);
        window.location.reload();
    } catch (error) {
        runBusy.value = false;
        runError.value = failure(error, 'That did not work. Please try again.');
    }
};

// Delete, after the pop-up asks; then back to the events list.
const asking = ref(false);
const deleteError = ref('');
const picture = computed(() => {
    const path = event.images?.[0]?.large_image_path ?? event.thumbImagePath ?? null;
    return path ? `${imageUrl}${path}` : null;
});
const place = computed(() => [event.location?.city, event.location?.region].filter(Boolean).join(', '));
const close = () => {
    if (deleting.value) return;
    asking.value = false;
    deleteError.value = '';
};
const remove = async () => {
    if (deleting.value) return;
    deleting.value = true;
    deleteError.value = '';
    try {
        await axios.delete(`/hosting/event/${event.slug}`);
        window.location.href = event.organizer?.slug ? `/hosting/events?organizer=${event.organizer.slug}` : '/hosting/events';
    } catch (error) {
        deleting.value = false;
        deleteError.value = failure(error, 'That did not delete. Please try again.');
    }
};

// Nothing for Update: each setting saves itself.
defineExpose({ isValid: async () => false, submitData: async () => null });
</script>
