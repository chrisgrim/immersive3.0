<template>
    <!-- Sizes/widths below are measured directly off Airbnb's live
         /users/profile page at a 1512px viewport (not eyeballed): 572px
         sidebar, 129px sidebar heading inset, 32px/600 headings, 104px
         avatar, 22px/600 stat numbers, 10px/500 stat labels, stats stacked
         vertically with dividers rather than side-by-side.

         The "Liked events"/"Saved searches" tabs' two-pane layout (list +
         detail/map/editor) is the old standalone Dashboard's own layout,
         relocated here — the sidebar slot itself SWAPS to show that tab's
         list (with its own back arrow) in place of the flat nav, exactly
         like Dashboard's sidebar swapped from its tab-picker to a list.
         Keeping the flat nav permanently visible alongside a separate list
         pane would make this three panes at once instead of two — tried
         that, it read as three windows, not one page with two states. -->
    <div class="relative text-1xl font-normal w-full h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 md:flex h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row w-full max-w-screen-4xl">
                <!-- Navigation Sidebar — same shell as Account Settings. Its
                     content is the flat nav on About me, or the current
                     tab's list (with a back arrow to return to the nav) on
                     Liked events/Saved searches. Same fixed width either way
                     so switching tabs doesn't jump the layout. -->
                <div v-if="isOwner" class="flex-shrink-0 overflow-y-auto w-full md:w-[572px] md:block">
                    <div v-if="currentTab === 'about'" class="px-6 md:px-[129px] pt-16 md:pt-20">
                        <h1 class="text-5xl font-semibold mb-10">Profile</h1>
                        <NavSidebar :current-tab="currentTab" @navigate="handleNavigation" />
                    </div>
                    <template v-else>
                        <div class="relative bg-white px-6 md:px-16 pt-10 md:pt-16 pb-8 md:pb-12">
                            <div class="relative flex items-center justify-center">
                                <button
                                    type="button"
                                    aria-label="Back"
                                    @click="handleBack"
                                    class="absolute left-0 inline-flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 12H5"/>
                                        <path d="M12 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                <h2 v-if="!selectedEvent" class="text-3xl font-semibold line-clamp-1 text-center">{{ headerTitle }}</h2>
                                <!-- Visually hidden — the detail view is deliberately back-arrow-only
                                     (no visible title), but screen readers still need a heading. -->
                                <h2 v-else class="sr-only">{{ selectedEvent.name }}</h2>
                            </div>
                        </div>

                        <!-- Mobile only (see the content column's own hidden-until-lg editor
                             below) — two mounted instances of the same editor (this one +
                             the desktop one below) share the SAME draft/dirty/saving/error
                             state (owned here, not inside the editor component) so a
                             mid-edit window resize can't desync them. -->
                        <SavedSearchesEdit
                            v-if="currentTab === 'search-preferences' && selectedSearch"
                            class="lg:hidden"
                            :draft="editorDraft"
                            :dirty="isEditorDirty"
                            :saving="editorSaving"
                            :error="editorError"
                            @update:draft="editorDraft = $event"
                            @save="saveEditor"
                        />
                        <!-- Hidden (not just its child) below `lg` once a search is
                             selected — a display:none child still leaves this wrapper's
                             OWN padding rendered as visible dead space, since padding
                             belongs to the box regardless of whether its content is hidden. -->
                        <div
                            class="px-6 md:px-16 pb-20 md:pb-40"
                            :class="{ 'hidden lg:block': currentTab === 'search-preferences' && selectedSearch }"
                        >
                            <Events
                                v-if="currentTab === 'events'"
                                :events="events"
                                :loading="eventsLoading"
                                :selected-event="selectedEvent"
                                @paginate="fetchEvents"
                                @remove-requested="handleEventRemoveRequested"
                                @select="selectEvent($event)"
                                @notify-updated="selectedEvent.notifyUpdates = $event"
                            />
                            <SavedSearches
                                v-else-if="currentTab === 'search-preferences'"
                                :searches="searches"
                                :loading="searchesLoading"
                                :selected-search="selectedSearch"
                                @select="selectSearch($event)"
                                @pin-toggled="handleSearchPinToggled"
                                @remove-requested="handleSearchRemoveRequested"
                            />
                        </div>
                    </template>
                </div>

                <!-- Main Content Column. Fixed px-[128px] padding (not a
                     centered max-width) — the same real bug fixed on
                     Account Settings applies here: a pure mx-auto approach
                     collapses to zero gutter once the viewport is too
                     narrow for the max-width to bind. Airbnb's own profile
                     page uses this exact fixed-padding pattern, not
                     centering. -->
                <div class="flex-1 flex-col h-full w-full md:w-auto flex" :class="{ 'border-l border-neutral-200': isOwner }">
                    <!-- About me: single card, single pane (unchanged from before this tab was added) -->
                    <div v-if="currentTab === 'about'" class="flex-1 md:overflow-y-auto">
                        <div class="w-full pt-20 md:pt-20 md:pb-40">
                            <div class="px-6 py-8 md:px-[128px] max-w-[940px]">
                                <AboutMe :user="user" :is-owner="isOwner" />
                            </div>
                        </div>
                    </div>

                    <!-- Liked events/Saved searches' detail/map/editor pane — desktop
                         only (`lg` and up); mobile gets its full-screen equivalent from
                         Events/SavedSearchesEdit's own list pane in the sidebar instead. -->
                    <div v-else class="hidden lg:block flex-1 h-full p-20">
                        <!-- -m-20 breaks out of this pane's own p-20 (the map and
                             SavedSearchesEdit both still want that — see the footer's
                             own lg:-mx-20 lg:-mb-20 — so it can't just be removed from
                             the shared wrapper), then reapplies the EXACT same top/side
                             offset from the nav that the About me tab uses
                             (pt-20 + px-6/md:px-[128px]), so this card sits at the same
                             position on screen regardless of which tab is open. -->
                        <div
                            v-if="currentTab === 'events' && selectedEvent && !selectedEvent.hasLocation"
                            class="-m-20 pt-20 md:pt-20 md:pb-40"
                        >
                        <div class="px-6 py-8 md:px-[128px] max-w-[940px]">
                            <!-- Same card language as the profile's own About me card
                                 (border-neutral-200 + rounded-3xl + p-10, sections
                                 separated by border-t pt-8 mt-8, not divide-y) — compact
                                 icon+heading+description rows, not a big hero image. How
                                 you attend (remote-location type) and what kind of
                                 experience it is (category) are two facts, not a photo
                                 spread; a small square thumbnail is plenty. -->
                            <div v-if="selectedEvent.category || selectedEvent.remotelocations?.length" class="border border-neutral-200 rounded-3xl p-10">
                                <div v-if="selectedEvent.remotelocations?.length" class="flex items-start gap-6" :class="{ 'mb-8': selectedEvent.category }">
                                    <div class="w-14 h-14 rounded-2xl bg-neutral-100 flex items-center justify-center flex-shrink-0">
                                        <component :is="RiGlobalLine" class="w-7 h-7 text-neutral-600" />
                                    </div>
                                    <div>
                                        <p class="text-2xl font-semibold mb-1">{{ selectedEvent.remotelocations.map((r) => r.name).join(', ') }}</p>
                                        <p class="text-lg text-neutral-500">How you'll attend this event.</p>
                                    </div>
                                </div>
                                <div
                                    v-if="selectedEvent.category"
                                    class="flex items-start gap-6"
                                    :class="{ 'border-t border-neutral-200 pt-8': selectedEvent.remotelocations?.length }"
                                >
                                    <div class="w-14 h-14 rounded-2xl overflow-hidden bg-neutral-100 flex-shrink-0">
                                        <img
                                            v-if="selectedEvent.category.thumbImagePath"
                                            class="w-full h-full object-cover"
                                            :src="`${imageUrl}${selectedEvent.category.thumbImagePath}`"
                                            :alt="selectedEvent.category.name"
                                        >
                                    </div>
                                    <div>
                                        <p class="text-2xl font-semibold mb-1">{{ selectedEvent.category.name }}</p>
                                        <p v-if="selectedEvent.category.description" class="text-lg text-neutral-500">{{ selectedEvent.category.description }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="border border-neutral-200 rounded-3xl p-10 flex flex-col items-center justify-center text-center">
                                <component :is="RiGlobalLine" class="w-10 h-10 mb-4 text-neutral-500" />
                                <p class="text-2xl font-semibold">This is a remote event</p>
                            </div>
                        </div>
                        </div>
                        <hub-events-map
                            v-else-if="currentTab === 'events'"
                            :events="selectedEvent ? [selectedEvent] : events.data"
                            @select="handleMapSelect"
                        />
                        <template v-else-if="currentTab === 'search-preferences'">
                            <SavedSearchesEdit
                                v-if="selectedSearch"
                                :draft="editorDraft"
                                :dirty="isEditorDirty"
                                :saving="editorSaving"
                                :error="editorError"
                                @update:draft="editorDraft = $event"
                                @save="saveEditor"
                            />
                            <div v-else class="w-full h-full rounded-3xl bg-neutral-100 flex items-center justify-center text-center px-12">
                                <p class="text-2xl font-semibold text-neutral-500">Select a saved search to edit its preferences</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <toast-notifications
            v-model:show="toastVisible"
            :message="toastMessage"
            :duration="5000"
            action-label="Undo"
            @action="undoRemoval"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { RiGlobalLine } from '@remixicon/vue';
import NavSidebar from './Pages/navSidebar.vue';
import AboutMe from './Pages/AboutMe.vue';
import Events from './Pages/Events.vue';
import SavedSearches from './Pages/SavedSearches.vue';
import SavedSearchesEdit from './Pages/SavedSearchesEdit.vue';
import HubEventsMap from './Components/hub-events-map.vue';
import ToastNotifications from '@/GlobalComponents/toast-notifications.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    isOwner: {
        type: Boolean,
        default: false,
    },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;

