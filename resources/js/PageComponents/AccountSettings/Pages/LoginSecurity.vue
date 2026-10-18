<template>
    <div>
        <!-- Sizes measured off Airbnb's live page: H2 26px/600, sub-section
             H3 22px/600, row label 16px/600, row description 14px/400 grey,
             links 14px/500. -->
        <h2 class="text-4.5xl font-semibold mb-10">Login &amp; security</h2>

        <div v-if="loading" class="py-40 text-center text-neutral-500 text-1xl">Loading…</div>

        <div v-else-if="loadError" class="py-40 text-center">
            <p class="text-neutral-500 text-1xl mb-4">Could not load your login &amp; security info.</p>
            <button type="button" class="px-6 py-2 rounded-full border border-black text-1xl font-medium hover:bg-black hover:text-white transition-colors" @click="fetchData">
                Try again
            </button>
        </div>

        <template v-else>
            <!-- Row/header rhythm below (32px pt / 24px pb on each h3, 24px py
                 on each row, both as exact px — NOT Tailwind's py-*/mb-* scale,
                 which is rem-based and shrinks to 62.5% under this site's 10px
                 root font-size) is measured off Airbnb's live page the same
                 way as the rest of this file. -->

            <!-- Login -->
            <div>
                <h3 class="text-4xl font-semibold pt-[32px] pb-[24px] border-b border-neutral-200">Login</h3>
                <div class="divide-y divide-neutral-200">
                    <div class="flex items-center gap-4 py-[24px]">
                        <component :is="providerIcon" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                        <div>
                            <p class="text-2.5xl font-semibold text-black">{{ providerLabel }}</p>
                        </div>
                    </div>
                    <!-- The login-code flow (LoginCodeController::sendCode) looks
                         up by email with no provider check, so it already works
                         for every account today — including one that signs in
                         with Google/GitHub/Apple — not a future option. -->
                    <div v-if="provider" class="flex items-center gap-4 py-[24px]">
                        <component :is="RiMailLine" class="w-6 h-6 text-neutral-700 flex-shrink-0" />
                        <div>
                            <p class="text-2.5xl font-semibold text-black">Login code</p>
                            <p class="text-1xl text-neutral-500">A one-time code sent to your email also works, any time — you don't need {{ providerName }} to sign in.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Passkeys -->
            <div>
                <div class="flex items-center gap-3 pt-[32px] pb-[24px] border-b border-neutral-200">
                    <h3 class="text-4xl font-semibold">Passkeys</h3>
                    <span v-if="passkeysRecommended && !passkeys.length" class="text-sm font-semibold uppercase tracking-wide bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full">
                        Recommended
                    </span>
                </div>
                <p class="text-1xl text-neutral-500 pt-[24px] mb-[8px]">
                    Sign in with your face, fingerprint or device PIN instead of waiting for an email code.
                    <template v-if="passkeysRecommended">Your account can edit any event, so a passkey keeps it safer: it cannot be phished like an emailed code.</template>
                </p>

                <div class="divide-y divide-neutral-200">
                    <div v-for="passkey in passkeys" :key="passkey.id" class="flex items-center justify-between py-[24px] gap-4">
                        <div class="flex items-center gap-4 min-w-0">
                            <img v-if="passkey.icon" :src="passkey.icon.light" alt="" class="w-6 h-6 flex-shrink-0">
                            <component :is="RiKey2Line" v-else class="w-6 h-6 text-neutral-500 flex-shrink-0" />
                            <div class="min-w-0">
                                <p class="text-2.5xl font-semibold text-black truncate">{{ passkey.authenticator || passkey.name }}</p>
                                <p class="text-1xl text-neutral-500">
                                    <template v-if="passkey.authenticator">{{ passkey.name }} · </template>Added {{ timeAgo(passkey.created_at).toLowerCase() }}<template v-if="passkey.last_used_at"> · Last used {{ timeAgo(passkey.last_used_at).toLowerCase() }}</template>
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            :disabled="removingPasskeyId === passkey.id"
                            class="text-1xl font-medium underline hover:no-underline disabled:opacity-50 flex-shrink-0"
                            @click="removeOnePasskey(passkey)"
                        >
                            {{ removingPasskeyId === passkey.id ? 'Removing…' : 'Remove' }}
                        </button>
                    </div>
                </div>

                <p v-if="passkeyError" class="text-1xl text-red-700 pt-[16px]" role="alert">{{ passkeyError }}</p>
                <p v-if="passkeyStatus" class="text-1xl text-green-700 pt-[16px]" role="status">{{ passkeyStatus }}</p>

                <div class="pt-[24px]">
                    <button
                        v-if="canUsePasskeys"
                        type="button"
                        :disabled="addingPasskey"
                        class="px-6 py-2 rounded-full border border-black text-1xl font-medium hover:bg-black hover:text-white transition-colors disabled:opacity-50"
                        @click="addOnePasskey"
                    >
                        {{ addingPasskey ? 'Waiting for your device…' : (passkeys.length ? 'Add another passkey' : 'Add a passkey') }}
                    </button>
                    <p v-else class="text-1xl text-neutral-500">This browser cannot make passkeys. Try Safari, Chrome, Edge or Firefox on an up-to-date device.</p>
                </div>
            </div>

            <!-- Device history -->
            <div>
                <h3 class="text-4xl font-semibold pt-[32px] pb-[24px] border-b border-neutral-200">Device history</h3>
                <p class="text-1xl text-neutral-500 pt-[24px] mb-[8px]">
                    Devices that have signed into your account recently.
                </p>

                <div v-if="!devices.length" class="text-neutral-400 text-1xl py-8">
                    No device history yet.
                </div>

                <div v-else class="divide-y divide-neutral-200">
                    <div v-for="device in visibleDevices" :key="device.id" class="flex items-center justify-between py-[24px] gap-4">
                        <div class="flex items-center gap-4 min-w-0">
                            <component :is="deviceIcon(device.device_type)" class="w-6 h-6 text-neutral-500 flex-shrink-0" />
                            <div class="min-w-0">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <p class="text-2.5xl font-semibold text-black truncate">
                                        {{ device.browser || 'Unknown browser' }} on {{ device.platform || 'unknown OS' }}
                                    </p>
                                    <span v-if="device.isCurrent" class="text-sm font-semibold uppercase tracking-wide bg-green-100 text-green-700 px-2 py-0.5 rounded-full flex-shrink-0">
                                        Current session
                                    </span>
                                </div>
                                <p class="text-1xl text-neutral-500">{{ timeAgo(device.last_seen_at) }}</p>
                            </div>
                        </div>
                        <button
                            v-if="!device.isCurrent"
                            type="button"
                            :disabled="loggingOutId === device.id"
                            class="text-1xl font-medium underline hover:no-underline disabled:opacity-50 flex-shrink-0"
                            @click="logOutDevice(device.id)"
                        >
                            {{ loggingOutId === device.id ? 'Logging out…' : 'Log out' }}
                        </button>
                    </div>
                </div>

                <!-- The list is capped at 20 rows server-side (see LoginHistory
                     pruning in RecordLoginHistory), which reads as a wall of
                     near-identical "Chrome on Mac" rows for anyone signed in
                     from one browser across many days — collapse to a short
                     preview by default, same as Airbnb's own device list. -->
                <button
                    v-if="devices.length > INITIAL_DEVICE_COUNT && !showAllDevices"
                    type="button"
                    class="text-1xl font-medium underline hover:no-underline mt-4"
                    @click="showAllDevices = true"
                >
                    Show all {{ devices.length }} devices
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RiGoogleFill, RiGithubFill, RiAppleFill, RiMailLine, RiComputerLine, RiSmartphoneLine, RiTabletLine, RiKey2Line } from '@remixicon/vue';
import { addPasskey, removePasskey, passkeysSupported, syncPasswordManager } from '@/composables/usePasskeys';

