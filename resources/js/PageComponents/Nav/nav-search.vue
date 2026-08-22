<template>
    <div ref="wrapperRef" class="w-full inline-block relative min-w-[30rem] col-span-1 z-20 self-start">
        <!-- One persistent shell — no explicit height here at all. The tabs
             row and fields row below each carry their own transitions, and
             the shell just naturally reflows to fit them (ordinary block
             layout), so it "shrinks" as a side effect of its content
             changing rather than needing its own separately-coordinated
             height animation.

             `position: fixed` escapes the Blade grid entirely (needed so
             the expanded state can grow taller than the grid row's fixed
             8rem height) — that's also why it stays full viewport width:
             the white background/shadow below is the backdrop for the
             WHOLE top strip (including the space below the logo and
             profile, which the shell also needs to cover once it grows
             past their 8rem row). Only the interactive content inside is
             constrained to the grid's actual middle column — `margin-left`/
             `width` on .nav-search-content below are measured from the wrapper
             (which DOES sit correctly in the grid), since centering that
             content on the full viewport doesn't match the grid's middle
             column when the logo and profile aren't symmetric widths. That
             mismatch was easy to miss while the pill was wide enough to
             mostly fill the gap either way; at the current narrower width
             it reads as visibly off-center.

             Unpinned (an event show page), the shell is `position: absolute`
             instead of `relative` — still escaping the grid row's fixed
             8rem height (so expanding doesn't push the logo/profile down to
             re-center within a now-taller row, which is what a plain
             `relative` grid item would do), but anchored to THIS wrapper
             (already `position: relative` and sitting correctly in the
             grid) rather than the viewport, so it scrolls away with the
             page like an ordinary element instead of staying pinned. -->
        <div
            @click="checkClickPosition"
            class="nav-search-shell top-0 z-20"
            :class="[pinned ? 'fixed left-0 w-full' : 'absolute', { 'is-collapsed': collapsed }]"
            :style="pinned ? {} : { left: shellOverflowLeft, width: shellOverflowWidth }"
        >
            <div class="nav-search-content" :style="{ marginLeft: shellLeft, width: shellWidth }">
                <!-- Tabs row: a separate, secondary row that simply collapses
                     away (Airbnb does the same with its Homes/Experiences/
                     Services tabs) — the actual morph happens below, in the
                     search pill itself, not here. -->
                <div class="nav-search-tabs" :class="{ 'is-collapsed': collapsed }" :aria-hidden="collapsed">
                    <div class="w-full flex justify-center items-center gap-2">
                        <button
                            @click="activeTab = 'l'"
                            :class="[
                                'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                                activeTab === 'l' ? 'font-bold' : 'font-normal',
                                'hover:bg-gray-100'
                            ]">
                            <span class="block font-bold invisible h-0">Location</span>
                            <span class="block" :class="{ 'font-bold text-black': activeTab === 'l' }">Location</span>
                        </button>
                        <button
                            @click="activeTab = 'e'"
                            :class="[
                                'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                                activeTab === 'e' ? 'font-bold' : 'font-normal',
                                'hover:bg-gray-100'
                            ]">
                            <span class="block font-bold invisible h-0">Name</span>
                            <span class="block" :class="{ 'font-bold text-black': activeTab === 'e' }">Name</span>
                        </button>
                        <button
                            @click="activeTab = 'a'"
                            :class="[
                                'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                                activeTab === 'a' ? 'font-bold' : 'font-normal',
                                'hover:bg-gray-100'
                            ]">
                            <span class="block font-bold invisible h-0">At Home</span>
                            <span class="block" :class="{ 'font-bold text-black': activeTab === 'a' }">At Home</span>
                        </button>
                    </div>
                </div>

                <!-- Fields row: this is what actually morphs. SearchLocation
                     and SearchAtHome both stay mounted (v-show, not v-if)
                     across expand/collapse AND across tab switches — only
                     their own internal CSS (width, padding) transitions;
                     see location-search.vue / at-home-search.vue. Each is
                     visible whenever it's the active tab, collapsed or not,
                     so the collapsed pill always reflects whichever search
                     is actually current (a collapsed At Home search shows
                     its own picked type, not an empty Location bar). Name
                     has no compact-mode UI of its own, so collapsing while
                     on it falls back to showing Location instead (the
                     `activeTab !== 'a'` clause below) rather than nothing.
                     The filter button lives inside each search bar itself
                     now (to the right of Search), not as a separate element
                     beside it. -->
                <div class="nav-search-fields-row w-full flex items-center justify-center gap-8" :class="{ 'is-collapsed': collapsed }">
                    <SearchLocation
                        v-show="activeTab === 'l' || (collapsed && activeTab !== 'a')"
                        ref="locationSearch"
                        :compact="collapsed"
                        :has-active-filters="hasActiveFilters"
                        :show-filters="showLocationFilters"
                        @search="handleLocationSearch"
                        @close-search="collapse"
                        @dates-cleared="handleDatesClear"
                        @expand-requested="expand()"
                        @open-filters="showFilters = true"
                        @close-filters="showFilters = false"
                        @filters-changed="handleFilterUpdate"
                        :initial-start-date="state?.dates?.start"
                        :initial-end-date="state?.dates?.end"
                    />
                    <SearchEvent
                        v-if="!collapsed && activeTab === 'e'"
                        :has-active-filters="hasActiveFilters"
                        :show-filters="showFilters"
                        @open-filters="showFilters = true"
                        @close-filters="showFilters = false"
                        @filters-changed="handleFilterUpdate"
                    />
                    <SearchAtHome
                        v-show="activeTab === 'a'"
                        :compact="collapsed"
                        :has-active-filters="hasActiveFilters"
                        :show-filters="showAtHomeFilters"
                        @search="handleAtHomeSearch"
                        @close-search="collapse"
                        @dates-cleared="handleDatesClear"
                        @expand-requested="expand()"
                        @open-filters="showFilters = true"
                        @close-filters="showFilters = false"
                        @filters-changed="handleFilterUpdate"
                        :initial-start-date="state?.dates?.start"
                        :initial-end-date="state?.dates?.end"
                        :initial-remote-location="searchedRemoteLocation"
                    />
                </div>
            </div>
        </div>
        <!-- Sibling of the shell, not a child: a fixed child with a negative
             z-index only ranks below its OWN siblings inside the shell's
             stacking context — the shell (z-20) is still elevated above
             ordinary page content either way, so it would silently swallow
             every click on the rest of the page. As a sibling with a lower
             positive z-index it sits above the page but below the shell, and
             clicking it collapses the search like clicking outside a modal.

             Gated on pinnedOpen, not just !collapsed: at the top of the
             page, expanded is the resting state and collapse() is a no-op
             there (see its comment) — rendering the backdrop anyway would
             leave a click-eating layer over the entire page that visibly
             does nothing when clicked, since there'd be nothing left for
             it to actually dismiss. -->
        <div v-if="!collapsed && pinnedOpen" @click="collapse" class="fixed top-0 left-0 w-full h-full bg-[#00000026] z-10" />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import SearchLocation from './Components/location-search.vue';
