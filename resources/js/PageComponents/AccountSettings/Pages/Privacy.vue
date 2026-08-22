<template>
    <div>
        <!-- Sizes measured off Airbnb's live page: H2 26px/600, row title
             16px/600, row description 14px/400 grey, links 14px/500. -->
        <h2 class="text-4.5xl font-semibold mb-10">Privacy</h2>

        <div v-if="loading" class="py-40 text-center text-neutral-500 text-1xl">Loading…</div>

        <div v-else-if="loadError" class="py-40 text-center">
            <p class="text-neutral-500 text-1xl mb-4">Could not load your privacy settings.</p>
            <button type="button" class="px-6 py-2 rounded-full border border-black text-1xl font-medium hover:bg-black hover:text-white transition-colors" @click="fetchPreferences">
                Try again
            </button>
        </div>

        <template v-else>
            <!-- Visibility toggles -->
            <div class="space-y-8 mb-12">
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex-1">
                        <h3 class="text-2.5xl font-semibold text-black">Show followed organizers</h3>
                        <p class="text-1xl text-gray-600">Let other people see the organizers you follow on your profile.</p>
                    </div>
                    <div class="flex justify-end">
                        <preference-toggle
                            aria-label="Show followed organizers"
                            :checked="followedOrganizers"
                            :disabled="saving"
                            @change="toggle('followed_organizers')"
                        />
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex-1">
                        <h3 class="text-2.5xl font-semibold text-black">Show saved events count</h3>
                        <p class="text-1xl text-gray-600">Let other people see how many events you've saved on your profile.</p>
                    </div>
                    <div class="flex justify-end">
                        <preference-toggle
                            aria-label="Show saved events count"
                            :checked="savedEventsCount"
                            :disabled="saving"
                            @change="toggle('saved_events_count')"
                        />
                    </div>
                </div>

                <p v-if="saveError" class="text-red-600 text-1xl">{{ saveError }}</p>
            </div>

            <!-- Bottom block -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="border border-neutral-200 rounded-2xl p-6">
                    <component :is="RiDownloadLine" class="w-6 h-6 mb-3" />
                    <h3 class="text-2.5xl font-semibold mb-2">Request my personal data</h3>
                    <p class="text-1xl text-neutral-500 mb-4">Get a copy of the data we have on your account.</p>
                    <button
                        type="button"
                        :disabled="requestingData || dataRequested"
                        class="text-1xl font-medium underline hover:no-underline disabled:opacity-50"
                        @click="requestData"
                    >
                        {{ dataRequested ? 'Request sent' : (requestingData ? 'Sending…' : 'Request data') }}
                    </button>
                </div>

                <div class="border border-neutral-200 rounded-2xl p-6">
                    <component :is="RiInformationLine" class="w-6 h-6 mb-3" />
                    <h3 class="text-2.5xl font-semibold mb-2">Cookie preferences</h3>
                    <p class="text-1xl text-neutral-500">
                        We use essential session cookies and Google Analytics. A self-serve cookie manager isn't available yet — email
                        <a href="mailto:support@everythingimmersive.com" class="underline hover:no-underline">support@everythingimmersive.com</a>
                        to opt out of analytics.
                    </p>
                </div>

                <div class="border border-red-200 rounded-2xl p-6 md:col-span-2">
                    <component :is="RiDeleteBinLine" class="w-6 h-6 mb-3 text-red-600" />
                    <h3 class="text-2.5xl font-semibold mb-2">Delete my account</h3>
                    <p class="text-1xl text-neutral-500 mb-4">This permanently deletes your account. This can't be undone.</p>
                    <button
                        type="button"
                        class="text-1xl font-medium text-red-600 underline hover:no-underline"
                        @click="showDeleteModal = true"
                    >
                        Delete my account
                    </button>
                </div>
            </div>

            <div class="flex items-start gap-3 bg-neutral-50 rounded-2xl p-6">
                <component :is="RiShieldCheckLine" class="w-6 h-6 flex-shrink-0 text-neutral-500" />
                <p class="text-lg text-neutral-500">
                    Committed to privacy. We only ever use your info to run Everything Immersive — never sold, never shared beyond what's needed to run the site.
                </p>
            </div>
        </template>

        <!-- Delete confirmation modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black bg-opacity-40" @click="closeDeleteModal"></div>
                <div class="relative bg-white rounded-3xl max-w-lg w-full p-8 shadow-xl">
                    <h3 class="text-2.5xl font-semibold mb-4">Delete your account?</h3>

                    <template v-if="!deleteBlockedMessage">
                        <p class="text-neutral-600 text-1xl mb-6">
                            This permanently deletes your account, saved events, followed organizers, and everything else tied to it. This can't be undone.
                        </p>
                        <label class="block text-1xl text-neutral-500 mb-2">Type <span class="font-semibold">{{ userEmail }}</span> to confirm</label>
                        <input v-model="deleteConfirmText" type="text" class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-1xl mb-6 focus:outline-none focus:border-red-500">
                        <p v-if="deleteError" class="text-red-600 text-1xl mb-4">{{ deleteError }}</p>
                        <div class="flex gap-4">
                            <button
                                type="button"
                                :disabled="!isConfirmValid || deleting"
                                class="px-6 py-3 rounded-lg bg-red-600 text-white text-1xl font-medium hover:bg-red-700 disabled:opacity-50"
                                @click="confirmDelete"
                            >
                                {{ deleting ? 'Deleting…' : 'Delete my account' }}
                            </button>
                            <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium hover:bg-neutral-100" @click="closeDeleteModal">Cancel</button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-neutral-600 text-1xl mb-6">{{ deleteBlockedMessage }}</p>
                        <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium bg-neutral-100 hover:bg-neutral-200" @click="closeDeleteModal">Close</button>
                    </template>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RiDownloadLine, RiInformationLine, RiDeleteBinLine, RiShieldCheckLine } from '@remixicon/vue';
