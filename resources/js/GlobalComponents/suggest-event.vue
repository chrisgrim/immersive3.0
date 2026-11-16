<template>
    <button type="button" aria-haspopup="dialog" @click="open" v-bind="$attrs">
        <slot>Tell us about an event</slot>
    </button>

    <teleport to="body">
        <!-- Airbnb's pop-up: close on the left, the title in the middle, the
             fields as cards with their label inside, then a bar with the actions. -->
        <div
            v-if="isOpen"
            class="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-[2000]"
            @click.self="close"
        >
            <div
                class="relative bg-white w-full md:w-[64rem] md:mx-8 rounded-t-[3.2rem] md:rounded-[3.2rem] shadow-2xl flex flex-col max-h-[92vh] text-left"
                role="dialog"
                aria-modal="true"
                aria-labelledby="suggest-event-title"
            >
                <div class="relative flex items-center justify-center h-[6.4rem] px-[2.4rem] border-b border-[#ebebeb] flex-shrink-0">
                    <button type="button" class="absolute left-[1.6rem] w-[4rem] h-[4rem] rounded-full flex items-center justify-center hover:bg-[#f7f7f7]" aria-label="Close" @click="close">
                        <svg class="w-[1.6rem] h-[1.6rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                    <h2 id="suggest-event-title" class="text-[1.6rem] font-semibold text-[#222222]">Tell us about an event</h2>
                </div>

                <!-- Thank-you state -->
                <template v-if="sent">
                    <div class="px-[2.4rem] md:px-[4rem] py-[4rem] text-center">
                        <div class="mx-auto w-[5.6rem] h-[5.6rem] rounded-full bg-[#f7f7f7] flex items-center justify-center">
                            <svg class="w-[2.4rem] h-[2.4rem] text-[#222222]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="mt-[2rem] text-[2.2rem] font-semibold text-[#222222]">Thanks for the tip!</p>
                        <p class="mt-[0.8rem] text-[1.6rem] text-[#6a6a6a]">We got your suggestion and will take a look.</p>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-[2.4rem] md:px-[4rem] py-[1.6rem] border-t border-[#ebebeb] flex-shrink-0">
                        <button type="button" class="text-[1.6rem] font-semibold text-[#222222] underline px-2 py-2 rounded-lg hover:bg-[#f7f7f7]" @click="reset">Tell us about another</button>
                        <button type="button" class="h-[4.8rem] px-[2.4rem] rounded-[0.8rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold" @click="close">Done</button>
                    </div>
                </template>

                <!-- Form. Fields are 16px on phones: iOS zooms the page into any smaller field it focuses. -->
                <form v-else novalidate class="flex flex-col min-h-0" @submit.prevent="submit">
                    <div class="overflow-y-auto px-[2.4rem] md:px-[4rem] py-[2.4rem]">
                        <!-- Creators and PR reps kept using this box to have us list their
                             own show, so point them at the real submission flow first. -->
                        <a href="/hosting/getting-started" class="flex items-center justify-between gap-4 p-[2rem] rounded-[1.6rem] border border-[#dddddd] hover:border-[#222222] transition-colors" data-suggest-hosting>
                            <span>
                                <span class="block text-[1.6rem] font-semibold text-[#222222]">Are you the creator, producer or press agent?</span>
                                <span class="block mt-[0.2rem] text-[1.4rem] text-[#6a6a6a]">Submit your event yourself here instead.</span>
                            </span>
                            <svg class="w-[1.6rem] h-[1.6rem] flex-shrink-0 text-[#222222]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <p class="mt-[2.4rem] text-[1.6rem] text-[#6a6a6a]">Fans: tell us about an event you love. The name, a link and where it is are a big help.</p>

                        <!-- A guest's way back (a signed-in person is reached through their account).
                             Airbnb's stacked fields: one box, a line between them. -->
                        <div v-if="isGuest" class="mt-[1.6rem] rounded-[1.2rem] border divide-y" :class="errors.email ? 'border-[#c13515] divide-[#c13515]' : 'border-[#b0b0b0] divide-[#b0b0b0]'">
                            <label class="block px-[1.6rem] pt-[1rem] pb-[0.8rem] focus-within:rounded-t-[1.2rem] focus-within:shadow-[inset_0_0_0_2px_#222222]">
                                <span class="block text-[1.2rem] text-[#6a6a6a]">Name (optional)</span>
                                <input v-model="form.name" type="text" maxlength="100" autocomplete="name" data-suggest-name
                                    class="w-full p-0 border-0 bg-transparent text-[1.6rem] text-[#222222] focus:ring-0 focus:outline-none">
                            </label>
                            <label class="block px-[1.6rem] pt-[1rem] pb-[0.8rem] focus-within:rounded-b-[1.2rem] focus-within:shadow-[inset_0_0_0_2px_#222222]">
                                <span class="block text-[1.2rem] text-[#6a6a6a]">Email address (optional)</span>
                                <input v-model="form.email" type="email" maxlength="255" autocomplete="email" inputmode="email" data-suggest-email
                                    :aria-invalid="!!errors.email"
                                    class="w-full p-0 border-0 bg-transparent text-[1.6rem] text-[#222222] focus:ring-0 focus:outline-none">
                            </label>
                        </div>
                        <p v-if="isGuest && errors.email" role="alert" class="mt-[0.8rem] text-[1.4rem] text-[#c13515]">{{ errors.email }}</p>
                        <p v-else-if="isGuest" class="mt-[0.8rem] text-[1.3rem] text-[#6a6a6a]">So we can write back if we have a question.</p>

                        <label class="mt-[1.6rem] block px-[1.6rem] pt-[1rem] pb-[0.8rem] rounded-[1.2rem] border focus-within:shadow-[inset_0_0_0_1px_#222222]"
                            :class="errors.message ? 'border-[#c13515]' : 'border-[#b0b0b0] focus-within:border-[#222222]'">
                            <span class="block text-[1.2rem] text-[#6a6a6a]">Message</span>
                            <textarea
                                ref="messageInput"
                                v-model="form.message"
                                :maxlength="MAX_LENGTH"
                                rows="5"
                                aria-labelledby="suggest-event-title"
                                aria-describedby="suggest-event-count suggest-event-error"
                                :aria-invalid="!!errors.message"
                                placeholder="Event name, website, where it is..."
                                class="w-full p-0 border-0 bg-transparent resize-none text-[1.6rem] leading-[2.4rem] text-[#222222] placeholder:text-[#b0b0b0] focus:ring-0 focus:outline-none"
                            ></textarea>
                        </label>
                        <div class="mt-[0.8rem] flex items-start justify-between gap-6">
                            <p id="suggest-event-error" role="alert" class="text-[1.4rem] text-[#c13515]">{{ errors.message || generalError }}</p>
                            <p id="suggest-event-count" class="text-[1.3rem] text-[#6a6a6a] flex-shrink-0">{{ form.message.length }}/{{ MAX_LENGTH }}</p>
                        </div>

                        <!-- Honeypot: invisible to people, tempting to bots. Must stay empty. -->
                        <div class="absolute -left-[9999px] w-px h-px overflow-hidden" aria-hidden="true">
                            <label for="suggest-event-extra">Leave this empty</label>
                            <input id="suggest-event-extra" v-model="form[HONEYPOT]" type="text" :name="HONEYPOT" tabindex="-1" autocomplete="off">
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-[2.4rem] md:px-[4rem] py-[1.6rem] border-t border-[#ebebeb] flex-shrink-0">
                        <button type="button" class="text-[1.6rem] font-semibold text-[#222222] underline px-2 py-2 rounded-lg hover:bg-[#f7f7f7]" @click="close">Cancel</button>
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="h-[4.8rem] px-[2.4rem] rounded-[0.8rem] bg-[#222222] hover:bg-black text-white text-[1.6rem] font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ submitting ? 'Sending…' : 'Send' }}
                        </button>
                    </div>
                </form>
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

const emptyForm = () => ({ name: '', email: '', message: '', [HONEYPOT]: '' });
// Signed in: their account says who they are, so no name or email fields.
const isGuest = !window.Laravel?.user;
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
    if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
        errors.email = 'Please check the email address, or leave it empty.';
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