import SearchEvent from './Components/events-search.vue';
import SearchAtHome from './Components/at-home-search.vue';
import SearchStore from '@/Stores/SearchStore.vue';
import MapStore from '@/Stores/MapStore.vue';

// Props definition
const props = defineProps({
  searchedEvents: {
    type: Object,
    default: () => ({})
  },
  maxPrice: {
    type: Number,
    default: undefined
  },
  // Server-resolved { id, name, slug } for the URL's remoteLocation slug
  // (see ListingsController::buildSearchFilters) — passed straight through
  // to SearchAtHome so its compact pill can show the real name on first
  // paint instead of a blank "Search" placeholder while its own client-side
  // fetch resolves. See at-home-search.vue for the rest of the reasoning.
  searchedRemoteLocation: {
    type: Object,
    default: null
  },
  // False on pages where the search isn't the main point (an event page, an
  // organizer page) and it should just scroll away with the rest of the
  // page like an ordinary element, instead of staying pinned to the
  // viewport while the page scrolls beneath it.
  pinned: {
    type: Boolean,
    default: true
  }
});

// Replace the state computed property with this simpler version
const state = computed(() => SearchStore.state);

// Layout state (expanded vs. collapsed) and content state (which tab shows
// when expanded) used to be conflated into one `search` ref (null | 'l' | 'e'),
// which made it impossible to remember the active tab across a collapse/
// re-expand cycle without a separate tracking variable. Split apart, the tab
// just naturally persists on its own.
//
// Starting expanded only makes sense on the homepage, where the search is
// the whole point of the page — everywhere else, including the search
// results page itself, it should load minimized, same as if the user had
// already scrolled past it, and only open on request.
const isHomePage = () => window.location.pathname === '/';

