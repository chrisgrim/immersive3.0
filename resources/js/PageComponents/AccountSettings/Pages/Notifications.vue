<template>
    <div>
        <!-- Airbnb's Notifications page: sections of rows, each a title, a
             one-line description and a switch. Sizes measured off Airbnb's
             live page: H2 26px/600, section H3 22px/600, row label 16px/600,
             description 14px/400 grey. A switch only stops that EMAIL: the
             in-app feed and the inbox are unchanged. -->
        <h2 class="text-4.5xl font-semibold mb-10">Notifications</h2>

        <div v-if="loading" class="py-40 text-center text-neutral-500 text-1xl">Loading…</div>

        <div v-else-if="loadError" class="py-40 text-center">
            <p class="text-neutral-500 text-1xl mb-4">Could not load your notification settings.</p>
            <button
                type="button"
                class="px-6 py-2 rounded-full border border-black text-1xl font-medium hover:bg-black hover:text-white transition-colors"
                @click="load"
            >
                Try again
            </button>
        </div>

        <template v-else>
            <section v-for="section in visibleSections" :key="section.title" class="mb-12" :data-notification-section="section.key">
                <h3 class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222]">{{ section.title }}</h3>
                <p v-if="section.line" class="mt-1 text-[1.4rem] text-[#6a6a6a]">{{ section.line }}</p>

                <div class="mt-4 divide-y divide-[#ebebeb] border-b border-[#ebebeb]">
                    <div v-for="row in section.rows" :key="row.key" class="flex items-center justify-between gap-8 py-[2.4rem]" :data-notification-row="row.key">
                        <div class="min-w-0">
                            <p class="text-[1.6rem] font-semibold text-[#222222]">{{ row.title }}</p>
                            <p class="mt-1 text-[1.4rem] leading-[2rem] text-[#6a6a6a]">{{ row.line }}</p>
                            <p v-if="errors[row.key]" class="mt-2 text-[1.4rem] text-[#c13515]" role="alert">{{ errors[row.key] }}</p>
                        </div>
                        <!-- Airbnb's switch: a cross when off, a tick when on. -->
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="settings[row.key]"
                            :aria-label="row.title"
                            :disabled="saving[row.key]"
                            class="relative flex-shrink-0 w-[7.2rem] h-[4rem] rounded-full transition-colors disabled:opacity-60"
                            :class="settings[row.key] ? 'bg-[#222222]' : 'bg-[#dddddd]'"
                            :data-notification-switch="row.key"
                            @click="toggle(row.key)"
                        >
                            <span class="absolute top-[0.4rem] left-[0.4rem] w-[3.2rem] h-[3.2rem] rounded-full bg-white shadow flex items-center justify-center transition-transform" :class="settings[row.key] ? 'translate-x-[3.2rem]' : ''">
                                <svg v-if="settings[row.key]" class="w-[1.4rem] h-[1.4rem] text-[#222222]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <svg v-else class="w-[1.2rem] h-[1.2rem] text-[#6a6a6a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- The one-time "Clear all" (ClearAllNotificationsAction) for the
                     per-item "Get updates" switches on the Liked Events page. -->
                <div v-if="section.key === 'follows'" class="mt-6" data-notification-clear-all>
                    <a :href="likedEventsUrl" class="block text-[1.4rem] text-[#6a6a6a] mb-4 hover:text-black hover:underline w-fit">
                        Emails are on for <strong>{{ savedEventsCount }}</strong> saved {{ savedEventsCount === 1 ? 'event' : 'events' }} and <strong>{{ followedOrganizersCount }}</strong> followed {{ followedOrganizersCount === 1 ? 'organizer' : 'organizers' }}.
                    </a>
                    <button
                        type="button"
                        class="text-[1.4rem] font-semibold underline text-[#222222] disabled:opacity-50"
                        :disabled="clearing"
                        @click="clearAll"
                    >
                        {{ clearing ? 'Turning them off…' : 'Turn them all off' }}
                    </button>
                    <p v-if="clearedMessage" class="text-[1.4rem] text-green-700 mt-2">{{ clearedMessage }}</p>
                    <p v-if="clearError" class="text-[1.4rem] text-[#c13515] mt-2">{{ clearError }}</p>
                </div>
            </section>

            <!-- Always sent: about your own account and listings. -->
            <section class="mb-12" data-notification-section="always">
                <h3 class="text-[2.2rem] leading-[2.6rem] font-semibold text-[#222222]">Account and your listings</h3>
                <p class="mt-1 text-[1.4rem] text-[#6a6a6a]">These are always on, so you never miss something important.</p>
                <ul class="mt-4 list-none p-0 m-0 divide-y divide-[#ebebeb] border-b border-[#ebebeb]">
                    <li v-for="item in ALWAYS_ON" :key="item.title" class="flex items-center justify-between gap-8 py-[2rem]">
                        <div class="min-w-0">
                            <p class="text-[1.6rem] font-semibold text-[#222222]">{{ item.title }}</p>
                            <p class="mt-1 text-[1.4rem] leading-[2rem] text-[#6a6a6a]">{{ item.line }}</p>
                        </div>
                        <span class="flex-shrink-0 text-[1.4rem] text-[#6a6a6a]">Always on</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

// Every switch the server may send (User::NOTIFICATION_KEYS); only the ones
// it returns are shown (staff-only and owner-only rows, admin rows).
const SECTIONS = [
    {
        key: 'messages',
        title: 'Messages',
        rows: [
            { key: 'messages', title: 'New messages', line: 'An email when an organizer, member or EI sends you a message, including replies in a thread. Messages always reach your inbox.' },
        ],
    },
    {
        key: 'follows',
        title: 'Things you follow',
        line: 'Turn one off to stop all emails of that kind. For each event or organizer, you choose on your Liked Events page.',
        rows: [
            { key: 'saved_event_dates', title: 'Saved events', line: 'New dates for events you saved, for the ones you turned emails on for.' },
            { key: 'followed_organizer_events', title: 'Organizers you follow', line: 'New events from organizers you follow, for the ones you turned emails on for.' },
            { key: 'saved_search_matches', title: 'Saved searches', line: 'New events that match your saved searches, for the searches you turned emails on for.' },
        ],
    },
    {
        key: 'hosting',
        title: 'Your events',
        rows: [
            { key: 'closing_soon', title: 'Events ending soon', line: 'A reminder when one of your events is about to run out of dates.' },
        ],
    },
    {
        key: 'admin',
        title: 'Admin',
        line: 'Emails for the review queue. Turning one off only stops your email; the queue is unchanged.',
        rows: [
            { key: 'events', title: 'New events to review', line: 'An email for every event sent for review.' },
            { key: 'organizers', title: 'New organizations to review', line: 'An email for every new organization sent for review.' },
            { key: 'claims', title: 'Claims and name changes', line: 'Ownership claims on organizations, and requests to change a name.' },
            { key: 'show_run_requests', title: 'Show run requests waiting', line: 'A request to join a show run that its owner has not answered in 3 days.' },
        ],
    },
];

const ALWAYS_ON = [
    { title: 'Your account', line: 'Sign-in codes, email checks, passkey changes and requests about your data.' },
    { title: 'Your listings', line: 'Review results and questions from EI, name change and ownership claim results, and events moved between organizations.' },
    { title: 'Invitations', line: 'Invitations to curate a community.' },
];

const loading = ref(true);
const loadError = ref(false);
const settings = reactive({});
const saving = reactive({});
const errors = reactive({});

const visibleSections = computed(() => SECTIONS
    .map((section) => ({ ...section, rows: section.rows.filter((row) => row.key in settings) }))
    .filter((section) => section.rows.length));

const savedEventsCount = ref(0);
const followedOrganizersCount = ref(0);
const clearing = ref(false);
const clearError = ref('');
const clearedMessage = ref('');
const likedEventsUrl = computed(() => `/users/${window.Laravel?.user?.id}/events`);

const load = async () => {
    loading.value = true;
    loadError.value = false;
    try {
        const [prefs, counts] = await Promise.all([
            axios.get('/api/account/notification-settings'),
            axios.get('/api/hub/notification-preferences/counts'),
        ]);
        Object.assign(settings, prefs.data.settings);
        savedEventsCount.value = counts.data.saved_events_count;
        followedOrganizersCount.value = counts.data.followed_organizers_count;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

// Saves at once; on failure the switch goes back and says why.
const toggle = async (key) => {
    if (saving[key]) return;
    const next = !settings[key];
    settings[key] = next;
    saving[key] = true;
    errors[key] = '';
    try {
        const { data } = await axios.patch('/api/account/notification-settings', { [key]: next });
        // Only this switch: a reply for another quick toggle may still be on its way.
        settings[key] = data.settings[key];
    } catch {
        settings[key] = !next;
        errors[key] = 'That did not save. Please try again.';
    } finally {
        saving[key] = false;
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
        clearedMessage.value = 'Emails turned off for everything you saved and follow.';
    } catch {
        clearError.value = 'Could not turn them off. Please try again.';
    } finally {
        clearing.value = false;
    }
};

onMounted(load);
</script>
