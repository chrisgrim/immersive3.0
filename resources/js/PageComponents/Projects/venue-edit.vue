<template>
    <!-- The organizer edit page's layout: section cards on the left, the chosen
         section on the right, Update pinned to the bottom of the panel. -->
    <div class="relative text-1xl font-medium w-full min-h-[calc(100vh-8rem)] md:h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 md:flex md:h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row">
                <!-- Section cards -->
                <div
                    class="flex-shrink-0 overflow-y-auto border-r border-gray-200 w-full lg-air:w-[40rem] xl-air:w-[56rem] lg-air:block"
                    :class="{ 'hidden': currentSection }">
                    <div class="flex items-center justify-center">
                        <nav class="relative flex flex-col items-center flex-shrink-0 w-full mx-auto pt-12">
                            <div class="w-full flex items-center gap-4 py-8 z-50 bg-white p-10 lg-air:max-w-[40rem]">
                                <button
                                    @click="goBack"
                                    aria-label="Go back"
                                    class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0"
                                >
                                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 12H5"/>
                                        <path d="M12 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                <a :href="venue.url" class="ml-4 text-3xl md:text-5xl font-semibold truncate hover:underline">Venue</a>
                            </div>

                            <div class="w-full flex flex-col md:items-center overflow-y-auto max-h-[calc(100vh-11rem)] md:max-h-[calc(100vh-20rem)]">
                                <div class="w-full space-y-10 lg-air:max-w-[40rem] p-10 mb-20">
                                    <button @click="handleNavigation('Name')" :class="cardClass('Name')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Name<span v-if="venue.pending_name" class="ml-[0.6rem] font-normal text-neutral-500" data-renaming-tag>(pending change)</span></h3>
                                        <p class="text-4xl text-neutral-600 mb-4 break-words hyphens-auto overflow-hidden">{{ venue.name || 'No name set' }}</p>
                                        <p class="text-neutral-500 text-2xl line-clamp-3 leading-tight break-words hyphens-auto overflow-hidden">{{ venue.description || 'No description set' }}</p>
                                    </button>

                                    <button @click="handleNavigation('Image')" :class="cardClass('Image')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Photos <span v-if="venue.photos?.length" class="font-normal text-neutral-500">({{ venue.photos.length }})</span></h3>
                                        <div class="flex justify-center mb-8">
                                            <img v-if="venue.image" :src="`${imageUrl}${venue.image}`" class="w-56 h-40 rounded-2xl object-cover" alt="Venue photo" />
                                            <div v-else class="w-56 h-40 rounded-2xl bg-neutral-200 flex items-center justify-center text-neutral-400">No photo</div>
                                        </div>
                                    </button>

                                    <button @click="handleNavigation('Location')" :class="cardClass('Location')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Location</h3>
                                        <p class="text-4xl text-neutral-600 mb-4 break-words">{{ venue.street || venue.home || 'No address set' }}</p>
                                        <p class="text-neutral-500 text-2xl leading-tight">{{ locality(venue) }}</p>
                                        <p v-if="venue.directions" class="mt-2 text-neutral-500 line-clamp-1">How to get there: {{ venue.directions }}</p>
                                    </button>

                                    <button @click="handleNavigation('Amenities')" :class="cardClass('Amenities')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">What's there</h3>
                                        <p class="text-neutral-500 text-2xl line-clamp-3 leading-tight break-words">{{ amenitySummary || 'Nothing picked yet' }}</p>
                                    </button>

                                    <button @click="handleNavigation('Contact')" :class="cardClass('Contact')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Contact &amp; socials</h3>
                                        <div class="space-y-2">
                                            <p v-for="link in contactSummary" :key="link" class="text-neutral-600 truncate">{{ link }}</p>
                                            <p v-if="!contactSummary.length" class="text-neutral-500">No contact links set</p>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>

                <!-- The chosen section -->
                <div
                    class="md:flex-1 flex-col md:h-full w-full md:w-auto transition-all duration-300"
                    :class="currentSection ? 'flex' : 'hidden md:flex'">
                    <div v-if="isMobile && currentSection" class="relative bg-white px-8 pt-12 pb-4">
                        <button
                            @click="handleNavigation(null)"
                            aria-label="Back to sections"
                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                        >
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 12H5"/>
                                <path d="M12 19l-7-7 7-7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 md:overflow-y-auto">
                        <div class="w-full xl:w-2/3 mx-auto" :class="currentSection ? 'pt-4 pb-40 md:pt-40 md:pb-40' : 'pt-20 md:pt-40 md:pb-40'">
                            <div class="p-8">
                                <!-- Kept mounted so unsaved photo changes survive switching sections. -->
                                <main v-show="currentStep === 'Image'" class="w-full min-h-fit">
                                    <PhotoGalleryEdit ref="gallery" :photos="venue.photos" :max="venue.max_photos" shape="landscape" noun="venue" />
                                </main>
                                <main v-if="currentStep === 'Name'" class="w-full min-h-fit">
                                    <h2>What's this venue called?</h2>
                                    <textarea
                                        v-model="form.name"
                                        autocomplete="off"
                                        rows="2"
                                        maxlength="255"
                                        placeholder="Enter the venue's name"
                                        class="text-4xl p-4 border rounded-2xl mt-6 block w-full border-[#222222] focus:border-black focus:shadow-focus-black"
                                    />
                                    <p v-if="venue.pending_name && !venue.renames_directly" class="mt-3 text-[1.4rem] text-[#6a6a6a]" data-pending-name>Waiting for EI to approve this name. Until then the venue is still called "{{ venue.name }}".</p>
                                    <p class="text-black font-medium mt-10 mb-4">Room or space (optional)</p>
                                    <input
                                        v-model="form.room"
                                        autocomplete="off"
                                        type="text"
                                        maxlength="255"
                                        placeholder="Upstairs studio, Hall B..."
                                        class="p-4 border rounded-2xl block w-full border-[#222222] focus:border-black focus:shadow-focus-black"
                                    />
                                    <p class="text-black font-medium mt-10 mb-4">Description</p>
                                    <textarea
                                        v-model="form.description"
                                        rows="9"
                                        maxlength="5000"
                                        placeholder="Tell people about this venue"
                                        class="p-4 border rounded-2xl block w-full border-[#222222] focus:border-black focus:shadow-focus-black"
                                    />
                                    <div class="flex justify-end mt-1 text-gray-500">{{ form.description.length }}/5000</div>
                                </main>


                                <main v-else-if="currentStep === 'Location'" class="w-full min-h-fit">
                                    <h2>Where is the venue?</h2>
                                    <p class="text-[1.6rem] leading-[2.2rem] text-[#6a6a6a] font-normal mt-3 mb-8">Search the address, then drag the map if the pin is not quite right. Listings already here keep their own address.</p>
                                    <!-- The same map as the event Location step -->
                                    <AddressMap v-model="address" />
                                    <!-- How to get there belongs with where it is -->
                                    <h3 class="mt-12 text-[1.8rem] font-medium text-[#222222]">How to get there <span class="text-[#6a6a6a] font-normal text-[1.4rem]">(optional)</span></h3>
                                    <textarea
                                        v-model="form.directions"
                                        rows="4"
                                        maxlength="5000"
                                        placeholder="Parking, transit, which door to use."
                                        class="p-4 border rounded-2xl mt-3 block w-full border-[#dddddd] focus:border-black focus:shadow-focus-black"
                                    />
                                </main>

                                <main v-else-if="currentStep === 'Amenities'" class="w-full min-h-fit">
                                    <h2>What does this venue offer?</h2>
                                    <p class="text-[1.6rem] text-[#6a6a6a] font-normal mt-3 mb-10">Pick any that apply, or add your own. All optional.</p>
                                    <AmenityTiles v-model="form.amenities" />
                                </main>

                                <main v-else-if="currentStep === 'Contact'" class="w-full min-h-fit">
                                    <h2>How can people reach the venue?</h2>
                                    <p class="text-gray-500 mt-4">All optional. They show on the venue's page.</p>
                                    <div class="mt-6 space-y-8">
                                        <label v-for="field in contactFields" :key="field.key" class="block">
                                            <span class="text-black font-medium">{{ field.label }}</span>
                                            <!-- Refused by the server: the box goes red with the reason under it. -->
                                            <div class="mt-2 flex items-center border rounded-2xl overflow-hidden" :class="fieldErrors[field.key] ? 'border-red-500 focus-within:shadow-focus-error' : 'border-[#222222] focus-within:shadow-focus-black'">
                                                <span v-if="field.prefix" class="pl-4 text-neutral-500 flex-shrink-0">{{ field.prefix }}</span>
                                                <input v-model="form[field.key]" :type="field.type" :placeholder="field.placeholder" :maxlength="field.max" class="flex-1 min-w-0 p-4 border-0 focus:ring-0" :aria-invalid="Boolean(fieldErrors[field.key])" @input="onContactInput(field.key)" />
                                            </div>
                                            <span v-if="fieldErrors[field.key]" class="block mt-2 text-1xl text-red-500 leading-tight" :data-field-error="field.key">{{ fieldErrors[field.key] }}</span>
                                        </label>
                                    </div>
                                </main>

                            </div>
                        </div>
                    </div>

                    <div class="flex border-t border-gray-200 bg-white h-32 justify-end items-center fixed bottom-0 inset-x-0 z-[401] md:static md:z-auto">
                        <div class="px-8 py-6 flex gap-4">
                            <button
                                @click="save"
                                :disabled="busy"
                                class="px-6 py-3 rounded-lg transition-colors"
                                :class="busy ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-black text-white hover:bg-gray-800'"
                            >
                                {{ busy ? 'Updating...' : 'Update' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- The organizer edit page's toasts -->
        <div v-if="toast" class="fixed top-36 right-4 z-50 bg-white rounded-xl shadow-lg p-4 max-w-sm border">
            <div class="flex items-center gap-3">
                <svg v-if="toast.ok" class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg v-else class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <p class="text-gray-600">{{ toast.text }}</p>
            </div>
        </div>

        <NavBarMobile v-if="isMobile && !currentSection" :user="user" />
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import NavBarMobile from '../Nav/nav-bar-mobile.vue';
import PhotoGalleryEdit from './photo-gallery-edit.vue';
import AddressMap from '@/GlobalComponents/address-map.vue';
import AmenityTiles from '../Venues/amenity-tiles.vue';
import { amenityLabel } from '../Venues/amenity-icons.js';
import { phoneDashes } from '@/composables/phoneDashes';

const props = defineProps({
    venue: { type: Object, required: true },
    user: { type: Object, default: () => ({}) },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const venue = reactive({ ...props.venue });
const currentStep = ref('Name');
const currentSection = ref(null);
const busy = ref(false);
// The server's reasons for a contact box, shown under that box.
const fieldErrors = ref({});
const onContactInput = (key) => {
    if (key === 'phone') form.phone = phoneDashes(form.phone);
    if (fieldErrors.value[key]) fieldErrors.value = { ...fieldErrors.value, [key]: null };
};
const toast = ref(null);
const isMobile = computed(() => window?.Laravel?.isMobile ?? false);

const ADDRESS = ['home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'latitude', 'longitude'];
const CONTACT = ['website', 'email', 'phone', 'instagramHandle', 'facebookHandle', 'twitterHandle', 'tiktokHandle'];

const formFrom = (from) => ({
    // A new name waiting for EI shows as typed, with a note under it.
    name: (from.renames_directly ? null : from.pending_name) ?? from.name ?? '',
    room: from.room ?? '',
    description: from.description ?? '',
    directions: from.directions ?? '',
    amenities: [...(from.amenities ?? [])],
    ...Object.fromEntries(ADDRESS.map((key) => [key, from[key] ?? ''])),
    ...Object.fromEntries(CONTACT.map((key) => [key, from[key] ?? ''])),
});

const contactFields = [
    { key: 'website', label: 'Website', type: 'url', placeholder: 'https://', max: 255 },
    { key: 'email', label: 'Email', type: 'email', placeholder: 'hello@venue.com', max: 255 },
    { key: 'phone', label: 'Phone', type: 'tel', placeholder: '', max: 30 },
    { key: 'instagramHandle', label: 'Instagram', type: 'text', prefix: '@', max: 30 },
    { key: 'facebookHandle', label: 'Facebook', type: 'text', prefix: 'facebook.com/', max: 50 },
    { key: 'tiktokHandle', label: 'TikTok', type: 'text', prefix: '@', max: 30 },
    { key: 'twitterHandle', label: 'X (Twitter)', type: 'text', prefix: '@', max: 15 },
];

const amenitySummary = computed(() => (venue.amenities ?? []).map(amenityLabel).filter(Boolean).join(' · '));

const contactSummary = computed(() => contactFields.filter((field) => venue[field.key]).map((field) => field.label));

const locality = (from) => [from.city, [from.region, from.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', ');

// Address search, as the event's Location step does it.
const form = reactive(formFrom(props.venue));

// The address part of the form, for the map: read and written as one.
const address = computed({
    get: () => Object.fromEntries(ADDRESS.map((key) => [key, form[key]])),
    set: (value) => ADDRESS.forEach((key) => { form[key] = value[key] ?? ''; }),
});

const cardClass = (step) => [
    'flex flex-col w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
    currentStep.value === step ? 'border-[#222222] shadow-focus-black bg-neutral-50' : 'border border-neutral-200',
];

const handleNavigation = (section) => {
    if (isMobile.value) {
        currentSection.value = section;
    }
    if (section) {
        currentStep.value = section;
    }
};

const goBack = () => window.history.back();

const show = (ok, text) => {
    toast.value = { ok, text };
    setTimeout(() => { toast.value = null; }, ok ? 3000 : 5000);
};

// Take what the server saved. A rename moves the address, so the edit page
// (and the next save) follows it at once.
const adopt = (response) => {
    if (response.data.slug !== venue.slug) {
        window.history.replaceState({}, '', `/venues/${response.data.slug}/edit`);
    }
    Object.assign(venue, response.data);
    Object.assign(form, formFrom(response.data));
};

const run = async (call, done) => {
    busy.value = true;
    try {
        adopt(await call());
        fieldErrors.value = {};
        show(true, done);
    } catch (failure) {
        const errors = failure.response?.data?.errors ?? {};
        fieldErrors.value = Object.fromEntries(Object.entries(errors).filter(([key]) => CONTACT.includes(key)).map(([key, list]) => [key, [list].flat()[0]]));
        show(false, Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'Failed to update the venue. Please try again.');
    } finally {
        busy.value = false;
    }
};

const gallery = ref(null);

// Update saves the words, then the photos if they changed.
const save = () => run(async () => {
    let response = await axios.post(`/venues/${venue.slug}`, form);
    // The words are saved: follow a rename now, so a failed photo save below
    // leaves the page on the right address, its photo changes still waiting.
    adopt(response);
    if (gallery.value?.changed) {
        response = await axios.post(`/venues/${response.data.slug}/photos`, gallery.value.formData());
        gallery.value.reset(response.data.photos);
    }
    return response;
}, 'Venue updated successfully');
</script>