const collapsed = ref(!isHomePage());
// Defaults to Location, but a page loaded from an At Home search (whether
// via this nav's own At Home tab or the Filters panel's Remote Events
// toggle, which sets the same searchType param) should open back up on
// that same tab instead — otherwise expanding a collapsed At Home search
// results page shows the unrelated Location bar.
const activeTab = ref(new URLSearchParams(window.location.search).get('searchType') === 'atHome' ? 'a' : 'l');
const showFilters = ref(false);
const unsubscribe = ref(null);
const locationSearch = ref(null);

// The fixed shell's horizontal bounds are measured from the logo and
// profile elements on either side, not from this wrapper's own grid track.
// The Blade grid gives the logo and profile columns an EQUAL width at wide
// viewports (`grid-cols-[1fr_3fr_1fr]`), but their CONTENT isn't the same
// width — so a track that's centered is not the same as a gap that looks
// centered next to the actual logo text and profile icon; the wider the
// viewport, the more unused space each equal track has, and the more
// visible the mismatch. Measuring the actual DOM siblings (the logo div
// and the profile component's root, which Vue mounts in place of
// <vue-nav-profile> — both direct siblings of this component's own root
// in the Blade grid) matches what the eye is actually comparing against.
// Falls back to the wrapper's own track if a sibling isn't found for any
// reason. Only changes on layout/viewport width changes, not during the
// collapse animation, so measuring here (rather than using fixed known
// values) is safe.
const wrapperRef = ref(null);
const shellLeft = ref('0px');
const shellWidth = ref('100%');
// Unpinned, the shell is `position: absolute` — anchored to this wrapper (a
// single narrow grid column) purely so its TOP position scrolls away
// naturally with the page. But the shell's own white background/shadow is
// meant to cover the WHOLE top strip regardless of pinned state (see its
// own comment above) — left at the default `left-0 w-full`, that background
// would resolve against this narrow wrapper (its positioned ancestor), not
// the viewport, leaving the logo/profile areas uncovered once the search
// expands taller than the Blade nav's own fixed-height base strip. These
// overflow the shell back out to full viewport width while keeping its
// vertical anchoring to the wrapper intact.
const shellOverflowLeft = ref('0px');
const shellOverflowWidth = ref('100vw');

const updateShellBounds = () => {
    if (!wrapperRef.value) return;

    if (!props.pinned) {
        const wrapperRect = wrapperRef.value.getBoundingClientRect();
        shellOverflowLeft.value = `${-wrapperRect.left}px`;
        shellOverflowWidth.value = `${window.innerWidth}px`;
        return;
    }

    const prevRect = wrapperRef.value.previousElementSibling?.getBoundingClientRect();
    const nextRect = wrapperRef.value.nextElementSibling?.getBoundingClientRect();
    if (prevRect && nextRect) {
        shellLeft.value = `${prevRect.right}px`;
        shellWidth.value = `${nextRect.left - prevRect.right}px`;
        return;
    }
    const rect = wrapperRef.value.getBoundingClientRect();
    shellLeft.value = `${rect.left}px`;
    shellWidth.value = `${rect.width}px`;
};

// Expanding while scrolled grows the Blade nav spacer, which can trigger
// the browser's scroll anchoring to shift window.scrollY to compensate for
// the layout shift above the viewport — that shift fires a genuine 'scroll'
// event which would otherwise immediately re-collapse the bar right after
// the user asked to expand it. `overflow-anchor: none` on the spacer (see
// resources/views/nav/*.blade.php) suppresses this in Chrome/Firefox;
// this cooldown is the cross-browser backstop (Safari's support differs).
let ignoreScrollUntil = 0;

// Once the user explicitly opens the search while scrolled (as opposed to
// it opening because they scrolled back to the top of the homepage), it
// stays open through further scrolling — they have to click away, or scroll
// to the top and back down, before scroll position is allowed to collapse
// it again. Also drives whether the click-away backdrop renders at all: at
// the top of the homepage, expanded is just the resting state (nothing to
// dismiss), so the backdrop needs to stay out of the way rather than sit
// there silently swallowing every click on the page — a ref (not a plain
// variable) because the template's backdrop v-if needs to react to it.
const pinnedOpen = ref(false);