// Own-profile-only tabs — matches the route's own where('tab', 'events|search-preferences').
// 'about' isn't in here: it's the default, represented by the ABSENCE of a
// tab segment (bare /users/{id}), same as bare /account-settings defaults
// to personal-info.
const TAB_SLUGS = ['events', 'search-preferences'];

const TAB_LABELS = {
    events: 'Liked Events',
    'search-preferences': 'Saved Search Preferences',
};

const basePath = computed(() => `/users/${props.user.id}`);

// Reads the active tab from the URL path rather than client-only state, so a
// hard refresh or a fresh visit to any of these URLs lands on the right tab
// — the server returns the same shell for all of them (see routes/web.php),
// this is what actually picks the tab.
const explicitTabFromPath = () => {
    const slug = window.location.pathname.match(/^\/users\/[^/]+\/([^/]+)/)?.[1];

    return TAB_SLUGS.includes(slug) ? slug : null;
};

// TAB_SLUGS are own-profile-only (see its own comment above) — the sidebar
// that links to them is already isOwner-gated, but the route itself ignores
// ownership server-side (ProfilesController::show() renders the same shell
// for any viewer), so a non-owner reaching one of these URLs directly
// (typed, bookmarked, or via browser back/forward to a URL they visited
// while they WERE the owner) must still fall back to 'about' rather than
// rendering another user's Liked Events/Saved Searches panes populated from
// the VIEWER's own /api/hub/* data with no owner-scoped access check at all.
const resolveTab = () => (props.isOwner ? (explicitTabFromPath() ?? 'about') : 'about');

