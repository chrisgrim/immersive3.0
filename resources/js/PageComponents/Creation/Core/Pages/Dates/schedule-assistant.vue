<template>
    <!-- Admin-only scheduling assistant. Collapsed to a pill; expands to a chat panel. -->
    <div v-if="isAdmin">
        <!-- Chat panel: bottom-anchored on its own, independent of where the
             launcher sits. Only rendered while open. -->
        <div
            v-if="open"
            class="fixed bottom-6 right-6 z-[1200] flex w-[92vw] max-w-[52rem] flex-col overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-custom-1"
            :style="minimized ? '' : 'height: min(52rem, 90vh)'"
        >
            <!-- Header (click to restore when minimized) -->
            <div
                class="flex items-center justify-between px-6 py-4"
                :class="minimized ? 'cursor-pointer' : 'border-b border-neutral-100'"
                @click="minimized && (minimized = false)"
            >
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🗓️</span>
                    <div>
                        <p class="text-2xl font-bold leading-tight">Schedule assistant</p>
                        <p class="text-base font-light text-neutral-500">Admin · dates only</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click.stop="minimized = !minimized"
                        class="flex h-12 w-12 items-center justify-center rounded-full border-none bg-neutral-100 text-3xl leading-none text-neutral-600 hover:bg-neutral-200 hover:text-neutral-900"
                        :aria-label="minimized ? 'Expand' : 'Minimize'"
                    >{{ minimized ? '□' : '−' }}</button>
                    <button
                        @click.stop="open = false"
                        class="flex h-12 w-12 items-center justify-center rounded-full border-none bg-neutral-100 text-4xl leading-none text-neutral-600 hover:bg-neutral-200 hover:text-neutral-900"
                        aria-label="Close"
                    >×</button>
                </div>
            </div>

            <!-- Messages -->
            <div v-show="!minimized" ref="scrollRef" class="flex-1 space-y-4 overflow-y-auto px-6 py-4">
                <p v-if="messages.length === 0" class="text-xl font-light leading-relaxed text-neutral-400">
                    Tell me how the show runs and I'll set the dates.
                </p>

                <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-2xl leading-relaxed"
                        :class="m.role === 'user'
                            ? 'bg-neutral-900 text-white'
                            : (m.error ? 'bg-red-50 text-red-700' : 'bg-neutral-100 text-neutral-800')"
                    >{{ m.text }}</div>
                </div>

                <div v-if="loading" class="flex justify-start">
                    <div class="rounded-2xl bg-neutral-100 px-4 py-3 text-2xl text-neutral-500">
                        <span class="animate-pulse">Thinking…</span>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div v-show="!minimized" class="border-t border-neutral-100 p-4">
                <textarea
                    v-model="input"
                    @keydown.enter.exact.prevent="send"
                    :disabled="loading"
                    rows="3"
                    placeholder="Describe the schedule…"
                    class="min-h-[5.5rem] max-h-56 w-full resize-y rounded-2xl border border-neutral-200 px-4 py-3 text-1xl leading-relaxed focus:border-neutral-800 focus:outline-none"
                ></textarea>
                <button
                    @click="send"
                    :disabled="loading || !input.trim()"
                    class="mt-3 w-full rounded-2xl border-none bg-neutral-900 px-5 py-4 text-xl font-medium text-white disabled:opacity-40"
                >Send</button>
            </div>
        </div>

        <!-- Launcher: a compact FAB lifted above the page's own bottom-right
             action buttons (the wizard "Continue" and the edit "Update/Resubmit")
             so it never covers them. Shown only while the panel is closed;
             closing the panel returns here with the conversation preserved. -->
        <button
            v-if="!open"
            @click="open = true; minimized = false"
            title="Schedule assistant"
            aria-label="Open schedule assistant"
            class="fixed bottom-44 right-8 z-[1200] flex h-20 w-20 items-center justify-center rounded-full border-none bg-neutral-900 text-4xl text-white shadow-custom-1 hover:bg-neutral-800"
        >🗓️</button>
    </div>
</template>

<script setup>
import { ref, computed, inject, nextTick } from 'vue';

const emit = defineEmits(['schedule-updated']);

const event = inject('event');
const user = inject('user');

const isAdmin = computed(() => !!(user && user.isAdmin));

const open = ref(false);
const minimized = ref(false);
const input = ref('');
const loading = ref(false);
const messages = ref([]); // { role: 'user' | 'assistant', text, error? }
const scrollRef = ref(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (scrollRef.value) {
            scrollRef.value.scrollTop = scrollRef.value.scrollHeight;
        }
    });
};

const send = async () => {
    const text = input.value.trim();
    if (!text || loading.value) return;

    // History = everything already exchanged (exclude error bubbles).
    const history = messages.value
        .filter(m => !m.error)
        .map(m => ({ role: m.role, content: m.text }));

    messages.value.push({ role: 'user', text });
    input.value = '';
    loading.value = true;
    scrollToBottom();

    try {
        const { data } = await window.axios.post(
            `/api/hosting/event/${event.slug}/schedule-assistant`,
            { message: text, history },
            // Override the global 30s axios default: an Opus turn (extended
            // thinking + tool-use loop + expanding a long recurring dateArray)
            // routinely runs longer than 30s. At 30s the browser aborted the
            // request (nginx logged 499) while the server kept working — the user
            // saw "Something went wrong" even though the change sometimes applied.
            { timeout: 180000 }
        );

        messages.value.push({ role: 'assistant', text: data.reply || 'Done.' });

        if (data.schedule_changed && data.schedule) {
            emit('schedule-updated', data.schedule);
        }
    } catch (e) {
        const msg = e?.response?.status === 403
            ? 'You need admin access to use the schedule assistant.'
            : 'Something went wrong reaching the assistant. Please try again.';
        messages.value.push({ role: 'assistant', text: msg, error: true });
    } finally {
        loading.value = false;
        scrollToBottom();
    }
};
</script>