// tab is optional: while collapsed, only SearchLocation is ever visible
// (regardless of activeTab — see its v-show), so it's the only thing that
// can trigger an expand. Forcing activeTab to 'l' here would stomp on
// whichever tab was actually active before collapsing (e.g. loading
// straight onto an At Home results page, or having been on Name before a
// scroll-collapse) — omit it to just reveal whatever's already selected.
const expand = (tab) => {
    if (tab) activeTab.value = tab;
    collapsed.value = false;
    pinnedOpen.value = true;
    ignoreScrollUntil = Date.now() + 500;
};

const collapse = () => {
    pinnedOpen.value = false;
    // On the homepage, expanded is the resting state at the top of the page
    // — only scrolling away should be able to minimize it there; clicking
    // away, applying filters, etc. shouldn't force it shut while still at
    // the top. Everywhere else, including the search results page, minimized
    // is the resting state instead (see isHomePage), so an explicit collapse
    // should always go through, regardless of scroll position.
    if (window.scrollY <= EXPAND_AT && isHomePage()) {
        return;
    }
    collapsed.value = true;
};

// Named (not inline) specifically so onUnmounted's removeEventListener
// actually matches this same reference — an inline arrow function there
// would never remove anything (a different function object every render),
// leaking the listener on every unmount. expand() first: opening Filters
// while the nav is still collapsed (the results-page resting state — see
// isHomePage() above) rendered as a tiny floating box, not the intended
// full-size expanded search bar.
const handleOpenFilters = () => {
    expand();
    showFilters.value = true;
};

// The shell is `position: fixed`, so it doesn't push page content down on its
// own — the actual reflow comes from a CSS custom property this sets on
// <html>, which the Blade nav wrapper's spacer height reads
// (var(--nav-bar-height, ...); see resources/views/nav/*.blade.php). Fixed,
// known values (matching the shell's rendered height in each state) rather
// than measuring the DOM — simpler, and immune to reading a mid-animation size.
const NAV_EXPANDED_HEIGHT_REM = 19.95;
const NAV_COLLAPSED_HEIGHT_REM = 8;

const updateNavBarHeight = () => {
    const height = collapsed.value ? NAV_COLLAPSED_HEIGHT_REM : NAV_EXPANDED_HEIGHT_REM;
    document.documentElement.style.setProperty('--nav-bar-height', `${height}rem`);
};

watch(collapsed, updateNavBarHeight, { immediate: true });

// Filters now lives nested inside the currently-visible pill (see
// location-search.vue / events-search.vue) so its dropdown can match that
// pill's real width — closing it whenever the nav collapses (scrolling
// down, or any other trigger collapse() handles) keeps it from being left
// open and mispositioned against a pill that's about to shrink out from
// under it.
watch(collapsed, (isCollapsed) => {
    if (isCollapsed) {
        showFilters.value = false;
    }
});

// Remove the getUrlParams utility function and replace with computed property
const urlParams = computed(() => new URLSearchParams(window.location.search));

const updateUrlParams = (params) => {
    window.history.pushState({}, '', `${window.location.pathname}?${params.toString()}`);
};

// Computed properties
const isSearchPage = computed(() => {
    return window.location.pathname.includes('/search');
});

// Reflects the committed filter state from the store — the filter button
// updates once Apply is clicked, not live as the panel's own draft state
// changes (Filters is nested inside whichever pill is active now, not
// rendered here directly, so there's no local ref to read a live draft
// from).
// atHome isn't counted here (deliberately, not an oversight): with the
// Remote Events toggle removed from the Filters panel, atHome now just
// reflects which search tab is active rather than a discretionary filter,
// so it shouldn't make the filter button itself read as "active".
const hasActiveFilters = computed(() => {
    return state.value.filters.categories?.length > 0 ||
           state.value.filters.tags?.length > 0 ||
           state.value.filters.searchingByPrice === true;
});

// SearchLocation and SearchAtHome both stay mounted (v-show, not v-if — see
// the fields-row template) regardless of which tab is active, so passing
// the raw showFilters boolean straight through to either would mount a
// SECOND, hidden Filters instance alongside whichever one is genuinely
// visible — wasted duplicate category/tag fetches, and two components both
// able to react to state. Each is scoped to mirror its own component's
// v-show condition exactly, so only the pill that's actually on screen
// ever mounts its own Filters.
const showLocationFilters = computed(() => showFilters.value && (activeTab.value === 'l' || (collapsed.value && activeTab.value !== 'a')));
const showAtHomeFilters = computed(() => showFilters.value && activeTab.value === 'a');

