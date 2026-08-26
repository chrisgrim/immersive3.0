<template>
    <!-- font-normal, not font-medium — every element below sets its own
         weight explicitly (matching Airbnb's actual 400/500/600 mix
         measured off their live page), so the base must be regular or
         plain description/value text inherits a bolder weight than
         Airbnb's real rgb(115,115,115) grey 400-weight text. -->
    <div class="relative text-1xl font-normal w-full h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 md:flex h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row w-full max-w-screen-4xl">
                <!-- Navigation Sidebar with own scroll. Width/padding/heading
                     size below are measured directly off Airbnb's live
                     account-settings page (475px-wide <aside>, 26px/600
                     heading) at a 1512px viewport — not eyeballed. -->
                <div
                    class="flex-shrink-0 overflow-y-auto w-full md:w-[475px] md:block"
                    :class="{ 'hidden': currentSection }">
                    <!-- px-[67px] is exact: Airbnb's sidebar heading and nav
                         row both sit 67px in from the aside's edge (341px
                         row width + 67px + 67px = the 475px aside), measured
                         live — not a round Tailwind step. -->
                    <div class="px-[67px] pt-16 md:pt-20">
                        <h1 class="text-4.5xl font-semibold mb-10">Account settings</h1>
                        <NavSidebar
                            :current-tab="currentTab"
                            @navigate="handleNavigation"
                        />
                    </div>
                </div>

                <!-- Main Content Column -->
                <div
                    class="flex-1 flex-col h-full w-full md:w-auto border-l border-neutral-200"
                    :class="currentSection ? 'flex' : 'hidden md:flex'">
                    <!-- Mobile back button -->
                    <div
                        v-if="isMobile && currentSection"
                        class="relative bg-white px-8 pt-12 pb-4">
                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                aria-label="Back"
                                @click="handleNavigation(null)"
                                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 12H5"/>
                                    <path d="M12 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Component Area. Matches Airbnb's actual
                         two-layer structure, not just a single centered
                         max-width: a 772px container (mx-auto — this is what
                         supplies the ~133px gutter at wide viewports) with a
                         39px padding BAKED IN on top of that, so the text
                         never sits flush against the divider even when the
                         viewport is too narrow for the 772px cap to bind and
                         the centering margin collapses to zero. Text width
                         ends up ~694px either way, matching Airbnb's
                         measured 695px effective content width. -->
                    <div class="flex-1 md:overflow-y-auto">
                        <div class="w-full max-w-[772px] mx-auto"
                             :class="currentSection ? 'pt-4 pb-40 md:pt-20' : 'pt-20 md:pt-20 md:pb-40'">
                            <div class="px-6 py-8 md:px-[39px]">
                                <component :is="currentComponent" v-bind="currentTab === 'api-keys' ? { embedded: true } : {}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import NavSidebar from './Pages/navSidebar.vue';
import PersonalInformation from './Pages/PersonalInformation.vue';
import LoginSecurity from './Pages/LoginSecurity.vue';
import Privacy from './Pages/Privacy.vue';
import Notifications from './Pages/Notifications.vue';
// Moderator/admin only — gated the same as the /settings/api-tokens route
// itself (EnsureCanManageApiTokens), not just hidden here. See navSidebar.vue.
import ApiKeys from '../Settings/api-tokens.vue';

// Same eligibility check as navSidebar.vue's link visibility (kept in sync
// by hand — both read the same two window.Laravel fields). Without this, a
// non-eligible user typing /account-settings/api-keys directly would still
// mount ApiKeys client-side and immediately eat a 403 from its own
// /settings/api-tokens/list fetch (Codex caught this in review) — the real
// data stays protected either way, but this keeps the URL from being a
// dead end. Excluding the slug from TAB_SLUGS entirely (rather than special
// casing it later) means it's treated exactly like any other unrecognized
// tab: falls back to DEFAULT_TAB below.
const apiKeysEligible = !!window.Laravel?.user?.isModerator || !!window.Laravel?.mcpTokenUiPublic;

const TAB_SLUGS = ['personal-info', 'login-security', 'privacy', 'notifications', ...(apiKeysEligible ? ['api-keys'] : [])];
const DEFAULT_TAB = 'personal-info';

const components = {
    'personal-info': PersonalInformation,
    'login-security': LoginSecurity,
    privacy: Privacy,
    notifications: Notifications,
    'api-keys': ApiKeys,
};

// Same URL-drives-tab pattern as the Hub shell (resources/js/PageComponents/Hub/index.vue)
// — the active tab is read from the path so a hard refresh or cold visit to any of the
// three URLs still lands correctly.
const explicitTabFromPath = () => {
    const slug = window.location.pathname.match(/^\/account-settings\/([^/]+)/)?.[1];

    return TAB_SLUGS.includes(slug) ? slug : null;
};

const tabFromPath = () => explicitTabFromPath() ?? DEFAULT_TAB;

const currentTab = ref(tabFromPath());
const currentSection = ref(explicitTabFromPath());

const isMobile = computed(() => window?.Laravel?.isMobile ?? false);
const currentComponent = computed(() => components[currentTab.value]);

const setTab = (tab, { pushState = true } = {}) => {
    if (!TAB_SLUGS.includes(tab)) return;

    currentTab.value = tab;

    if (pushState) {
        window.history.pushState({ tab }, '', `/account-settings/${tab}`);
    }
};

const handleNavigation = (tab) => {
    if (window?.Laravel?.isMobile) {
        currentSection.value = tab;

        if (tab) {
            setTab(tab);
        } else {
            // The mobile Back button case (tab is null) — setTab() only
            // pushState's for a real tab slug, so without this the URL stays
            // on whatever sub-page was showing even though the UI has
            // already gone back to the list; a refresh or share from here
            // would reopen that sub-page instead of the list actually shown.
            window.history.pushState({ tab: null }, '', '/account-settings');
        }
    } else {
        setTab(tab);
    }
};

const onPopState = () => {
    currentTab.value = tabFromPath();

    if (window?.Laravel?.isMobile) {
        currentSection.value = explicitTabFromPath();
    }
};

onMounted(() => window.addEventListener('popstate', onPopState));
onUnmounted(() => window.removeEventListener('popstate', onPopState));
</script>
