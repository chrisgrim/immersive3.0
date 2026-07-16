<template>
    <div class="px-8 md:px-32 w-full mt-20 md:mt-12 max-w-5xl mx-auto">
        <div class="w-full flex items-center justify-between mb-4">
            <h2 class="font-medium">API Tokens</h2>
        </div>
        <p class="text-gray-600 mb-8">
            Connect AI assistants (like Claude) to Everything Immersive. Tokens let an assistant create
            organizers and event drafts on your behalf — submissions still go through normal review.
            Tokens expire after 90 days.
        </p>

        <!-- Create form -->
        <div class="border border-gray-300 rounded-2xl p-8 mb-8">
            <label class="block font-medium mb-2" for="token-name">New token name</label>
            <div class="flex flex-col md:flex-row gap-4">
                <input
                    id="token-name"
                    v-model="newTokenName"
                    type="text"
                    maxlength="60"
                    placeholder="e.g. Claude on my laptop"
                    class="flex-grow border border-black rounded-full px-6 h-16"
                    @keyup.enter="createToken"
                >
                <button
                    :disabled="creating || !newTokenName.trim()"
                    class="rounded-full bg-black text-white px-8 h-16 disabled:opacity-40 hover:bg-gray-800"
                    @click="createToken"
                >
                    {{ creating ? 'Creating…' : 'Create token' }}
                </button>
            </div>
            <p v-if="createError" class="text-red-600 mt-4">{{ createError }}</p>

            <!-- Show-once token display -->
            <div v-if="freshToken" class="mt-8 bg-gray-100 rounded-2xl p-6">
                <p class="font-medium mb-2">Copy your token now — it will not be shown again.</p>
                <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center">
                    <code class="bg-white border border-gray-300 rounded-xl px-4 py-3 break-all flex-grow text-2xl">{{ freshToken }}</code>
                    <button
                        class="rounded-full border border-black px-8 h-16 hover:bg-gray-200 flex-shrink-0"
                        @click="copyToken"
                    >
                        {{ copied ? 'Copied!' : 'Copy' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Token list -->
        <div v-if="tokens.length" class="border border-gray-300 rounded-2xl divide-y divide-gray-200">
            <div
                v-for="token in tokens"
                :key="token.id"
                class="flex items-center justify-between p-6 gap-4"
            >
                <div class="min-w-0">
                    <p class="font-medium truncate">{{ token.name }}</p>
                    <p class="text-gray-500 text-2xl">
                        Created {{ formatDate(token.created_at) }}
                        <span v-if="token.last_used_at"> · Last used {{ formatDate(token.last_used_at) }}</span>
                        <span v-else> · Never used</span>
                        <span v-if="token.expires_at"> · Expires {{ formatDate(token.expires_at) }}</span>
                    </p>
                </div>
                <button
                    class="rounded-full border border-red-600 text-red-600 px-6 h-14 hover:bg-red-50 flex-shrink-0"
                    @click="revokeToken(token)"
                >
                    Revoke
                </button>
            </div>
        </div>
        <p v-else-if="loaded" class="text-gray-500">You have no API tokens.</p>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const tokens = ref([]);
const loaded = ref(false);
const newTokenName = ref('');
const freshToken = ref(null);
const creating = ref(false);
const createError = ref(null);
const copied = ref(false);

const fetchTokens = async () => {
    const response = await axios.get('/settings/api-tokens/list');
    tokens.value = response.data.tokens;
    loaded.value = true;
};

const createToken = async () => {
    if (!newTokenName.value.trim() || creating.value) return;
    creating.value = true;
    createError.value = null;
    freshToken.value = null;
    copied.value = false;
    try {
        const response = await axios.post('/settings/api-tokens', { name: newTokenName.value.trim() });
        freshToken.value = response.data.token;
        newTokenName.value = '';
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
    await navigator.clipboard.writeText(freshToken.value);
    copied.value = true;
};

const revokeToken = async (token) => {
    if (!window.confirm(`Revoke "${token.name}"? Anything using it will stop working immediately.`)) return;
    await axios.delete(`/settings/api-tokens/${token.id}`);
    tokens.value = tokens.value.filter(t => t.id !== token.id);
};

const formatDate = (value) => new Date(value).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
});

onMounted(fetchTokens);
</script>