// Scroll-position-driven, not one-way: past the collapse threshold it
// collapses to the compact pill; back near the top it re-expands to whichever
// tab was last open. Collapse/expand use different thresholds (hysteresis) so
// scrolling that hovers right at one fixed line doesn't flicker back and
// forth. rAF-throttled (not debounced) so the switch happens on the next
// frame as you scroll, not only after you stop scrolling. Reaching the top
// always clears the pin (see `pinnedOpen` above) — scrolling back down from
// there collapses normally again, same as a fresh page load.
const COLLAPSE_AT = 30;
const EXPAND_AT = 8;
let scrollTicking = false;

const handleScroll = () => {
    // Unpinned, the shell scrolls away with the page on its own — there's
    // no "shrink to stay out of the way while still pinned" case to handle,
    // so scroll position shouldn't drive collapse/expand here at all.
    if (!props.pinned) return;
    if (scrollTicking) return;
    scrollTicking = true;

    requestAnimationFrame(() => {
        if (Date.now() < ignoreScrollUntil) {
            scrollTicking = false;
            return;
        }
        // Auto-re-expanding at the top is only the right call on the
        // homepage — on every other page, including the search results
        // page, it should stay minimized until explicitly clicked open,
        // scroll position or not.
        if (window.scrollY <= EXPAND_AT && isHomePage()) {
            collapsed.value = false;
            pinnedOpen.value = false;
        } else if (window.scrollY > COLLAPSE_AT && !pinnedOpen.value) {
            collapsed.value = true;
        }
        // Between EXPAND_AT and COLLAPSE_AT: leave whatever it currently is.
        scrollTicking = false;
    });
};

const checkClickPosition = (event) => {
    if (event.clientY > 150) {
        collapse();
    }
};

const hideSearch = () => {
    collapse();
};

const handleFilterUpdate = async (filters) => {
    SearchStore.updateState({
        filters: filters
    });

    // Close the filters modal
    showFilters.value = false;

    // Close the search modal when filters are applied
    collapse();

    if (isSearchPage.value) {
        const params = urlParams.value;

        // Get current search type to detect mode changes
        const currentSearchType = params.get('searchType');
        const newSearchType = filters.atHome ? 'atHome' : 'inPerson';

        // Detect if we're switching between in-person and remote modes
        const isCurrentlyInPerson = currentSearchType === 'inPerson';
        const isCurrentlyRemote = currentSearchType === 'atHome';
        const isTogglingModes = (isCurrentlyInPerson && filters.atHome) ||
                               (isCurrentlyRemote && !filters.atHome);

        params.set('page', 1);

        // Handle categories
        if (filters.categories.length) {
            params.set('category', filters.categories.join(','));
        } else {
            params.delete('category');
        }

        // Handle tags
        if (filters.tags.length) {
            params.set('tag', filters.tags.join(','));
        } else {
            params.delete('tag');
        }

        // Handle price range
        const [minPrice, maxPrice] = filters.price;
        if (minPrice > 0) {
            params.set('price0', minPrice);
        } else {
            params.delete('price0');
            // Update searchingByPrice if both price params are being removed
            if (!params.has('price1')) {
                SearchStore.updateState({
                    filters: {
                        ...filters,
                        searchingByPrice: false
                    }
                });
            }
        }

        if (maxPrice < state.value.filters.maxPrice) {
            params.set('price1', maxPrice);
        } else {
            params.delete('price1');
            // Update searchingByPrice if both price params are being removed
            if (!params.has('price0')) {
                SearchStore.updateState({
                    filters: {
                        ...filters,
                        searchingByPrice: false
                    }
                });
            }
        }

        // Set searchType based on atHome filter
        params.set('searchType', newSearchType);

        // Remove location data when switching to atHome mode
        if (filters.atHome) {
            params.delete('city');
            params.delete('lat');
            params.delete('lng');
            params.delete('NElat');
            params.delete('NElng');
            params.delete('SWlat');
            params.delete('SWlng');
            params.delete('live');
        }

        // Do a full redirect when toggling between modes, otherwise update in place
        if (isTogglingModes) {
            window.location.href = `/index/search?${params.toString()}`;
        } else {
            updateUrlParams(params);
            await fetchResults(params.toString());
        }
    } else {
        const params = new URLSearchParams();

        if (filters.categories.length) {
            params.set('category', filters.categories.join(','));
        }
        if (filters.tags.length) {
            params.set('tag', filters.tags.join(','));
        }

        const [minPrice, maxPrice] = filters.price;
        if (minPrice > 0) {
            params.set('price0', minPrice);
        }
        if (maxPrice < state.value.filters.maxPrice) {
            params.set('price1', maxPrice);
        }

        // Handle searchType based on atHome
        params.set('searchType', filters.atHome ? 'atHome' : 'inPerson');

        window.location.href = `/index/search?${params.toString()}`;
    }
};

