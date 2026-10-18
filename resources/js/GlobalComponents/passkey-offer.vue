<template>
    <teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-[2000]"
            @click.self="notNow"
        >
            <div
                class="relative bg-white w-full md:max-w-[56rem] md:mx-8 md:rounded-3xl rounded-t-3xl shadow-xl text-left"
                role="dialog"
                aria-modal="true"
                aria-labelledby="passkey-offer-title"
            >
                <button type="button" class="absolute top-6 right-6 p-2 rounded-full text-neutral-500 hover:text-black hover:bg-neutral-100" aria-label="Close" @click="notNow">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="px-8 pt-12 pb-10 md:px-16 md:pt-16 md:pb-14">
                    <RiKey2Line class="w-12 h-12 text-neutral-900" />

                    <template v-if="saved">
                        <h2 id="passkey-offer-title" class="mt-6 text-4xl md:text-5xl font-semibold tracking-tight text-neutral-900">You're all set</h2>
                        <p class="mt-4 text-2xl text-neutral-500">Next time, pick your passkey when you sign in. No email code needed.</p>
                        <div class="flex justify-end mt-10">
                            <button type="button" class="px-8 py-4 bg-black text-white rounded-2xl hover:bg-neutral-800 text-xl font-semibold" @click="close">
                                Done
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <h2 id="passkey-offer-title" class="mt-6 text-4xl md:text-5xl font-semibold tracking-tight text-neutral-900">Sign in faster next time</h2>
                        <p class="mt-4 text-2xl text-neutral-500">
                            Save a passkey and sign in with your face, fingerprint or device PIN instead of waiting for an email code.
                        </p>
                        <p v-if="error" class="mt-6 text-xl text-red-600" role="alert">{{ error }}</p>
                        <div class="flex justify-end gap-4 mt-10">
                            <button type="button" class="px-8 py-4 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl font-semibold" @click="notNow">
                                Not now
                            </button>
                            <button
                                type="button"
                                :disabled="saving"
                                class="px-8 py-4 bg-black text-white rounded-2xl hover:bg-neutral-800 text-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="save"
                            >
                                {{ saving ? 'Waiting for your device…' : 'Save a passkey' }}
                            </button>
                        </div>
                        <p class="mt-8 text-lg text-neutral-500">You can add or remove passkeys any time in Account settings, Login &amp; security.</p>
                    </template>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
/**
 * "Sign in faster next time?" The layout only renders this right after an
 * email code or social sign-in by someone with no passkey (PasskeyOffer).
 * "Not now" hides it in this browser for SNOOZE_DAYS: a passkey belongs to
 * a device or password manager, so the answer is per browser too.
 */
import { ref, onMounted } from 'vue';
import { RiKey2Line } from '@remixicon/vue';
import { addPasskey, passkeysSupported } from '@/composables/usePasskeys';

const SNOOZE_KEY = 'ei.passkeyOfferSnoozedUntil';
const SNOOZE_DAYS = 90;

const isOpen = ref(false);
const saving = ref(false);
const saved = ref(false);
const error = ref('');

const snoozed = () => {
    try {
        return Number(localStorage.getItem(SNOOZE_KEY) || 0) > Date.now();
    } catch {
        return false;
    }
};

const snooze = () => {
    try {
        localStorage.setItem(SNOOZE_KEY, String(Date.now() + SNOOZE_DAYS * 86400000));
    } catch {
        // Private mode or blocked storage: it just may ask again next sign-in.
    }
};

const close = () => {
    isOpen.value = false;
};

const notNow = () => {
    snooze();
    close();
};

const save = async () => {
    saving.value = true;
    error.value = '';
    try {
        if (await addPasskey()) {
            saved.value = true;
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    isOpen.value = passkeysSupported() && !!window.Laravel?.user && !snoozed();
});
</script>