// The third path segment on /users/{id}/events/{slug} — a specific saved
// event's own URL. Only meaningful when the tab itself is 'events'.
const explicitEventSlugFromPath = () => window.location.pathname.match(/^\/users\/[^/]+\/events\/([^/]+)/)?.[1] ?? null;

// Same idea for /users/{id}/search-preferences/{id} — a saved search's own
// numeric id, not a slug (saved searches don't have one).
const explicitSearchIdFromPath = () => window.location.pathname.match(/^\/users\/[^/]+\/search-preferences\/([^/]+)/)?.[1] ?? null;

const currentTab = ref(resolveTab());

const headerTitle = computed(() => TAB_LABELS[currentTab.value]);

// The event a user has drilled into within the Liked Events tab (list shows
// its detail, map zooms to just that pin) — lives here, not in Events.vue,
// since both the list and the map need to agree on it at once.
const selectedEvent = ref(null);

// Same idea for Saved Searches — both the list (which shows which card is
// selected) and the right-pane editor need to agree on this at once.
const selectedSearch = ref(null);

// The editor's draft/dirty/saving/error state lives here, not inside
// SavedSearchesEdit.vue — that component mounts twice (mobile + desktop,
// see the template), and a single source of truth is what keeps them from
// desyncing on a mid-edit window resize.
const editorDraft = ref(null);
const editorOriginal = ref(null); // JSON snapshot of editorDraft as last loaded/saved
const editorSaving = ref(false);
const editorError = ref(null);