const handleLocationSearch = (searchData) => {

    // Use the updateState method to update the store with search data
    SearchStore.updateState({
        location: {
            city: searchData.location.city,
            lat: searchData.location.lat,
            lng: searchData.location.lng,
            live: searchData.location.live
        },
        dates: {
            start: searchData.dates.start,
            end: searchData.dates.end
        }
    });

    // Set up URL parameters
    const params = urlParams.value;
    const currentCity = params.get('city'); // Get current city here so it's in scope

    // Reset to page 1 for any new search
    params.set('page', 1);

    // A location search is never an At Home search — clear a remoteLocation
    // param left over from switching tabs (handleAtHomeSearch cleans up its
    // own city/lat/lng the same way in the other direction). Left in place,
    // this stale param survived into the URL indefinitely and got applied by
    // the backend as a filter on an in-person search, silently zeroing out
    // results (ListingsController::buildSearchFilters now also gates on
    // searchType === 'atHome' as a second line of defense, but the URL
    // shouldn't carry misleading params either).
    params.delete('remoteLocation');

    // Handle date parameters
    if (searchData.dates.start) {
        params.set('start', searchData.dates.start);
        params.set('end', searchData.dates.end || searchData.dates.start);
    } else {
        params.delete('start');
        params.delete('end');
    }

    // Check if we have location data
    const hasLocationData = searchData.location.city !== null;
    let isNewLocation = false;

    if (hasLocationData) {
        isNewLocation = currentCity !== searchData.location.city;

        // Clear map bounds if location has changed
        if (isNewLocation) {
            params.delete('NElat');
            params.delete('NElng');
            params.delete('SWlat');
            params.delete('SWlng');
            params.set('searchType', 'inPerson');
            params.set('live', 'false');
        }

        // Set location parameters
        params.set('city', searchData.location.city);

        // Explicitly convert to float before setting params with validation
        const parsedLat = searchData.location.lat ? parseFloat(searchData.location.lat) : null;
        const parsedLng = searchData.location.lng ? parseFloat(searchData.location.lng) : null;

        // Only set lat/lng if they're valid numbers (not NaN)
        if (parsedLat !== null && !isNaN(parsedLat)) {
            params.set('lat', parsedLat.toString());
        }
        if (parsedLng !== null && !isNaN(parsedLng)) {
            params.set('lng', parsedLng.toString());
        }

        // If we have location data, make sure atHome is false
        if (state.value.filters.atHome) {
            SearchStore.updateState({
                filters: {
                    ...state.value.filters,
                    atHome: false,
                    // Stale otherwise — harmless while atHome is false
                    // (active-filters.vue only reads it when atHome is
                    // true), but clearing it keeps the store honest.
                    remoteLocation: null
                }
            });
        }
    } else {
        // For date-only search, use 'inPerson' searchType
        params.set('searchType', 'inPerson');

        // Remove any previous location data if it exists
        params.delete('city');
        params.delete('lat');
        params.delete('lng');
        params.delete('NElat');
        params.delete('NElng');
        params.delete('SWlat');
        params.delete('SWlng');
        params.delete('live');
    }

    // Add price filter params if needed
    const [minPrice, maxPrice] = state.value.filters.price;
    if (minPrice > 0) {
        params.set('price0', minPrice);
    }
    if (maxPrice < state.value.maxPrice) {
        params.set('price1', maxPrice);
    }

    // Auto-save happens in location-search.vue's own handleSearch (before it
    // decides whether to emit here or redirect on its own — see that
    // component for why it can't live only in this handler).

    // Determine whether to redirect or update URL based on conditions
    if (hasLocationData && isNewLocation) {
        window.location.href = `/index/search?${params.toString()}`;
    } else {
        updateUrlParams(params);
        fetchResults(params.toString());
    }
};