const INITIAL_DEVICE_COUNT = 5;

const loading = ref(true);
const loadError = ref(false);
const provider = ref(null);
const devices = ref([]);
const loggingOutId = ref(null);
const showAllDevices = ref(false);

const passkeys = ref([]);
const passkeySignals = ref(null);
const passkeysRecommended = ref(false);
const canUsePasskeys = passkeysSupported();
const addingPasskey = ref(false);
const removingPasskeyId = ref(null);
const passkeyError = ref('');
const passkeyStatus = ref('');

const visibleDevices = computed(() => (
    showAllDevices.value ? devices.value : devices.value.slice(0, INITIAL_DEVICE_COUNT)
));

const providerIcon = computed(() => {
    if (provider.value === 'google') return RiGoogleFill;
    if (provider.value === 'github') return RiGithubFill;
    if (provider.value === 'apple') return RiAppleFill;
    return RiMailLine;
});

const providerLabel = computed(() => {
    if (provider.value === 'google') return 'You sign in with Google';
    if (provider.value === 'github') return 'You sign in with GitHub';
    if (provider.value === 'apple') return 'You sign in with Apple';
    return 'You sign in with a login code sent to your email';
});

const providerName = computed(() => {
    if (provider.value === 'google') return 'Google';
    if (provider.value === 'github') return 'GitHub';
    if (provider.value === 'apple') return 'Apple';
    return '';
});