const isEditorDirty = computed(() => (
    editorDraft.value !== null && JSON.stringify(editorDraft.value) !== editorOriginal.value
));

const resetEditor = (search) => {
    editorDraft.value = { name: search.name, criteria: JSON.parse(JSON.stringify(search.criteria)) };
    editorOriginal.value = JSON.stringify(editorDraft.value);
    editorError.value = null;
};

const clearEditor = () => {
    editorDraft.value = null;
    editorOriginal.value = null;
    editorError.value = null;
};

// Matches the template's own `lg:` breakpoint for the list/detail split
// below — the one place handleBack needs to know whether the list is
// currently visible alongside the editor, or hidden behind it.
const DESKTOP_LAYOUT_BREAKPOINT = 1024;

// Drilled into an event's own detail steps back to the list first (a
// genuinely deeper page, worth its own stop) on every viewport. Search
// Preferences only has that same kind of middle layer below `lg` — at `lg`
// and up the list stays visible beside the editor the whole time, so
// there's nothing hidden to reveal and back goes straight to About me;
// below `lg`, selecting a search hides the list entirely, making it a real
// screen worth backing out of first, same as Events' own selectedEvent step.
const handleBack = () => {
    if (selectedEvent.value) {
        selectedEvent.value = null;
        window.history.pushState({ tab: currentTab.value }, '', `${basePath.value}/${currentTab.value}`);
        return;
    }

    if (selectedSearch.value && isEditorDirty.value && !window.confirm(`Discard unsaved changes to "${selectedSearch.value.name}"?`)) {
        return;
    }

    if (selectedSearch.value && window.innerWidth < DESKTOP_LAYOUT_BREAKPOINT) {
        selectedSearch.value = null;
        clearEditor();
        window.history.pushState({ tab: 'search-preferences' }, '', `${basePath.value}/search-preferences`);
        return;
    }

    handleNavigation('about');
};

// Clicking a pin on the map drills into that event's detail, same as
// clicking its card in the list — the map only ever shows events already in
// `events.data`, so the lookup is always found when currentTab === 'events'.
const handleMapSelect = (eventId) => {
    const match = events.value.data.find((item) => item.id === eventId);
    if (match) selectEvent(match);
};

// Leaving a tab entirely shouldn't leave a stale selection behind for the next visit.
watch(currentTab, (tab) => {
    if (tab !== 'events') {
        selectedEvent.value = null;
    }
    if (tab !== 'search-preferences') {
        selectedSearch.value = null;
        clearEditor();
    }
});

// Liked Events' data lives here (not inside Events.vue) because both the
// list and the map need the same data at the same time — they're two views
// onto one tab, not two independent components.
const events = ref({ data: [], total: 0, per_page: 24, current_page: 1, last_page: 1 });
const eventsLoading = ref(true);

