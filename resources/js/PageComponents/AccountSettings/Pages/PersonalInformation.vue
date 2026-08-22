<template>
    <div>
        <!-- Sizes below are measured directly off Airbnb's live
             account-settings page (not eyeballed): H2 26px/600, row label
             16px/600, row value 14px/400 grey, Edit/Add link 14px/500. -->
        <h2 class="text-4.5xl font-semibold mb-8">Personal information</h2>

        <div v-if="loading" class="py-40 text-center text-neutral-500 text-1xl">Loading…</div>

        <div v-else-if="loadError" class="py-40 text-center">
            <p class="text-neutral-500 text-1xl mb-4">Could not load your personal info.</p>
            <button
                type="button"
                class="px-6 py-2 rounded-full border border-black text-1xl font-medium hover:bg-black hover:text-white transition-colors"
                @click="fetchInfo"
            >
                Try again
            </button>
        </div>

        <div v-else class="divide-y divide-neutral-200">
            <!-- Legal name -->
            <div :class="rowClass('legal-name')">
                <template v-if="editingRow !== 'legal-name'">
                    <div>
                        <p class="text-2.5xl font-semibold text-black mb-1">Legal name</p>
                        <p class="text-1xl text-neutral-500">{{ legalNameDisplay || 'Not provided' }}</p>
                    </div>
                    <button type="button" class="text-1xl font-medium underline hover:no-underline" @click="startEditing('legal-name')">
                        {{ legalNameDisplay ? 'Edit' : 'Add' }}
                    </button>
                </template>
                <form v-else class="w-full" @submit.prevent="saveLegalName">
                    <p class="text-2.5xl font-semibold text-black mb-1">Legal name</p>
                    <p class="text-1xl text-neutral-500 mb-6">
                        Make sure this matches the name on your government-issued ID.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-lg text-neutral-500 mb-2">First name on ID</label>
                            <input v-model="legalFirstName" type="text" maxlength="255" class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-xl focus:outline-none focus:border-black" required>
                        </div>
                        <div>
                            <label class="block text-lg text-neutral-500 mb-2">Last name on ID</label>
                            <input v-model="legalLastName" type="text" maxlength="255" class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-xl focus:outline-none focus:border-black" required>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" :disabled="saving" class="px-6 py-3 rounded-lg bg-black text-white text-1xl font-medium hover:bg-neutral-800 disabled:opacity-50">
                            {{ saving ? 'Saving…' : 'Save and continue' }}
                        </button>
                        <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium hover:bg-neutral-100" @click="cancelEditing">Cancel</button>
                    </div>
                    <p v-if="saveError" class="text-red-600 text-lg mt-4">{{ saveError }}</p>
                </form>
            </div>

            <!-- Preferred first name -->
            <div :class="rowClass('preferred-name')">
                <template v-if="editingRow !== 'preferred-name'">
                    <div>
                        <p class="text-2.5xl font-semibold text-black mb-1">Preferred first name</p>
                        <p class="text-1xl text-neutral-500">{{ name || 'Not provided' }}</p>
                    </div>
                    <button type="button" class="text-1xl font-medium underline hover:no-underline" @click="startEditing('preferred-name')">
                        {{ name ? 'Edit' : 'Add' }}
                    </button>
                </template>
                <form v-else class="w-full" @submit.prevent="savePreferredName">
                    <p class="text-2.5xl font-semibold text-black mb-1">Preferred first name</p>
                    <p class="text-1xl text-neutral-500 mb-6">This is the name other members and organizers will see.</p>
                    <input v-model="name" type="text" maxlength="60" class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-xl mb-6 focus:outline-none focus:border-black" required>
                    <div class="flex gap-4">
                        <button type="submit" :disabled="saving" class="px-6 py-3 rounded-lg bg-black text-white text-1xl font-medium hover:bg-neutral-800 disabled:opacity-50">
                            {{ saving ? 'Saving…' : 'Save and continue' }}
                        </button>
                        <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium hover:bg-neutral-100" @click="cancelEditing">Cancel</button>
                    </div>
                    <p v-if="saveError" class="text-red-600 text-lg mt-4">{{ saveError }}</p>
                </form>
            </div>

            <!-- Email address -->
            <div :class="rowClass('email')">
                <template v-if="editingRow !== 'email'">
                    <div>
                        <p class="text-2.5xl font-semibold text-black mb-1">Email address</p>
                        <p class="text-1xl text-neutral-500">{{ email }}</p>
                        <p class="text-lg mt-1 font-medium" :class="emailVerified ? 'text-green-600' : 'text-amber-600'">
                            {{ emailVerified ? 'Verified' : 'Not verified' }}
                        </p>
                    </div>
                    <button type="button" class="text-1xl font-medium underline hover:no-underline" @click="startEditing('email')">Edit</button>
                </template>
                <div v-else class="w-full">
                    <p class="text-2.5xl font-semibold text-black mb-1">Email address</p>

                    <template v-if="!emailCodeSent">
                        <p class="text-1xl text-neutral-500 mb-6">We'll send a code to confirm your new email.</p>
                        <input v-model="emailInput" type="email" class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-xl mb-6 focus:outline-none focus:border-black" required>
                        <div class="flex gap-4">
                            <button type="button" :disabled="saving || !emailInput" class="px-6 py-3 rounded-lg bg-black text-white text-1xl font-medium hover:bg-neutral-800 disabled:opacity-50" @click="sendEmailCode">
                                {{ saving ? 'Sending…' : 'Send code' }}
                            </button>
                            <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium hover:bg-neutral-100" @click="cancelEditing">Cancel</button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-1xl text-neutral-500 mb-6">Enter the 6-digit code we sent to {{ emailInput }}.</p>
                        <input v-model="emailCode" type="text" inputmode="numeric" maxlength="6" class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-xl mb-6 tracking-widest focus:outline-none focus:border-black" required>
                        <div class="flex gap-4">
                            <button type="button" :disabled="saving || emailCode.length !== 6" class="px-6 py-3 rounded-lg bg-black text-white text-1xl font-medium hover:bg-neutral-800 disabled:opacity-50" @click="confirmEmailCode">
                                {{ saving ? 'Confirming…' : 'Confirm' }}
                            </button>
                            <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium hover:bg-neutral-100" @click="cancelEditing">Cancel</button>
                        </div>
                    </template>
                    <p v-if="emailError" class="text-red-600 text-lg mt-4">{{ emailError }}</p>
                </div>
            </div>

            <!-- Location -->
            <div :class="rowClass('location')">
                <template v-if="editingRow !== 'location'">
                    <div>
                        <p class="text-2.5xl font-semibold text-black mb-1">Location</p>
                        <p class="text-1xl text-neutral-500">{{ locationDisplay || 'Not provided' }}</p>
                    </div>
                    <button type="button" class="text-1xl font-medium underline hover:no-underline" @click="startEditing('location')">
                        {{ locationDisplay ? 'Edit' : 'Add' }}
                    </button>
                </template>
                <form v-else class="w-full" @submit.prevent="saveLocation">
                    <p class="text-2.5xl font-semibold text-black mb-1">Location</p>
                    <p class="text-1xl text-neutral-500 mb-6">
                        Just the city you live in — we never ask for or show your exact address.
                    </p>
                    <div class="relative mb-6" v-click-outside="closeLocationDropdown">
                        <input
                            v-model="locationInput"
                            type="text"
                            placeholder="Search by city"
                            class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-xl focus:outline-none focus:border-black"
                            autocomplete="off"
                            @input="updateLocationPredictions"
                            @focus="locationDropdown = predictions.length > 0"
                        >
                        <ul
                            v-if="locationDropdown && predictions.length"
                            class="absolute z-10 bg-white w-full mt-2 overflow-hidden py-2 list-none rounded-2xl shadow-custom-7 border border-neutral-100"
                        >
                            <li
                                v-for="place in predictions"
                                :key="place.place_id"
                                class="py-3 px-4 flex items-center gap-3 hover:bg-neutral-100 cursor-pointer text-xl"
                                @click="selectLocation(place)"
                            >
                                <svg class="w-5 h-5 flex-shrink-0 text-neutral-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M12 22s-8-5-8-11a8 8 0 1 1 16 0c0 6-8 11-8 11z"/>
                                    <circle cx="12" cy="11" r="3"/>
                                </svg>
                                <span>{{ place.description }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" :disabled="saving || !pendingLocation" class="px-6 py-3 rounded-lg bg-black text-white text-1xl font-medium hover:bg-neutral-800 disabled:opacity-50">
                            {{ saving ? 'Saving…' : 'Save and continue' }}
                        </button>
                        <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium hover:bg-neutral-100" @click="cancelEditing">Cancel</button>
                    </div>
                    <p v-if="saveError" class="text-red-600 text-lg mt-4">{{ saveError }}</p>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { importMapsLibrary } from '@/composables/useGoogleMaps';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective';

const vClickOutside = ClickOutsideDirective;

const loading = ref(true);
const loadError = ref(false);
const saving = ref(false);
const editingRow = ref(null);

const legalFirstName = ref('');
const legalLastName = ref('');
const name = ref('');
const email = ref('');
const emailVerified = ref(false);

// The persisted location, and a locally-selected-but-not-yet-saved one — kept
// separate so the display value doesn't change until Save actually succeeds.
const location = ref(null);
const pendingLocation = ref(null);
const locationInput = ref('');
const locationDropdown = ref(false);
const predictions = ref([]);

const emailInput = ref('');
const emailCodeSent = ref(false);
const emailCode = ref('');
const emailError = ref('');
const saveError = ref('');

// Snapshot of the persisted values for whichever row is being edited, so
// Cancel can restore them — the inputs bind directly to the same refs the
// view uses, so without this a canceled edit stayed visible (and would be
// submitted) the next time that row was saved.
let snapshot = null;

let autoComplete = null;
let predictionsToken = 0;

const legalNameDisplay = computed(() => {
    const full = [legalFirstName.value, legalLastName.value].filter(Boolean).join(' ');
    return full || null;
});

const locationDisplay = computed(() => {
    if (!location.value) return null;
    return [location.value.city, location.value.region].filter(Boolean).join(', ');
});

const rowClass = (key) => [
    'py-8 flex flex-col md:flex-row md:justify-between md:items-start gap-2 transition-opacity',
    editingRow.value && editingRow.value !== key ? 'opacity-40 pointer-events-none' : '',
];

const fetchInfo = async () => {
    loading.value = true;
    loadError.value = false;

    try {
        const { data } = await axios.get('/api/account-settings/personal-info');
        legalFirstName.value = data.legal_first_name || '';
        legalLastName.value = data.legal_last_name || '';
        name.value = data.name || '';
        email.value = data.email || '';
        emailVerified.value = data.email_verified;
        location.value = data.location || null;
    } catch (error) {
        console.error('[account-settings] failed to load personal info', error);
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

const startEditing = (key) => {
    editingRow.value = key;
    saveError.value = '';

    if (key === 'legal-name') {
        snapshot = { legalFirstName: legalFirstName.value, legalLastName: legalLastName.value };
    } else if (key === 'preferred-name') {
        snapshot = { name: name.value };
    } else if (key === 'location') {
        pendingLocation.value = null;
        locationInput.value = locationDisplay.value || '';
        predictions.value = [];
        locationDropdown.value = false;
        initGoogleMaps();
    } else if (key === 'email') {
        emailInput.value = email.value;
        emailCodeSent.value = false;
        emailCode.value = '';
        emailError.value = '';
    }
};

const cancelEditing = () => {
    if (snapshot) {
        if ('legalFirstName' in snapshot) {
            legalFirstName.value = snapshot.legalFirstName;
            legalLastName.value = snapshot.legalLastName;
        } else if ('name' in snapshot) {
            name.value = snapshot.name;
        }
        snapshot = null;
    }
    editingRow.value = null;
    saveError.value = '';
};

const saveLegalName = async () => {
    saving.value = true;
    saveError.value = '';
    try {
        await axios.patch('/api/account-settings/personal-info/legal-name', {
            legal_first_name: legalFirstName.value,
            legal_last_name: legalLastName.value,
        });
        editingRow.value = null;
        snapshot = null;
    } catch (error) {
        saveError.value = 'Could not save that change. Please try again.';
        console.error('[account-settings] failed to save legal name', error);
    } finally {
        saving.value = false;
    }
};

const savePreferredName = async () => {
    saving.value = true;
    saveError.value = '';
    try {
        await axios.patch('/api/account-settings/personal-info/preferred-name', { name: name.value });
        editingRow.value = null;
        snapshot = null;
    } catch (error) {
        saveError.value = 'Could not save that change. Please try again.';
        console.error('[account-settings] failed to save preferred name', error);
    } finally {
        saving.value = false;
    }
};

const saveLocation = async () => {
    if (!pendingLocation.value) return;

    saving.value = true;
    saveError.value = '';
    try {
        const { data } = await axios.patch('/api/account-settings/personal-info/location', {
            city: pendingLocation.value.city,
            region: pendingLocation.value.region,
            country: pendingLocation.value.country,
            lat: pendingLocation.value.lat,
            lng: pendingLocation.value.lng,
        });
        location.value = { city: data.city, region: data.region, country: data.country, lat: data.lat, lng: data.lng };
        editingRow.value = null;
        pendingLocation.value = null;
    } catch (error) {
        saveError.value = 'Could not save that change. Please try again.';
        console.error('[account-settings] failed to save location', error);
    } finally {
        saving.value = false;
    }
};

const initGoogleMaps = async () => {
    if (autoComplete) return;
    try {
        const { AutocompleteService } = await importMapsLibrary('places');
        autoComplete = new AutocompleteService();
    } catch (error) {
        console.error('[account-settings] failed to init Google Places', error);
    }
};

const closeLocationDropdown = () => {
    locationDropdown.value = false;
};

// Undebounced, same as the nav search location field — each keystroke fires
// its own request; a token guards against an older response overwriting a
// newer one if they resolve out of order.
const updateLocationPredictions = async () => {
    const token = ++predictionsToken;

    // Typing again invalidates whatever was already picked — Save stays
    // disabled until a fresh prediction is selected from the list.
    pendingLocation.value = null;

    if (!locationInput.value || !autoComplete) {
        predictions.value = [];
        locationDropdown.value = false;
        return;
    }

    try {
        const result = await autoComplete.getPlacePredictions({
            input: locationInput.value,
            types: ['(cities)'],
        });
        if (token !== predictionsToken) return;
        predictions.value = result?.predictions || [];
        locationDropdown.value = true;
    } catch (error) {
        if (token !== predictionsToken) return;
        console.error('[account-settings] failed to fetch place predictions', error);
        predictions.value = [];
    }
};

const selectLocation = async (place) => {
    locationDropdown.value = false;
    locationInput.value = place.description;

    try {
        const { Place } = await importMapsLibrary('places');
        const placeResult = new Place({ id: place.place_id });
        await placeResult.fetchFields({ fields: ['addressComponents', 'formattedAddress', 'location'] });

        const components = placeResult.addressComponents || [];
        const findComponent = (type) => components.find((c) => c.types?.includes(type));

        const city = findComponent('locality')?.longText
            || findComponent('postal_town')?.longText
            || findComponent('sublocality')?.longText
            || placeResult.formattedAddress?.split(',')[0]?.trim()
            || place.description.split(',')[0].trim();
        const region = findComponent('administrative_area_level_1')?.shortText || null;
        const country = findComponent('country')?.longText || null;

        let lat = null;
        let lng = null;
        if (placeResult.location) {
            lat = typeof placeResult.location.lat === 'function' ? placeResult.location.lat() : placeResult.location.lat;
            lng = typeof placeResult.location.lng === 'function' ? placeResult.location.lng() : placeResult.location.lng;
        }

        pendingLocation.value = { city, region, country, lat, lng };
        locationInput.value = [city, region].filter(Boolean).join(', ');
    } catch (error) {
        console.error('[account-settings] failed to fetch place details', error);
    }
};

// Reuses the existing verify-code endpoints (previously only wired into the
// old name.vue settings tab) — same two-step send-code/confirm-code flow.
const sendEmailCode = async () => {
    saving.value = true;
    emailError.value = '';
    try {
        await axios.post('/users/email/verify', { email: emailInput.value });
        emailCodeSent.value = true;
    } catch (error) {
        emailError.value = error.response?.data?.message || 'Could not send a code to that address.';
        console.error('[account-settings] failed to send email code', error);
    } finally {
        saving.value = false;
    }
};

const confirmEmailCode = async () => {
    saving.value = true;
    emailError.value = '';
    try {
        await axios.post('/users/email/confirm', { email: emailInput.value, code: emailCode.value });
        email.value = emailInput.value;
        emailVerified.value = true;
        editingRow.value = null;
    } catch (error) {
        emailError.value = error.response?.data?.message || 'That code did not match. Try again.';
        console.error('[account-settings] failed to confirm email code', error);
    } finally {
        saving.value = false;
    }
};

onMounted(fetchInfo);
</script>
