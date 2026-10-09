<template>
    <button type="button" aria-haspopup="dialog" @click="open" v-bind="$attrs">
        <slot>Tell us about an event</slot>
    </button>

    <teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-[2000]"
            @click.self="close"
        >
            <div
                class="relative bg-white w-full md:max-w-[76rem] md:mx-8 md:rounded-3xl rounded-t-3xl shadow-xl flex flex-col max-h-[90vh] text-left"
                role="dialog"
                aria-modal="true"
                aria-labelledby="suggest-event-title"
            >
                <button type="button" class="absolute top-6 right-6 p-2 rounded-full text-neutral-500 hover:text-black hover:bg-neutral-100" aria-label="Close" @click="close">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="px-8 pt-12 pb-10 md:px-24 md:pt-16 md:pb-14 overflow-y-auto">
                    <h2 id="suggest-event-title" class="text-4xl md:text-5xl font-semibold tracking-tight text-neutral-900">Tell us about the event</h2>

                    <!-- Thank-you state -->
                    <template v-if="sent">
                        <p class="mt-4 text-2xl text-neutral-500">Thanks! We got your suggestion and will take a look.</p>
                        <div class="flex justify-end gap-4 mt-12">
                            <button type="button" class="px-8 py-4 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl font-semibold" @click="reset">
                                Tell us about another
                            </button>
                            <button type="button" class="px-8 py-4 bg-black text-white rounded-2xl hover:bg-neutral-800 text-xl font-semibold" @click="close">
                                Done
                            </button>
                        </div>
                    </template>

                    <!-- Form. The textarea is 16px on phones: iOS zooms the page into any
                         smaller field it focuses. -->
                    <form v-else novalidate @submit.prevent="submit">
                        <!-- Creators and PR reps kept using this box to have us list their
                             own show, so point them at the real submission flow first. -->
                        <div class="mt-6 rounded-2xl bg-neutral-100 p-6">
                            <p class="text-2xl text-neutral-900">Are you a creator, producer, or press agent for an event?</p>
                            <a href="/hosting/getting-started" class="mt-2 inline-block text-2xl font-semibold text-black underline hover:text-neutral-700">Submit your event here</a>
                        </div>
                        <p class="mt-8 text-2xl text-neutral-500">Otherwise, if you're a fan who is <strong class="font-semibold text-neutral-900">not involved</strong> with the experience, tell us about it below: the name, a link, and where it is.</p>

                        <textarea
                            ref="messageInput"
                            v-model="form.message"
                            :maxlength="MAX_LENGTH"
                            rows="9"
                            aria-labelledby="suggest-event-title"
                            aria-describedby="suggest-event-count suggest-event-error"
                            :aria-invalid="!!errors.message"
                            placeholder="Event name, website, where it is..."
                            class="mt-10 w-full resize-y rounded-2xl border border-neutral-400 p-6 text-[1.6rem] md:text-2xl leading-relaxed text-neutral-900 placeholder-neutral-400 focus:border-black focus:shadow-[0_0_0_1px_black] focus:outline-none"
                            :class="{ 'border-red-500': errors.message }"
                        ></textarea>
                        <div class="mt-3 flex items-start justify-between gap-6">
                            <p id="suggest-event-count" class="text-lg font-semibold text-neutral-500">{{ form.message.length }}/{{ MAX_LENGTH }}</p>
                            <p id="suggest-event-error" role="alert" class="text-lg text-red-500 text-right">{{ errors.message || generalError }}</p>
                        </div>

                        <!-- Honeypot: invisible to people, tempting to bots. Must stay empty. -->
                        <div class="absolute -left-[9999px] w-px h-px overflow-hidden" aria-hidden="true">
                            <label for="suggest-event-extra">Leave this empty</label>
                            <input id="suggest-event-extra" v-model="form[HONEYPOT]" type="text" :name="HONEYPOT" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="flex justify-end mt-10">
                            <button
                                type="submit"
                                :disabled="submitting"
                                class="px-8 py-4 bg-black text-white rounded-2xl hover:bg-neutral-800 text-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ submitting ? 'Sending…' : 'Send' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref, reactive, nextTick, onUnmounted } from 'vue';
import axios from 'axios';

// The root is the trigger button; attrs (class etc. from the footer) go there
// explicitly, since the teleported modal makes this a multi-root component.
defineOptions({ inheritAttrs: false });

const isOpen = ref(false);
const sent = ref(false);
const submitting = ref(false);
const generalError = ref('');
const messageInput = ref(null);