const fetchEvents = async (page = 1) => {
    eventsLoading.value = true;

    try {
        const response = await axios.get('/api/hub/events', { params: { page } });
        events.value = response.data.events;
    } catch (error) {
        console.error('[profile-events] failed to load', error);
    } finally {
        eventsLoading.value = false;
    }
};

// Saved searches — same reasoning as `events` above: the list and the editor
// both need the same data and selection at once.
const searches = ref([]);
const searchesLoading = ref(true);

const fetchSearches = async () => {
    searchesLoading.value = true;

    try {
        const response = await axios.get('/api/hub/saved-searches');
        searches.value = response.data.searches;
    } catch (error) {
        console.error('[profile-saved-searches] failed to load', error);
    } finally {
        searchesLoading.value = false;
    }
};

// Drilling into an event gets its own URL (/users/{id}/events/{slug}) — same
// pushState-without-reload idiom as setTab() below, so it's real, shareable,
// and refresh-safe like the tab URLs already are.
const selectEvent = (event, { pushState = true } = {}) => {
    selectedEvent.value = event;

    if (pushState) {
        window.history.pushState({ tab: 'events', eventSlug: event.slug }, '', `${basePath.value}/events/${event.slug}`);
    }
};

// Resolves a direct/cold visit to /users/{id}/events/{slug}, and browser
// back/forward onto one — the event might already be loaded (cheap, just
// select it), or this might be the very first thing fetched on this page
// view, before the paginated list has loaded at all.
const selectEventBySlug = async (slug) => {
    const cached = events.value.data.find((item) => item.slug === slug);
    if (cached) {
        selectEvent(cached, { pushState: false });
        return;
    }

    try {
        const { data } = await axios.get(`/api/hub/events/${slug}`);
        selectEvent(data.event, { pushState: false });
    } catch (error) {
        console.error('[profile-events] failed to load event', error);
        // Not a saved event (bad/stale link) — fall back to the list rather
        // than leaving the page stuck on an unresolvable URL.
        handleNavigation('events');
    }
};

// Same deep-link idiom as selectEvent, for a saved search's id — EXCEPT the
// URL update is a replaceState, not a pushState. Unlike drilling into an
// event's own detail (a genuinely deeper page worth stepping back through
// one at a time), the list+editor here is one screen that just shows
// whichever search you last touched — clicking between five different
// saved searches shouldn't leave five stacked history entries.
const selectSearch = (search, { updateUrl = true } = {}) => {
    if (updateUrl && selectedSearch.value && selectedSearch.value.id !== search.id && isEditorDirty.value) {
        if (!window.confirm(`Discard unsaved changes to "${selectedSearch.value.name}"?`)) return;
    }

    selectedSearch.value = search;
    resetEditor(search);

    if (updateUrl) {
        window.history.replaceState({ tab: 'search-preferences', searchId: search.id }, '', `${basePath.value}/search-preferences/${search.id}`);
    }
};

// Unlike selectEventBySlug, there's no separate by-id endpoint to fall back
// to — the full (capped-at-50) list is already what fetchSearches() loads.
// A stale/bad id falls back to the first search, same as landing on the
// bare tab URL.
const selectSearchById = (id) => {
    const match = searches.value.find((item) => String(item.id) === String(id));

    if (match) {
        selectSearch(match, { updateUrl: false });
    } else if (searches.value.length) {
        selectSearch(searches.value[0]);
    }
};

// Resolves a possibly-absent id from the URL to a selection — shared by the
// tab-entry watcher (first visit / re-visit within the SPA session) and
// onPopState (browser back/forward). No id (the normal way in, via the nav)
// falls straight to the first search.
const selectSearchFromId = (id) => {
    if (id) {
        selectSearchById(id);
        return;
    }

    if (searches.value.length) {
        selectSearch(searches.value[0]);
    } else {
        selectedSearch.value = null;
    }
};