const handleAtHomeSearch = (searchData) => {
    const params = urlParams.value;
    const currentRemoteLocation = params.get('remoteLocation');
    const isNewRemoteLocation = currentRemoteLocation !== searchData.remoteLocation;

    SearchStore.updateState({
        dates: {
            start: searchData.dates.start,
            end: searchData.dates.end
        },
        filters: {
            ...state.value.filters,
            atHome: true,
            // { slug, name } directly from the search bar's own already-
            // resolved selection (see at-home-search.vue's handleSearch) —
            // active-filters.vue reads this for the "Results filtered by"
            // summary. This path doesn't reload the page, so there's no
            // fresh onMounted() to resolve it the way a redirect would.
            remoteLocation: searchData.remoteLocation
                ? { slug: searchData.remoteLocation, name: searchData.remoteLocationName }
                : null
        }
    });

    params.set('page', 1);
    params.set('searchType', 'atHome');

    // A remote search has no city/coordinates — same cleanup handleLocationSearch
    // does in the other direction when switching to a location-based search.
    params.delete('city');
    params.delete('lat');
    params.delete('lng');
    params.delete('NElat');
    params.delete('NElng');
    params.delete('SWlat');
    params.delete('SWlng');
    params.delete('live');

    if (searchData.remoteLocation) {
        params.set('remoteLocation', searchData.remoteLocation);
    } else {
        params.delete('remoteLocation');
    }

    if (searchData.dates.start) {
        params.set('start', searchData.dates.start);
        params.set('end', searchData.dates.end || searchData.dates.start);
    } else {
        params.delete('start');
        params.delete('end');
    }

    const [minPrice, maxPrice] = state.value.filters.price;
    if (minPrice > 0) {
        params.set('price0', minPrice);
    }
    if (maxPrice < state.value.maxPrice) {
        params.set('price1', maxPrice);
    }

    // Auto-save happens in at-home-search.vue's own handleSearch — see the
    // identical note in handleLocationSearch for why.

    // Switching remote-location type (or landing on this type for the first
    // time from a non-atHome search) needs a full redirect for the same
    // reason a new city does in handleLocationSearch — otherwise stays in
    // place and re-fetches.
    if (isNewRemoteLocation) {
        window.location.href = `/index/search?${params.toString()}`;
    } else {
        updateUrlParams(params);
        fetchResults(params.toString());
    }
};

// Fetch results
const fetchResults = async (queryString) => {
    try {
        await SearchStore.fetchResults(queryString);
    } catch (error) {
        console.error('Error in fetchResults:', error);
    }
};

// Set up subscription to MapStore for map-based searches
const subscribeToMapStore = () => {
  const unsubscribeMap = MapStore.subscribe((mapState) => {
    // Validate that all boundary coordinates are numeric before proceeding
    const neLat = parseFloat(mapState.bounds.northEast.lat);
    const neLng = parseFloat(mapState.bounds.northEast.lng);
    const swLat = parseFloat(mapState.bounds.southWest.lat);
    const swLng = parseFloat(mapState.bounds.southWest.lng);
    const centerLat = parseFloat(mapState.bounds.center[0]);
    const centerLng = parseFloat(mapState.bounds.center[1]);

    // Check if any values are NaN - if so, skip this update
    if (isNaN(neLat) || isNaN(neLng) || isNaN(swLat) || isNaN(swLng) || isNaN(centerLat) || isNaN(centerLng)) {
      console.warn('Invalid map bounds received, skipping update:', mapState.bounds);
      return;
    }

    // Get current params to preserve other values
    const params = urlParams.value;

    // Update boundary coordinates with validated values
    params.set('NElat', neLat.toFixed(6));
    params.set('NElng', neLng.toFixed(6));
    params.set('SWlat', swLat.toFixed(6));
    params.set('SWlng', swLng.toFixed(6));
    params.set('live', 'true');
    params.set('searchType', 'inPerson');

    // Update center coordinates with validated values
    params.set('lat', centerLat.toString());
    params.set('lng', centerLng.toString());

    // Update URL without reload
    updateUrlParams(params);

    // Fetch new results with the updated parameters
    fetchResults(params.toString());
  });

  // Store unsubscribe function for cleanup
  unsubscribe.value = unsubscribeMap;
};

// Setup event listeners for UI interactions
const setupEventListeners = () => {
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('hide-search', hideSearch);
};