// Mirror EventSuggestion::MAX_LENGTH and EventSuggestionController::HONEYPOT.
const MAX_LENGTH = 500;
const HONEYPOT = 'ei_hp_extra';

const emptyForm = () => ({ message: '', [HONEYPOT]: '' });
const form = reactive(emptyForm());
const errors = reactive({});

// The spam check: fetch a challenge from our own server and solve it in the
// background while the person types, so it's usually done by the time they
// press Send. The solver library only loads when the form opens. A proof is
// good for one send and the server lets it expire after 30 minutes, so an
// older one is replaced rather than sent.
const MIN_FORM_MS = 3500;
const PROOF_MAX_AGE_MS = 25 * 60 * 1000;
let proof = null; // { promise, startedAt }

// Failures of the spam check itself (as opposed to the send), so the person
// gets a message that fits rather than a generic one.
class ProofError extends Error {}

function startProof() {
    const startedAt = Date.now();
    const promise = (async () => {
        if (!window.crypto?.subtle) {
            throw new ProofError("This browser can't run our spam check. Please try a different browser.");
        }
        let challenge;
        let lib;
        try {
            [lib, { data: challenge }] = await Promise.all([
                import('altcha/lib'),
                axios.get('/api/event-suggestions/challenge'),
            ]);
        } catch (error) {
            throw new ProofError(error.response?.status === 429
                ? 'Too many tries. Please wait a minute and try again.'
                : 'Something went wrong. Please try again.');
        }
        const solution = await lib.solveChallenge({ challenge, deriveKey: lib.pbkdf2.deriveKey });
        if (!solution) throw new ProofError('Something went wrong. Please try again.');
        // The server turns away forms sent back within 3s of the challenge
        // being issued (a script, not a person); only an instant paste could
        // get here that fast, so just wait out the remainder.
        const wait = MIN_FORM_MS - (Date.now() - startedAt);
        if (wait > 0) await new Promise((resolve) => setTimeout(resolve, wait));
        return btoa(JSON.stringify({ challenge, solution }));
    })();
    // Surfaced on submit, not here, so an unawaited rejection isn't reported.
    promise.catch(() => {});
    proof = { promise, startedAt };
}

function freshProof() {
    if (!proof || Date.now() - proof.startedAt > PROOF_MAX_AGE_MS) startProof();
    return proof.promise;
}

function onKeydown(e) {
    if (e.key === 'Escape') close();
}

let returnFocusTo = null;

async function open() {
    returnFocusTo = document.activeElement;
    isOpen.value = true;
    document.addEventListener('keydown', onKeydown);
    freshProof();
    await nextTick();
    messageInput.value?.focus();
}

function close() {
    // Closing mid-send would let the reply land on a hidden form.
    if (submitting.value) return;
    isOpen.value = false;
    document.removeEventListener('keydown', onKeydown);
    if (sent.value) reset();
    returnFocusTo?.focus?.();
    returnFocusTo = null;
}

function clearErrors() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    generalError.value = '';
}

function reset() {
    Object.assign(form, emptyForm());
    clearErrors();
    sent.value = false;
    // The last proof was used by the send; the next open or send makes a new one.
    proof = null;
    if (isOpen.value) {
        freshProof();
        nextTick(() => messageInput.value?.focus());
    }
}

async function send() {
    let altcha;
    try {
        altcha = await freshProof();
    } catch {
        // One retry with a new challenge before giving up (e.g. a network blip).
        proof = null;
        altcha = await freshProof();
    }
    return axios.post('/api/event-suggestions', { ...form, altcha });
}

async function submit() {
    clearErrors();
    if (!form.message.trim()) {
        errors.message = 'Please tell us about the event.';
        return;
    }

    submitting.value = true;
    try {
        try {
            await send();
        } catch (error) {
            // A refused spam check means that proof is spent or stale; quietly
            // try once more with a fresh one before bothering the person.
            if (error.response?.status !== 422 || !error.response.data?.errors?.altcha) throw error;
            proof = null;
            await send();
        }
        sent.value = true;
        proof = null;
    } catch (error) {
        // Whatever went wrong, don't reuse this proof: the server may have spent it.
        proof = null;
        const status = error.response?.status;
        if (error instanceof ProofError) {
            generalError.value = error.message;
        } else if (status === 422) {
            const fieldErrors = error.response.data?.errors || {};
            for (const [field, messages] of Object.entries(fieldErrors)) {
                if (field === 'altcha') generalError.value = messages[0];
                else errors[field] = messages[0];
            }
        } else if (status === 429) {
            generalError.value = "You've sent a lot of suggestions. Please try again in a little while.";
        } else {
            generalError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}

onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>