const deviceIcon = (type) => {
    if (type === 'mobile') return RiSmartphoneLine;
    if (type === 'tablet') return RiTabletLine;
    return RiComputerLine;
};

const timeAgo = (isoDate) => {
    const seconds = Math.floor((Date.now() - new Date(isoDate).getTime()) / 1000);
    const units = [
        ['year', 31536000], ['month', 2592000], ['week', 604800],
        ['day', 86400], ['hour', 3600], ['minute', 60],
    ];
    for (const [unit, secondsInUnit] of units) {
        const count = Math.floor(seconds / secondsInUnit);
        if (count >= 1) return `${count} ${unit}${count > 1 ? 's' : ''} ago`;
    }
    return 'Just now';
};

const fetchData = async () => {
    loading.value = true;
    loadError.value = false;

    try {
        const { data } = await axios.get('/api/account-settings/login-security');
        provider.value = data.provider;
        devices.value = data.devices;
        applyPasskeys(data);
    } catch (error) {
        console.error('[login-security] failed to load', error);
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

const applyPasskeys = (data) => {
    passkeys.value = data.passkeys ?? [];
    passkeySignals.value = data.passkeySignals ?? null;
    passkeysRecommended.value = !!data.passkeysRecommended;
};

// Re-read after a change: the list (with the provider name and logo the
// server works out) and the signal data both come from the server.
const refreshPasskeys = async () => {
    const { data } = await axios.get('/api/account-settings/login-security');
    applyPasskeys(data);
};

const addOnePasskey = async () => {
    addingPasskey.value = true;
    passkeyError.value = '';
    passkeyStatus.value = '';
    try {
        const saved = await addPasskey();
        if (saved) {
            await refreshPasskeys();
            passkeyStatus.value = 'Passkey saved. Next time, pick it when you sign in.';
        }
    } catch (error) {
        passkeyError.value = error.message;
    } finally {
        addingPasskey.value = false;
    }
};

const removeOnePasskey = async (passkey) => {
    removingPasskeyId.value = passkey.id;
    passkeyError.value = '';
    passkeyStatus.value = '';
    try {
        await removePasskey(passkey.id);
        await refreshPasskeys();
        // Now that the list is fresh, tell the password manager which
        // passkeys are left, so the removed one stops being offered.
        syncPasswordManager(passkeySignals.value);
        passkeyStatus.value = 'Passkey removed. You can still sign in with an email code.';
    } catch (error) {
        console.error('[login-security] failed to remove passkey', error);
        passkeyError.value = 'Could not remove the passkey. Please try again.';
    } finally {
        removingPasskeyId.value = null;
    }
};

const logOutDevice = async (id) => {
    loggingOutId.value = id;
    try {
        await axios.delete(`/api/account-settings/login-security/devices/${id}`);
        devices.value = devices.value.filter((d) => d.id !== id);
    } catch (error) {
        console.error('[login-security] failed to log out device', error);
    } finally {
        loggingOutId.value = null;
    }
};

onMounted(fetchData);
</script>
