<template>
    <!-- Add a venue: the event creation steps' look (Questions? / Save & exit,
         a three-part progress bar, Back / Next). Name, Location (with how to get
         there), Socials, Photos, About, What's there, Review. The first step
         makes it as a draft; each step saves; Finish sends it to EI. -->
    <div class="relative w-full h-screen flex flex-col font-medium">
        <div class="h-24 md:h-[8.8rem] flex-none bg-white z-[1000]">
            <div class="px-8 md:px-[2.6rem] h-full flex justify-between items-center">
                <!-- "Venue" beside the logo: this is adding a venue, not an event. -->
                <a href="/hosting/venues" aria-label="Your venues" class="flex items-center gap-3 md:gap-4 flex-shrink-0">
                    <img src="/storage/website-files/Everything_Immersive_logo_Short.png" alt="EI" class="w-12 h-12">
                    <!-- Nudged down to sit on the logo's letters, not the image's empty top. -->
                    <span class="h-[2.6rem] w-px bg-[#dddddd] translate-y-[0.2rem]" aria-hidden="true"></span>
                    <span class="text-[1.6rem] leading-none font-semibold text-[#222222] translate-y-[0.8rem]">Venue</span>
                </a>
                <div class="flex items-center gap-2 md:gap-3">
                    <a href="mailto:support@everythingimmersive.com?subject=Adding a venue" class="inline-flex items-center h-[3.6rem] md:h-[4rem] px-[1.3rem] md:px-[1.7rem] rounded-full border border-[#dddddd] hover:border-[#222222] bg-white text-[1.3rem] md:text-[1.4rem] font-semibold text-[#222222]">Questions?</a>
                    <button type="button" :disabled="busy" class="inline-flex items-center h-[3.6rem] md:h-[4rem] px-[1.3rem] md:px-[1.7rem] rounded-full border border-[#dddddd] hover:border-[#222222] bg-white text-[1.3rem] md:text-[1.4rem] font-semibold text-[#222222] disabled:opacity-50" data-save-exit @click="saveAndExit">Save &amp; exit</button>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto pb-40 md:pb-20">
            <!-- In the middle of the screen on a computer, as the event steps are; a tall step just scrolls. -->
            <div class="w-full max-w-[64rem] mx-auto px-8 pt-16 md:py-16 md:min-h-full md:flex md:flex-col md:justify-center">
                <p v-if="error" class="mb-6 px-[1.6rem] py-[1.2rem] rounded-[1.2rem] bg-[#fff1f0] text-[#c13515] text-[1.4rem]" role="alert" data-step-error>{{ error }}</p>
                <!-- 1. Name -->
                <main v-if="step === 'Name'">
                    <h2 class="text-black">What is the venue called?</h2>
                    <input v-model="form.name" type="text" autocomplete="off" maxlength="80" placeholder="The Lowry Hotel" class="mt-8 w-full p-6 text-[2.4rem] rounded-[1.2rem] border border-[#dddddd] focus:border-[#222222] focus:shadow-focus-black focus:outline-none" data-venue-name @input="nameMatches = []" @blur="findByName">

                    <!-- Already on EI under this name? A look at its page, where its owner can claim it. Nothing stops you carrying on. -->
                    <div v-if="nameMatches.length" class="mt-4 rounded-[1.2rem] border border-[#dddddd] divide-y divide-[#ebebeb]" data-venue-name-matches>
                        <p class="px-[2rem] pt-[1.6rem] pb-[1.2rem] text-[1.4rem] text-[#6a6a6a] font-normal">{{ nameMatches.some((match) => match.yours) ? 'You may already have this venue, or it is already on Everything Immersive.' : 'We think this venue may already be on EI. Check it out, and claim it there if you own it.' }}</p>
                        <div v-for="match in nameMatches" :key="match.id" class="flex items-center justify-between gap-4 px-[2rem] py-[1.4rem]">
                            <div class="min-w-0">
                                <p class="text-[1.5rem] font-medium text-[#222222] truncate">{{ match.name }}<span v-if="match.room" class="font-normal text-[#6a6a6a]">, {{ match.room }}</span><span v-if="match.status !== 'approved'" class="ml-2 px-2 py-0.5 rounded-full bg-[#f2f2f2] text-[1.2rem] font-medium text-[#6a6a6a]">In review</span></p>
                                <p class="text-[1.3rem] text-[#6a6a6a] font-normal truncate">{{ match.address }}</p>
                            </div>
                            <button v-if="match.yours" type="button" class="flex-shrink-0 px-[1.4rem] py-[0.7rem] rounded-full bg-[#222222] text-white text-[1.3rem] font-semibold" data-go-to-mine @click="goToMine(match)">You have it: go to it</button>
                            <a v-else-if="claimLink(match)" :href="claimLink(match)" target="_blank" rel="noopener" class="flex-shrink-0 px-[1.4rem] py-[0.7rem] rounded-full border border-[#222222] text-[1.3rem] font-semibold text-[#222222] hover:bg-[#f7f7f7]" data-venue-check-it>Check it out</a>
                        </div>
                    </div>
                    <p class="mt-8 mb-2 text-[1.4rem] text-[#6a6a6a] font-normal">Room or space, if it is one of several here (optional)</p>
                    <input v-model="form.room" type="text" autocomplete="off" maxlength="255" placeholder="Studio 2, Rooftop" class="w-full p-4 text-[1.6rem] font-normal rounded-[1.2rem] border border-[#dddddd] focus:border-[#222222] focus:outline-none">

                </main>

                <!-- 2. Location, and how to get there -->
                <main v-else-if="step === 'Location'">
                    <h2 class="text-black">Where is it?</h2>
                    <p class="mt-3 mb-8 text-[1.6rem] leading-[2.2rem] text-[#6a6a6a] font-normal">Search the address, then drag the map if the pin is not quite right.</p>
                    <AddressMap v-model="address" />

                    <div v-if="knownAtAddress && !differentSpace" class="mt-6 px-[2.4rem] py-[2rem] rounded-[1.2rem] border border-[#222222]" data-venue-known>
                        <p v-if="knownAtAddress.yours" class="text-[1.5rem] leading-[2.2rem] text-[#222222] font-normal">You already have a venue at this address: <span class="font-medium">{{ knownAtAddress.name }}<span v-if="knownAtAddress.room">, {{ knownAtAddress.room }}</span></span>.</p>
                        <p v-else class="text-[1.5rem] leading-[2.2rem] text-[#222222] font-normal">We already have a venue at this address: <span class="font-medium">{{ knownAtAddress.name }}<span v-if="knownAtAddress.room">, {{ knownAtAddress.room }}</span></span><span v-if="knownAtAddress.status !== 'approved'" class="text-[#6a6a6a]"> (in review)</span>. Check it out, and claim it there if you own it.</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <!-- Yours: go to it. Someone else's: its page (live, or in review through a
                                 link only from here), where Claim is. -->
                            <button v-if="knownAtAddress.yours" type="button" class="px-6 py-3 rounded-[0.8rem] bg-[#222222] text-white text-[1.4rem] font-medium" data-go-to-mine @click="goToMine(knownAtAddress)">Go to it</button>
                            <a v-else :href="claimLink(knownAtAddress)" target="_blank" rel="noopener" class="px-6 py-3 rounded-[0.8rem] bg-[#222222] text-white text-[1.4rem] font-medium" data-venue-known-open>Check it out</a>
                            <button type="button" class="px-6 py-3 rounded-[0.8rem] border border-[#222222] text-[1.4rem] font-medium" @click="differentSpace = true">I'm a different space here</button>
                        </div>
                    </div>
                    <div v-if="differentSpace" class="mt-6">
                        <p class="mb-2 text-[1.4rem] text-[#6a6a6a] font-normal">Name of this space, so people can tell it from {{ knownAtAddress?.name }}</p>
                        <input v-model="form.room" type="text" autocomplete="off" maxlength="255" placeholder="Studio 2, Rooftop, Back room" class="w-full p-4 text-[1.6rem] font-normal rounded-[1.2rem] border border-[#dddddd] focus:border-[#222222] focus:outline-none" data-venue-room>
                    </div>

                    <h3 class="mt-12 text-[1.8rem] font-medium text-[#222222]">How to get there <span class="text-[#6a6a6a] font-normal text-[1.4rem]">(optional)</span></h3>
                    <textarea v-model="form.directions" rows="3" maxlength="5000" placeholder="The entrance is round the back, past the car park." class="mt-3 w-full p-4 text-[1.6rem] font-normal rounded-[1.2rem] border border-[#dddddd] focus:border-[#222222] focus:outline-none" />
                </main>

                <!-- 3. Socials -->
                <main v-else-if="step === 'Socials'">
                    <h2 class="text-black">Socials</h2>
                    <p class="mt-3 mb-8 text-[1.6rem] text-[#6a6a6a] font-normal">Its website and socials. All optional; they show on the venue's page.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label v-for="field in CONTACT_FIELDS" :key="field.key" class="block">
                            <span class="text-[1.3rem] text-[#6a6a6a] font-normal">{{ field.label }}</span>
                            <!-- Refused by the server: the box goes red with the reason under it, as on event creation. -->
                            <span class="mt-1 flex items-center rounded-[1.2rem] border overflow-hidden" :class="fieldErrors[field.key] ? 'border-red-500 focus-within:shadow-focus-error' : 'border-[#dddddd] focus-within:border-[#222222]'">
                                <span v-if="field.prefix" class="pl-4 text-[#6a6a6a] font-normal flex-shrink-0">{{ field.prefix }}</span>
                                <input v-model="form[field.key]" :type="field.type" :placeholder="field.placeholder" :maxlength="field.max" class="flex-1 min-w-0 p-4 border-0 font-normal focus:ring-0 focus:outline-none" :data-social="field.key" :aria-invalid="Boolean(fieldErrors[field.key])" @input="onContactInput(field.key)">
                            </span>
                            <span v-if="fieldErrors[field.key]" class="block mt-2 text-[1.3rem] leading-[1.8rem] text-red-500 font-normal" :data-field-error="field.key">{{ fieldErrors[field.key] }}</span>
                        </label>
                    </div>
                </main>

                <!-- 4. Photos -->
                <main v-else-if="step === 'Photos'">
                    <PhotoGalleryEdit ref="gallery" :photos="venue?.photos ?? []" :max="venue?.max_photos ?? 4" shape="landscape" noun="venue" />
                </main>

                <!-- 5. About -->
                <main v-else-if="step === 'About'">
                    <h2 class="text-black">Tell people about the venue</h2>
                    <p class="mt-3 text-[1.6rem] text-[#6a6a6a] font-normal">What it is, what it feels like, what is good to know.</p>
                    <textarea v-model="form.description" rows="10" maxlength="5000" class="mt-8 w-full p-4 text-[1.6rem] font-normal rounded-[1.2rem] border border-[#dddddd] focus:border-[#222222] focus:outline-none" data-venue-description />
                    <p class="mt-1 text-right text-[1.3rem] text-[#6a6a6a] font-normal">{{ form.description.length }}/5000</p>
                </main>

                <!-- 6. What's there -->
                <main v-else-if="step === 'Amenities'">
                    <h2 class="text-black">What does this venue offer?</h2>
                    <p class="mt-3 mb-10 text-[1.6rem] text-[#6a6a6a] font-normal">Pick any that apply, or add your own. All optional, and you can change them later.</p>
                    <AmenityTiles v-model="form.amenities" />
                </main>

                <!-- 7. Review -->
                <main v-else-if="step === 'Review'">
                    <h2 class="text-black">Check it over</h2>
                    <p class="mt-3 mb-10 text-[1.6rem] text-[#6a6a6a] font-normal">When you finish, EI takes a look before it shows on the site.</p>
                    <!-- Airbnb's listing review: the card as people will see it, then
                         each part with what it says and Edit to go back to it. -->
                    <div class="rounded-[1.6rem] shadow-[0_6px_20px_rgba(0,0,0,0.12)] overflow-hidden" data-review-card>
                        <img v-if="venue?.image" :src="`${imageUrl}${venue.image}`" alt="" class="w-full aspect-[3/2] object-cover">
                        <div class="p-[2.4rem]">
                            <p class="text-[2.2rem] leading-[2.8rem] font-semibold text-[#222222] break-words">{{ form.name }}<span v-if="form.room" class="text-[#6a6a6a] font-normal">, {{ form.room }}</span></p>
                            <p v-if="addressLine" class="mt-1 text-[1.5rem] text-[#6a6a6a] font-normal">{{ addressLine }}</p>
                        </div>
                    </div>

                    <div class="mt-10 divide-y divide-[#ebebeb]">
                        <section v-for="part in reviewParts" :key="part.step" class="py-[2.4rem] flex items-start justify-between gap-6" :data-review-part="part.step">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-[1.6rem] font-semibold text-[#222222]">{{ part.title }}</h3>
                                <p v-if="part.empty" class="mt-1 text-[1.5rem] text-[#6a6a6a] font-normal">{{ part.empty }}</p>
                                <p v-for="(line, i) in part.lines" :key="i" class="mt-1 text-[1.5rem] leading-[2.2rem] text-[#222222] font-normal break-words whitespace-pre-line" :class="part.clamp ? 'line-clamp-3' : ''">
                                    <span v-if="line.label" class="text-[#6a6a6a]">{{ line.label }}: </span>{{ line.text }}
                                </p>
                                <ul v-if="part.chips" class="mt-3 flex flex-wrap gap-3 list-none p-0 m-0">
                                    <li v-for="(item, i) in part.chips" :key="i" class="flex items-center gap-2 px-[1.2rem] py-[0.6rem] rounded-full border border-[#dddddd] text-[1.4rem] text-[#222222] font-normal">
                                        <component :is="amenityIcon(item)" class="w-[1.8rem] h-[1.8rem] flex-shrink-0" />{{ amenityLabel(item) }}
                                    </li>
                                </ul>
                            </div>
                            <button type="button" class="flex-shrink-0 text-[1.5rem] font-semibold text-[#222222] underline" @click="setStep(part.step)">Edit</button>
                        </section>
                    </div>
                </main>

            </div>
        </div>

        <div class="fixed md:relative bottom-0 left-0 right-0 w-full flex-none bg-white z-[1000]">
            <div class="w-full flex gap-[0.6rem]">
                <div v-for="(part, index) in progressParts" :key="index" class="flex-1 h-[0.6rem] bg-[#ebebeb] overflow-hidden">
                    <div class="h-full bg-[#222222] transition-all duration-300" :style="{ width: `${part}%` }" />
                </div>
            </div>
            <div class="flex justify-between items-center gap-4 px-6 py-6 md:px-[2.6rem] md:py-[1.6rem]">
                <button v-if="index > 0" type="button" class="px-[1rem] py-[1rem] -ml-[1rem] rounded-lg text-[1.6rem] font-semibold text-[#222222] hover:underline hover:bg-[#f7f7f7]" @click="back">Back</button>
                <span v-else></span>
                <button type="button" :disabled="busy" class="h-[4.8rem] px-[3.8rem] rounded-[0.8rem] text-[1.6rem] font-semibold text-white transition-colors" :class="busy ? 'bg-[#dddddd]' : 'bg-[#222222] hover:bg-black'" data-next @click="next">
                    {{ busy ? 'Saving…' : (step === 'Review' ? 'Finish' : 'Next') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import axios from 'axios';
import AddressMap from '@/GlobalComponents/address-map.vue';
import PhotoGalleryEdit from '../Projects/photo-gallery-edit.vue';
import AmenityTiles from './amenity-tiles.vue';
import { amenityIcon, amenityLabel } from './amenity-icons.js';
import { phoneDashes } from '@/composables/phoneDashes';

const props = defineProps({
    // Carrying on (/venues/{slug}/setup): the venue made at the Location step.
    venue: { type: Object, default: null },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const STEPS = ['Name', 'Location', 'Socials', 'Photos', 'About', 'Amenities', 'Review'];
// The progress bar's three parts, as on event creation.
const PHASES = [['Name', 'Location', 'Socials'], ['Photos', 'About'], ['Amenities', 'Review']];
const ADDRESS = ['home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'latitude', 'longitude'];
const CONTACT = ['website', 'email', 'phone', 'instagramHandle', 'facebookHandle', 'tiktokHandle', 'twitterHandle'];
const CONTACT_FIELDS = [
    { key: 'website', label: 'Website', type: 'url', placeholder: 'https://', max: 255 },
    { key: 'email', label: 'Email', type: 'email', placeholder: 'hello@venue.com', max: 255 },
    { key: 'phone', label: 'Phone', type: 'tel', placeholder: '', max: 30 },
    { key: 'instagramHandle', label: 'Instagram', type: 'text', prefix: '@', max: 30 },
    { key: 'facebookHandle', label: 'Facebook', type: 'text', prefix: 'facebook.com/', max: 50 },
    { key: 'tiktokHandle', label: 'TikTok', type: 'text', prefix: '@', max: 30 },
    { key: 'twitterHandle', label: 'X (Twitter)', type: 'text', prefix: '@', max: 15 },
];

const venue = ref(props.venue);
const formFrom = (from) => ({
    name: from?.name ?? '',
    room: from?.room ?? '',
    description: from?.description ?? '',
    directions: from?.directions ?? '',
    amenities: [...(from?.amenities ?? [])],
    ...Object.fromEntries(ADDRESS.map((key) => [key, from?.[key] ?? ''])),
    ...Object.fromEntries(CONTACT.map((key) => [key, from?.[key] ?? ''])),
});
const form = reactive(formFrom(props.venue));

const address = computed({
    get: () => Object.fromEntries(ADDRESS.map((key) => [key, form[key]])),
    set: (value) => {
        ADDRESS.forEach((key) => { form[key] = value[key] ?? ''; });
        checkKnownVenue();
    },
});
const addressLine = computed(() => [form.home && form.home !== form.street ? form.home : null, form.street, form.city, form.country].filter(Boolean).join(', '));

// The review's parts, in the order of the steps, each with what it says.
const reviewParts = computed(() => {
    const contact = CONTACT_FIELDS
        .filter((field) => String(form[field.key] ?? '').trim())
        .map((field) => ({ label: field.label, text: `${field.prefix ?? ''}${String(form[field.key]).trim().replace(/^@/, '')}` }));
    const about = form.description ? [{ text: form.description }] : [];
    const where = [addressLine.value && { text: addressLine.value }, form.directions && { label: 'How to get there', text: form.directions }].filter(Boolean);
    return [
        { step: 'Name', title: 'Name', lines: [{ text: [form.name, form.room].filter(Boolean).join(', ') }] },
        { step: 'Location', title: 'Where it is', lines: where, empty: where.length ? null : 'No address yet' },
        { step: 'Socials', title: 'Website and socials', lines: contact, empty: contact.length ? null : 'None added' },
        { step: 'Photos', title: 'Photos', lines: [], empty: (venue.value?.photos?.length ?? 0) ? `${venue.value.photos.length} ${venue.value.photos.length === 1 ? 'photo' : 'photos'}` : 'None added' },
        { step: 'About', title: 'About it', lines: about, clamp: true, empty: about.length ? null : 'Nothing written yet' },
        { step: 'Amenities', title: 'What it offers', lines: [], chips: form.amenities.length ? form.amenities.filter((item) => amenityLabel(item)) : null, empty: form.amenities.length ? null : 'Nothing picked yet' },
    ];
});

// Carrying on opens on the step the address names (?step=), or Location.
const stepFromUrl = new URLSearchParams(window.location.search).get('step');
const step = ref(props.venue && STEPS.includes(stepFromUrl) ? stepFromUrl : (props.venue ? 'Location' : 'Name'));
const index = computed(() => STEPS.indexOf(step.value));
const progressParts = computed(() => PHASES.map((phase) => (phase.filter((name) => STEPS.indexOf(name) <= index.value).length / phase.length) * 100));

const busy = ref(false);
const error = ref('');
// The server's reasons for a contact box, shown under that box.
const fieldErrors = ref({});
const gallery = ref(null);
const knownAtAddress = ref(null);
const differentSpace = ref(false);

const setStep = (name) => {
    step.value = name;
    error.value = '';
    fieldErrors.value = {};
    if (venue.value) {
        const url = new URL(window.location);
        url.searchParams.set('step', name);
        window.history.replaceState({}, '', url);
    }
};

// Places EI already has that this might be: by name on the first step, by
// address on Location (live or in review, never someone's draft). Claiming one
// opens its page; the draft being added here goes once the claim is sent.
const nameMatches = ref([]);
const known = (params) => axios.get('/api/venues/known', { params: { ...params, except: venue.value?.slug } });

// Looked up once they leave the name box, not while they are still typing.
const findByName = async () => {
    if (form.name.trim().length < 3) {
        nameMatches.value = [];
        return;
    }
    try {
        nameMatches.value = (await known({ name: form.name.trim() })).data;
    } catch {
        nameMatches.value = [];
    }
};

const checkKnownVenue = async () => {
    knownAtAddress.value = null;
    differentSpace.value = false;
    if (!form.street || !form.city) return;
    try {
        const response = await known({ home: form.home, street: form.street, city: form.city });
        knownAtAddress.value = response.data.find((match) => match.at_address) ?? null;
    } catch {
        knownAtAddress.value = null;
    }
};

// One of their own already: open it. The draft being added here is left as it
// is (Back still finds it); an empty one is cleared after a few days
// (ei:prune-blank-drafts).
const goToMine = (match) => {
    window.location.href = match.edit_url;
};

// Its page: live, or in review through a link only from here.
const claimLink = (match) => match.claim_url || match.url;

const fieldsFor = {
    Name: () => ({ name: form.name.trim(), room: form.room.trim() || null }),
    Socials: () => Object.fromEntries(CONTACT.map((key) => [key, form[key] || null])),
    Location: () => ({ ...Object.fromEntries(ADDRESS.map((key) => [key, form[key] || null])), directions: form.directions || null, room: form.room.trim() || null }),
    About: () => ({ description: form.description || null }),
    Amenities: () => ({ amenities: form.amenities }),
};

// A refused save: a contact box's reason goes under that box, anything else at the top.
const failed = (failure) => {
    const errors = failure.response?.data?.errors ?? {};
    const boxes = Object.fromEntries(Object.entries(errors).filter(([key]) => CONTACT.includes(key)).map(([key, list]) => [key, [list].flat()[0]]));
    fieldErrors.value = boxes;
    error.value = Object.keys(boxes).length === Object.keys(errors).length && Object.keys(boxes).length ? '' : firstError(failure);
};
const onContactInput = (key) => {
    if (key === 'phone') form.phone = phoneDashes(form.phone);
    if (fieldErrors.value[key]) fieldErrors.value = { ...fieldErrors.value, [key]: null };
};

const firstError = (failure) => Object.values(failure.response?.data?.errors ?? {}).flat()[0] ?? failure.response?.data?.message ?? 'That did not save. Please try again.';

// What this step must have before moving on.
const stopFor = () => {
    if (step.value === 'Name' && !form.name.trim()) return 'Give the venue a name.';
    if (step.value === 'Location') {
        if (!form.latitude || !form.city) return 'Search the address and pick it from the list.';
        if (knownAtAddress.value && !differentSpace.value) {
            return knownAtAddress.value.yours
                ? `You already have ${knownAtAddress.value.name} here: go to it, or say this is a different space.`
                : `We already have ${knownAtAddress.value.name} here. Check it out, or say you are a different space here.`;
        }
        if (differentSpace.value && !form.room.trim()) return 'Name this space, so people can tell it apart.';
    }
    return null;
};

// Save this step. The first step makes the venue as a draft (only its keeper
// sees it, as an event draft); every step after saves to it, and Finish sends
// it to EI.
const saveStep = async () => {
    if (!venue.value) {
        const made = await axios.post('/hosting/venues', { name: form.name.trim(), room: form.room.trim() || null });
        venue.value = made.data;
        window.history.replaceState({}, '', `/venues/${made.data.slug}/setup?step=${step.value}`);
        return;
    }
    const slug = venue.value.slug;
    if (step.value === 'Photos') {
        if (gallery.value?.changed) {
            const response = await axios.post(`/venues/${slug}/photos`, gallery.value.formData());
            venue.value = response.data;
            gallery.value.reset(response.data.photos);
        }
        return;
    }
    if (step.value === 'Review') {
        const response = await axios.post(`/venues/${slug}/submit`);
        venue.value = response.data;
        return;
    }
    if (fieldsFor[step.value]) {
        const response = await axios.post(`/venues/${slug}`, fieldsFor[step.value]());
        venue.value = response.data;
        // A rename moves its address: keep the page on the new one.
        if (response.data.slug && response.data.slug !== slug) {
            window.history.replaceState({}, '', `/venues/${response.data.slug}/setup?step=${step.value}`);
        }
    }
};

const next = async () => {
    error.value = stopFor() ?? '';
    if (error.value) {
        // The message sits at the top of the step: bring it into view (on
        // Location the map has usually scrolled it away).
        await nextTick();
        document.querySelector('[data-step-error]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    busy.value = true;
    try {
        await saveStep();
        if (step.value === 'Review') {
            window.location.href = '/hosting/venues?added=1';
            return;
        }
        setStep(STEPS[index.value + 1]);
    } catch (failure) {
        failed(failure);
    } finally {
        busy.value = false;
    }
};

const back = () => {
    if (index.value > 0) setStep(STEPS[index.value - 1]);
};

// Save & exit: the first step makes the draft from its name; after that a
// finished step is saved and an unfinished one (no address yet, a question
// unanswered) is simply left for later. Then back to Your venues, where the
// draft waits to be finished.
const saveAndExit = async () => {
    if (!venue.value && !form.name.trim()) {
        window.location.href = '/hosting/venues';
        return;
    }
    busy.value = true;
    try {
        if (!venue.value || (step.value !== 'Review' && !stopFor())) await saveStep();
    } catch (failure) {
        failed(failure);
        busy.value = false;
        return;
    }
    window.location.href = '/hosting/venues';
};

defineExpose({ step, form, next, saveAndExit });
</script>