import PreferenceToggle from '@/GlobalComponents/preference-toggle.vue';

// window.Laravel.user is set globally on every page load (see
// master-container.blade.php) — no need to thread it through as a prop.
const userEmail = window?.Laravel?.user?.email ?? '';

const loading = ref(true);
const loadError = ref(false);
const saving = ref(false);
const saveError = ref('');

const followedOrganizers = ref(false);
const savedEventsCount = ref(false);

const requestingData = ref(false);
const dataRequested = ref(false);

const showDeleteModal = ref(false);
const deleteConfirmText = ref('');
const deleting = ref(false);
const deleteError = ref('');
const deleteBlockedMessage = ref('');

const isConfirmValid = computed(
    () => userEmail !== '' && deleteConfirmText.value.trim().toLowerCase() === userEmail.toLowerCase(),
);

const refs = {
    followed_organizers: followedOrganizers,
    saved_events_count: savedEventsCount,
};

const fetchPreferences = async () => {
    loading.value = true;
    loadError.value = false;

    try {
        const { data } = await axios.get('/api/account-settings/privacy');
        followedOrganizers.value = !!data.privacy_preferences.followed_organizers;
        savedEventsCount.value = !!data.privacy_preferences.saved_events_count;
    } catch (error) {
        console.error('[privacy] failed to load preferences', error);
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

// Same pattern as the Hub Notifications tab — always submits the full
// two-key state (with the clicked one flipped), `saving` disables both
// switches while a save is in flight, so at most one PATCH is ever
// outstanding.
const toggle = async (key) => {
    if (saving.value) return;

    const previous = { followed_organizers: followedOrganizers.value, saved_events_count: savedEventsCount.value };
    refs[key].value = !refs[key].value;
    saving.value = true;
    saveError.value = '';

    try {
        await axios.patch('/api/account-settings/privacy', {
            followed_organizers: followedOrganizers.value,
            saved_events_count: savedEventsCount.value,
        });
    } catch (error) {
        followedOrganizers.value = previous.followed_organizers;
        savedEventsCount.value = previous.saved_events_count;
        saveError.value = 'Could not save that change. Please try again.';
        console.error('[privacy] failed to save preferences', error);
    } finally {
        saving.value = false;
    }
};

const requestData = async () => {
    requestingData.value = true;
    try {
        await axios.post('/api/account-settings/privacy/data-request');
        dataRequested.value = true;
    } catch (error) {
        console.error('[privacy] failed to request data', error);
    } finally {
        requestingData.value = false;
    }
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deleteConfirmText.value = '';
    deleteError.value = '';
    deleteBlockedMessage.value = '';
};

const confirmDelete = async () => {
    deleting.value = true;
    deleteError.value = '';

    try {
        await axios.delete('/api/account-settings');
        window.location.href = '/';
    } catch (error) {
        if (error.response?.status === 422) {
            deleteBlockedMessage.value = error.response.data?.message || 'Your account cannot be deleted right now.';
        } else {
            deleteError.value = 'Something went wrong. Please try again.';
            console.error('[privacy] failed to delete account', error);
        }
    } finally {
        deleting.value = false;
    }
};

onMounted(fetchPreferences);
</script>