// Removing an item (an event or a saved search) is deferred, not immediate —
// it disappears from its list right away, but the actual DELETE only fires 5
// seconds later (commitPendingRemoval), giving the toast's Undo a real
// window to cancel it before anything happens server-side. Only one removal
// is ever pending at a time, regardless of type.
const toastVisible = ref(false);
const toastMessage = ref('');
let pendingRemoval = null; // { type: 'event' | 'search', item, index, timeoutId }

const showToast = (message) => {
    toastVisible.value = false; // force a show=false->true transition even if already showing, so the auto-dismiss timer restarts
    nextTick(() => {
        toastMessage.value = message;
        toastVisible.value = true;
    });
};

const commitPendingRemoval = async () => {
    const removal = pendingRemoval;
    if (!removal) return;
    pendingRemoval = null;

    if (removal.type === 'event') {
        try {
            await axios.delete(`/api/events/${removal.item.slug}/favorite`);

            // Removing the only item on a later page would otherwise leave a
            // dead empty page — step back a page now that it's actually gone.
            if (events.value.data.length === 0 && events.value.current_page > 1) {
                fetchEvents(events.value.current_page - 1);
            }
        } catch (error) {
            console.error('[profile-events] failed to remove favorite', error);
            // Put it back — a failed delete shouldn't silently lose it from view.
            events.value.data.splice(Math.min(removal.index, events.value.data.length), 0, removal.item);
            events.value.total += 1;
        }
        return;
    }

    try {
        await axios.delete(`/api/hub/saved-searches/${removal.item.id}`);
    } catch (error) {
        console.error('[profile-saved-searches] failed to remove', error);
        searches.value.splice(Math.min(removal.index, searches.value.length), 0, removal.item);
    }
};

const handleEventRemoveRequested = (event) => {
    if (pendingRemoval) {
        clearTimeout(pendingRemoval.timeoutId);
        commitPendingRemoval();
    }

    const index = events.value.data.findIndex((item) => item.id === event.id);
    if (index === -1) return;

    events.value.data.splice(index, 1);
    events.value.total = Math.max(0, events.value.total - 1);

    const timeoutId = setTimeout(commitPendingRemoval, 5000);
    pendingRemoval = { type: 'event', item: event, index, timeoutId };

    showToast(`Removed "${event.name}"`);
};

const handleSearchRemoveRequested = (search) => {
    if (pendingRemoval) {
        clearTimeout(pendingRemoval.timeoutId);
        commitPendingRemoval();
    }

    const index = searches.value.findIndex((item) => item.id === search.id);
    if (index === -1) return;

    searches.value.splice(index, 1);

    if (selectedSearch.value?.id === search.id) {
        selectedSearch.value = null;
        clearEditor();
        window.history.pushState({ tab: 'search-preferences' }, '', `${basePath.value}/search-preferences`);
    }

    const timeoutId = setTimeout(commitPendingRemoval, 5000);
    pendingRemoval = { type: 'search', item: search, index, timeoutId };

    showToast(`Removed "${search.name}"`);
};

// Saves the currently-selected search's draft (editorDraft) via the
// dedicated update endpoint — never the auto-save POST (see
// UpdateSavedSearchAction for why). Editing always pins the row server-side,
// so the response is re-sorted in alongside handleSearchPinToggled's own
// pinned-first resort.
const saveEditor = async () => {
    if (!selectedSearch.value || editorSaving.value) return;

    editorSaving.value = true;
    editorError.value = null;

    try {
        const { data } = await axios.patch(`/api/hub/saved-searches/${selectedSearch.value.id}`, {
            name: editorDraft.value.name,
            criteria: editorDraft.value.criteria,
        });
        const updated = data.search;

        const index = searches.value.findIndex((item) => item.id === updated.id);
        if (index !== -1) searches.value.splice(index, 1, updated);
        searches.value.sort((a, b) => (b.pinned === a.pinned ? 0 : b.pinned ? 1 : -1));

        selectedSearch.value = updated;
        resetEditor(updated);
    } catch (error) {
        if (error.response?.status === 409) {
            editorError.value = error.response.data.message;
        } else if (error.response?.status === 422) {
            editorError.value = Object.values(error.response.data.errors || {}).flat()[0] || 'Please check your changes and try again.';
        } else {
            editorError.value = "Something went wrong saving this search — try again.";
            console.error('[profile-saved-searches] failed to save', error);
        }
    } finally {
        editorSaving.value = false;
    }
};