// Lifecycle hooks
onMounted(() => {
  SearchStore.initializeFromUrl(props.searchedEvents, props.maxPrice === undefined ? null : props.maxPrice);
  subscribeToMapStore();
  setupEventListeners();
  updateShellBounds();
  window.addEventListener('resize', updateShellBounds);
  // The logo's serif font can still be loading at mount, which would leave
  // this measurement using a fallback-font width until the next resize —
  // re-measure once webfonts have actually settled.
  document.fonts?.ready?.then(updateShellBounds);

  // Add listener for opening filters from anywhere in the app — expand()
  // first, not just showFilters.value = true: on a results page the nav
  // starts collapsed (see isHomePage() above), and the Filters panel opening
  // while still collapsed rendered as a tiny floating box overlapping the
  // page instead of properly sized within the expanded search bar.
  window.addEventListener('open-filters', handleOpenFilters);
});

// Cleanup on component unmount
onUnmounted(() => {
  // Only need to clean up MapStore subscription
  if (unsubscribe.value) {
    unsubscribe.value();
  }

  // Remove event listeners
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('hide-search', hideSearch);
  window.removeEventListener('resize', updateShellBounds);
  window.removeEventListener('open-filters', handleOpenFilters);
});

// Add after handleLocationSearch
const handleDatesClear = () => {
    // Update the store with null dates
    SearchStore.updateState({
        dates: {
            start: null,
            end: null
        }
    });

    // Update URL parameters
    const params = urlParams.value;
    params.delete('start');
    params.delete('end');

    // If we're on a search page, update the search results
    if (isSearchPage.value) {
        updateUrlParams(params);
        fetchResults(params.toString());
    }

    // Close the search popup after clearing dates
    collapse();
};
</script>

<style scoped>
/* No drop shadow on the shell itself — that lives on the search pill/bar
   instead (see location-search.vue / events-search.vue), always visible
   rather than tied to expand state. Separation from the page below comes
   from a thin 1px bottom line instead (via ::after, so its color isn't
   tied to the background gradient) plus the subtle gradient itself. The
   Blade header row (index-desktop.blade.php etc.) used to carry its own
   border-bottom for this same line when the shell was collapsed and
   sitting inside that row's 8rem band — removed there so the two don't
   double up. */
.nav-search-shell {
    background: linear-gradient(180deg, #ffffff 39.9%, #f8f8f8 100%);
}
.nav-search-shell::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 2px;
    /* #dddddd against this shell's own #f8f8f8 gradient floor (and the page's
       similarly near-white background right below it) is barely 27/255 per
       channel apart — close enough to read as invisible in the expanded
       state specifically (confirmed live: same rule, same z-index, just
       hard to see against more of that light background at the taller
       height). Darkened for reliable contrast in both states rather than
       relying on z-index/stacking, which testing showed wasn't the cause. */
    background-color: #ebebeb;
    z-index: -1;
}

/* A separate, secondary row that simply collapses away (Airbnb does the
   same with its Homes/Experiences/Services tabs) — the real morph is in the
   search pill itself, in location-search.vue. Fixed max-height (not
   measured) so this is a plain, deterministic CSS transition; the shell
   above has no height of its own, so it just reflows to fit as this
   collapses, no separate coordination needed. */
.nav-search-tabs {
    overflow: hidden;
    max-height: 8.5rem;
    opacity: 1;
    padding-top: 1.8rem;
    transition: max-height 320ms cubic-bezier(0.2, 0.8, 0.2, 1), opacity 200ms ease, padding-top 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.nav-search-tabs.is-collapsed {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
}

.nav-search-fields-row {
    padding: 3.4rem 0 4.4rem;
    transition: padding 320ms cubic-bezier(0.2, 0.8, 0.2, 1), min-height 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
/* The Blade header row is a fixed 8rem band with the logo vertically
   centered in it (`items-center` on the grid). A collapsed pill that's
   genuinely shorter than 8rem still needs to sit centered within that same
   8rem band — not top-aligned with minimal padding — or it visibly floats
   above where the logo sits instead of sitting level with it. */
.nav-search-fields-row.is-collapsed {
    padding: 0;
    min-height: 8rem;
}

@media (prefers-reduced-motion: reduce) {
    .nav-search-tabs,
    .nav-search-fields-row {
        transition: none;
    }
}
</style>
