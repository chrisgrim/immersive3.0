<template>
    <div :class="embedded ? 'w-full' : 'px-8 md:px-32 w-full mt-20 md:mt-12 max-w-5xl mx-auto'">
        <h2 class="text-4.5xl font-semibold mb-4">AI &amp; API access</h2>
        <p class="text-1xl text-neutral-500 mb-10">
            Let an AI assistant create and edit events for your organizers. It acts as you, on your
            organizers only; submissions still go through the normal review.
        </p>

        <!-- Connect an assistant -->
        <section class="border border-neutral-200 rounded-2xl p-8 mb-8">
            <h3 class="text-2.5xl font-semibold mb-2">Connect an assistant</h3>
            <p class="text-1xl text-neutral-500 mb-6">
                Give your assistant this address. It will open a sign-in page here, you approve, and
                it's connected — nothing to copy.
            </p>
            <code class="block bg-neutral-100 rounded-xl px-4 py-3 break-all text-2xl mb-6" data-test="mcp-url">{{ mcpUrl }}</code>
            <ul class="space-y-3 text-1xl text-neutral-700">
                <li>
                    <strong class="font-semibold">Claude Code</strong> — in the terminal:
                    <code class="bg-neutral-100 rounded px-2 py-1 break-all">claude mcp add --transport http everything-immersive {{ mcpUrl }}</code>
                </li>
                <li>
                    <strong class="font-semibold">Claude</strong> (web or desktop) — Settings → Connectors → Add custom connector, paste the address.
                </li>
                <li>
                    <strong class="font-semibold">ChatGPT</strong> — Settings → Connectors → Create, paste the address.
                </li>
            </ul>
        </section>

        <!-- Connected apps -->
        <section class="mb-10">
            <h3 class="text-2.5xl font-semibold mb-2">Connected apps</h3>
            <p class="text-1xl text-neutral-500 mb-4">Assistants you've approved. Disconnecting one stops it immediately.</p>

            <div v-if="apps.length" class="border border-neutral-200 rounded-2xl divide-y divide-neutral-200" data-test="connected-apps">
                <div v-for="app in apps" :key="app.id" class="flex items-center justify-between p-6 gap-4">
                    <div class="min-w-0">
                        <p class="text-2xl font-medium truncate">
                            {{ app.app }}
                            <span v-if="app.scopes?.includes('mcp:moderate')" class="ml-2 rounded-full bg-red-50 text-red-700 px-3 py-1 text-sm font-medium align-middle">moderator powers</span>
                        </p>
                        <p class="text-1xl text-neutral-500">
                            Connected {{ formatDate(app.connected_at) }}
                            <span v-if="app.expires_at"> · Renews until {{ formatDate(app.expires_at) }}</span>
                        </p>
                    </div>
                    <button
                        class="rounded-full border border-red-600 text-red-600 px-6 h-14 hover:bg-red-50 flex-shrink-0 text-1xl"
                        @click="disconnect(app)"
                    >
                        Disconnect
                    </button>
                </div>
            </div>
            <p v-else-if="appsLoaded" class="text-1xl text-neutral-500" data-test="no-apps">No assistants connected yet.</p>
        </section>

        <!-- API keys -->
        <section>
            <h3 class="text-2.5xl font-semibold mb-2">API keys</h3>
            <p class="text-1xl text-neutral-500 mb-4">
                For scripts and tools that can't open a sign-in page. A key acts as you for 90 days; the
                key itself is shown once, when you create it.
            </p>

            <div class="border border-neutral-200 rounded-2xl p-8 mb-6">
                <label class="block text-1xl font-medium mb-2" for="token-name">New key name</label>
                <div class="flex flex-col md:flex-row gap-4">
                    <input
                        id="token-name"
                        v-model="newTokenName"
                        type="text"
                        maxlength="60"
                        placeholder="e.g. Nightly import script"
                        class="flex-grow border border-black rounded-full px-6 h-16"
                        @keyup.enter="createToken"
                    >
                    <button
                        :disabled="creating || !newTokenName.trim()"
                        class="rounded-full bg-black text-white px-8 h-16 disabled:opacity-40 hover:bg-neutral-800"
                        @click="createToken"
                    >
                        {{ creating ? 'Creating…' : 'Create key' }}
                    </button>
                </div>

                <label v-if="isModerator" class="flex items-start gap-3 mt-6 text-1xl cursor-pointer" data-test="moderate-option">
                    <input v-model="moderate" type="checkbox" class="mt-1">
                    <span>
                        <strong class="font-semibold">Include moderator powers.</strong>
                        <span class="text-neutral-500">
                            This key will be able to read and edit any event or organizer on the site. Only for
                            scripts that need it — an assistant you connect above never gets this.
                        </span>
                    </span>
                </label>

                <p v-if="createError" class="text-red-600 mt-4 text-1xl">{{ createError }}</p>

                <div v-if="freshToken" class="mt-8 bg-neutral-100 rounded-2xl p-6" data-test="fresh-token">
                    <p class="text-1xl font-medium mb-2">Copy your key now — it will not be shown again.</p>
                    <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center">
                        <code class="bg-white border border-neutral-200 rounded-xl px-4 py-3 break-all flex-grow text-2xl">{{ freshToken }}</code>
                        <button class="rounded-full border border-black px-8 h-16 hover:bg-neutral-200 flex-shrink-0" @click="copyToken">
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="tokens.length" class="border border-neutral-200 rounded-2xl divide-y divide-neutral-200" data-test="api-keys">
                <div v-for="token in tokens" :key="token.id" class="flex items-center justify-between p-6 gap-4">
                    <div class="min-w-0">
                        <p class="text-2xl font-medium truncate">
                            {{ token.name }}
                            <span v-if="token.moderate" class="ml-2 rounded-full bg-red-50 text-red-700 px-3 py-1 text-sm font-medium align-middle">moderator powers</span>
                        </p>
                        <p class="text-1xl text-neutral-500">
                            Created {{ formatDate(token.created_at) }}
                            <span v-if="token.expires_at"> · Expires {{ formatDate(token.expires_at) }}</span>
                        </p>
                    </div>
                    <button
                        class="rounded-full border border-red-600 text-red-600 px-6 h-14 hover:bg-red-50 flex-shrink-0 text-1xl"
                        @click="revokeToken(token)"
                    >
                        Revoke
                    </button>
                </div>
            </div>
            <p v-else-if="tokensLoaded" class="text-1xl text-neutral-500" data-test="no-keys">You have no API keys.</p>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