const handleSearchPinToggled = async (search) => {
    try {
        const { data } = await axios.patch(`/api/hub/saved-searches/${search.id}/pin`);
        search.pinned = data.search.pinned;
        // Pinned-first, same ordering the backend already returns —
        // Array.prototype.sort is stable, so relative order within each
        // group (pinned / unpinned) is preserved.
        searches.value.sort((a, b) => (b.pinned === a.pinned ? 0 : b.pinned ? 1 : -1));
    } catch (error) {
        console.error('[profile-saved-searches] failed to toggle pin', error);
    }
};

const undoRemoval = () => {
    if (!pendingRemoval) return;

    clearTimeout(pendingRemoval.timeoutId);
    const { type, item, index } = pendingRemoval;
    pendingRemoval = null;

    if (type === 'event') {
        events.value.data.splice(Math.min(index, events.value.data.length), 0, item);
        events.value.total += 1;
    } else {
        searches.value.splice(Math.min(index, searches.value.length), 0, item);
    }

    toastVisible.value = false;
};

// Fetches on first entering the tab, whether that's a nav click or a cold/
// direct visit to /users/{id}/events — doesn't refetch on repeat visits. A
// slug on that same cold visit also resolves here, once; later slug changes
// (browser back/forward) go through onPopState instead.
let eventsFetched = false;
watch(currentTab, (tab) => {
    if (tab === 'events' && !eventsFetched) {
        eventsFetched = true;
        fetchEvents();

        const slug = explicitEventSlugFromPath();
        if (slug) selectEventBySlug(slug);
    }
}, { immediate: true });

// Same idea for Saved Searches — awaits the fetch before resolving an id
// from the path, since there's no separate by-id endpoint to fall back to
// if the list hasn't loaded yet. Landing on the bare tab URL auto-selects
// the first search rather than resting on the empty "select one" placeholder;
// selectSearch's replaceState means this doesn't add its own history entry.
let searchesFetched = false;
watch(currentTab, async (tab) => {
    if (tab !== 'search-preferences') return;

    if (!searchesFetched) {
        searchesFetched = true;
        await fetchSearches();
    }

    if (selectedSearch.value) return;

    selectSearchFromId(explicitSearchIdFromPath());
}, { immediate: true });

// Updates the URL to a real path without a page load — so tab switching
// stays exactly as fast as client state, while the URL itself is real,
// shareable, and refresh-safe.
const setTab = (tab, { pushState = true } = {}) => {
    if (!TAB_SLUGS.includes(tab)) return;

    currentTab.value = tab;

    if (pushState) {
        window.history.pushState({ tab }, '', `${basePath.value}/${tab}`);
    }
};

const handleNavigation = (tab) => {
    if (!tab || tab === 'about') {
        currentTab.value = 'about';
        window.history.pushState({ tab: 'about' }, '', basePath.value);
        return;
    }

    setTab(tab);
};

// Without this, pushState would change the address bar but the browser's
// back/forward buttons wouldn't change which tab is showing. Also resolves
// an event slug / saved-search id both ways — landing back on one, or
// stepping back off one to the bare list.
const onPopState = () => {
    currentTab.value = resolveTab();

    if (currentTab.value === 'events') {
        const slug = explicitEventSlugFromPath();

        if (slug) {
            selectEventBySlug(slug);
        } else {
            selectedEvent.value = null;
        }
        return;
    }

    if (currentTab.value === 'search-preferences') {
        selectSearchFromId(explicitSearchIdFromPath());
    }
};

onMounted(() => window.addEventListener('popstate', onPopState));
onUnmounted(() => window.removeEventListener('popstate', onPopState));
</script>