// `embedded` = rendered inside the account settings panel (which already
// centers/pads); standalone /settings/api-tokens leaves it false.
defineProps({
    embedded: { type: Boolean, default: false },
});

const mcpUrl = computed(() => `${window.location.origin}/mcp`);
const isModerator = computed(() => !!window.Laravel?.user?.isModerator);

const apps = ref([]);
const appsLoaded = ref(false);
const tokens = ref([]);
const tokensLoaded = ref(false);
const newTokenName = ref('');
const moderate = ref(false);
const freshToken = ref(null);
const creating = ref(false);
const createError = ref(null);
const copied = ref(false);

const fetchApps = async () => {
    const response = await axios.get('/oauth/connections');
    apps.value = response.data.apps;
    appsLoaded.value = true;
};

const fetchTokens = async () => {
    const response = await axios.get('/settings/api-tokens/list');
    tokens.value = response.data.tokens;
    tokensLoaded.value = true;
};

const disconnect = async (app) => {
    if (!window.confirm(`Disconnect ${app.app}? It will stop working immediately.`)) return;
    await axios.delete(`/oauth/connections/${app.id}`);
    apps.value = apps.value.filter((a) => a.id !== app.id);
};

const createToken = async () => {
    if (!newTokenName.value.trim() || creating.value) return;
    creating.value = true;
    createError.value = null;
    freshToken.value = null;
    copied.value = false;
    try {
        const response = await axios.post('/settings/api-tokens', {
            name: newTokenName.value.trim(),
            moderate: isModerator.value && moderate.value,
        });
        freshToken.value = response.data.token;
        newTokenName.value = '';
        moderate.value = false;
        await fetchTokens();
    } catch (error) {
        createError.value = error.response?.data?.errors?.name?.[0]
            ?? error.response?.data?.message
            ?? 'Something went wrong.';
    } finally {
        creating.value = false;
    }
};

const copyToken = async () => {
    try {
        // navigator.clipboard only exists in secure contexts (https/localhost);
        // fall back to a hidden textarea on plain-http dev domains like ei.test.
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(freshToken.value);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = freshToken.value;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
        copied.value = true;
    } catch (error) {
        // Leave the key visible for manual selection.
        copied.value = false;
    }
};

const revokeToken = async (token) => {
    if (!window.confirm(`Revoke "${token.name}"? Anything using it will stop working immediately.`)) return;
    await axios.delete(`/settings/api-tokens/${token.id}`);
    tokens.value = tokens.value.filter((t) => t.id !== token.id);
};

const formatDate = (value) => new Date(value).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
});

onMounted(() => {
    fetchApps();
    fetchTokens();
});
</script>
