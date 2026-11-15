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
                                <a :href="page.public_url" class="ml-4 text-3xl md:text-5xl font-semibold truncate hover:underline">Project</a>
                            </div>

                            <div class="w-full flex flex-col md:items-center overflow-y-auto max-h-[calc(100vh-11rem)] md:max-h-[calc(100vh-20rem)]">
                                <div class="w-full space-y-10 lg-air:max-w-[40rem] p-10 mb-20">
                                    <button v-if="page.link_requests.length" @click="handleNavigation('Requests')" :class="cardClass('Requests')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Asking to join</h3>
                                        <p class="text-4xl text-neutral-600">{{ page.link_requests.length }}</p>
                                        <p class="text-neutral-500 text-2xl">{{ page.link_requests.length === 1 ? 'listing waits' : 'listings wait' }} for your answer</p>
                                    </button>

                                    <button @click="handleNavigation('Name')" :class="cardClass('Name')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Name</h3>
                                        <p class="text-4xl text-neutral-600 mb-4 break-words hyphens-auto overflow-hidden">{{ page.name || 'No name set' }}</p>
                                        <p class="text-neutral-500 text-2xl line-clamp-3 leading-tight break-words hyphens-auto overflow-hidden">{{ page.description || 'No description set' }}</p>
                                    </button>

                                    <button @click="handleNavigation('Photos')" :class="cardClass('Photos')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Photo</h3>
                                        <div class="flex justify-center mb-8">
                                            <img v-if="page.photos[0]" :src="`${imageUrl}${page.photos[0].url}`" class="w-32 h-40 rounded-2xl object-cover" alt="Main photo" />
                                            <div v-else class="w-32 h-40 rounded-2xl bg-neutral-200 flex items-center justify-center text-neutral-400 text-center">No photo</div>
                                        </div>
                                    </button>

                                    <!-- Writers: the backend keeps them, the screen waits for a later iteration. -->
                                    <button v-if="false" @click="handleNavigation('Writers')" :class="cardClass('Writers')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Writers</h3>
                                        <p class="text-neutral-500 text-2xl line-clamp-3 leading-tight">{{ form.writers.map((writer) => writer.artist).join(', ') || 'No writers set' }}</p>
                                    </button>

                                    <button @click="handleNavigation('Runs')" :class="cardClass('Runs')">
                                        <h3 class="text-xl font-semibold mb-4 text-black">Runs</h3>
                                        <p class="text-4xl text-neutral-600">{{ page.runs.length }}</p>
                                        <p class="text-neutral-500 text-2xl">{{ page.runs.length === 1 ? 'listing' : 'listings' }} on this project</p>
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
                                <main v-show="currentStep === 'Photos'" class="w-full min-h-fit">
                                    <PhotoGalleryEdit ref="gallery" :photos="page.photos" :max="page.max_photos" shape="portrait" noun="project" />
                                </main>

                                <main v-if="currentStep === 'Requests'" class="w-full min-h-fit">
                                    <h2>Asking to join</h2>
                                    <p class="text-gray-500 mt-4">Listings that want to be a run of this project.</p>
                                    <div class="mt-8 space-y-6">
                                        <div v-for="request in page.link_requests" :key="request.id" class="p-8 border border-neutral-200 rounded-3xl flex items-center justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="text-2xl text-black">{{ request.event?.name }}</p>
                                                <p class="text-neutral-500">{{ request.event?.organizer?.name }}</p>
                                                <p v-if="request.event?.status === 'r'" class="text-neutral-500">Waiting for EI to approve the listing first.</p>
                                            </div>
                                            <div class="flex gap-3 flex-shrink-0">
                                                <button type="button" class="px-6 py-3 rounded-lg bg-black text-white hover:bg-gray-800 disabled:opacity-50" :disabled="busy || !['p', 'e'].includes(request.event?.status)" @click="decide(request, 'approve')">Approve</button>
                                                <button type="button" class="px-6 py-3 rounded-lg border border-black hover:bg-gray-100 disabled:opacity-50" :disabled="busy" @click="decide(request, 'reject')">Decline</button>
                                            </div>
                                        </div>
                                    </div>
                                </main>

                                <main v-else-if="currentStep === 'Name'" class="w-full min-h-fit">
                                    <h2>What's the project called?</h2>
                                    <textarea
                                        v-model="form.name"
                                        rows="2"
                                        maxlength="255"
                                        placeholder="Enter the project's name"
                                        class="text-4xl p-4 border rounded-2xl mt-6 block w-full border-[#222222] focus:border-black focus:shadow-focus-black"
                                    />
                                    <p class="text-black font-medium mt-10 mb-4">About the project</p>
                                    <textarea
                                        v-model="form.description"
                                        rows="9"
                                        maxlength="5000"
                                        placeholder="What the project is, wherever it plays"
                                        class="p-4 border rounded-2xl block w-full border-[#222222] focus:border-black focus:shadow-focus-black"
                                    />
                                    <div class="flex justify-end mt-1 text-gray-500">{{ form.description.length }}/5000</div>
                                </main>

                                <main v-else-if="currentStep === 'Writers'" class="w-full min-h-fit">
                                    <h2>Who wrote it?</h2>
                                    <p class="text-gray-500 mt-4">The project keeps its writers; each run lists its own cast and crew.</p>
                                    <div class="mt-8 space-y-4">
                                        <div v-for="(writer, index) in form.writers" :key="writer.id ?? `new-${index}`" class="flex items-center gap-4 p-4 border border-neutral-200 rounded-2xl">
                                            <span class="text-2xl flex-1 min-w-0 truncate">{{ writer.artist }}</span>
                                            <input v-model="writer.role" type="text" placeholder="Role" class="w-60 p-3 border rounded-xl border-[#222222]" />
                                            <button type="button" aria-label="Remove this writer" class="w-10 h-10 rounded-full hover:bg-neutral-100 flex items-center justify-center" @click="form.writers.splice(index, 1)">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <artist-picker @picked="addWriter" />
                                    </div>
                                </main>

                                <main v-else-if="currentStep === 'Runs'" class="w-full min-h-fit">
                                    <h2>Runs of this project</h2>
                                    <div class="mt-8 space-y-4">
                                        <div v-for="run in page.runs" :key="run.id" class="p-6 border border-neutral-200 rounded-2xl flex items-center justify-between gap-4">
                                            <div class="min-w-0">
                                                <a :href="run.public_url" class="text-2xl text-black hover:underline">{{ run.name }}</a>
                                                <p class="text-neutral-500">{{ run.organizer?.name }}</p>
                                            </div>
                                            <button type="button" class="px-6 py-3 rounded-lg border border-black hover:bg-gray-100 flex-shrink-0 disabled:opacity-50" :disabled="busy" @click="unlink(run)">Take off this project</button>
                                        </div>
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
import ArtistPicker from './artist-picker.vue';
import PhotoGalleryEdit from './photo-gallery-edit.vue';
import NavBarMobile from '../Nav/nav-bar-mobile.vue';

const props = defineProps({
    project: { type: Object, required: true },
    user: { type: Object, default: () => ({}) },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const page = ref(props.project);
const busy = ref(false);
const toast = ref(null);
const gallery = ref(null);
const currentStep = ref(props.project.link_requests.length ? 'Requests' : 'Name');
const currentSection = ref(null);
const isMobile = computed(() => window?.Laravel?.isMobile ?? false);

const formFrom = (project) => ({
    name: project.name,
    description: project.description ?? '',
    writers: project.writers.map((writer) => ({ ...writer })),
});
const form = reactive(formFrom(props.project));

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

const addWriter = (artist) => {
    form.writers.push({ artist_id: artist.id, artist: artist.name, role: 'Writer' });
};

const run = async (call, done) => {
    busy.value = true;
    try {
        const response = await call();
        page.value = response.data;
        Object.assign(form, formFrom(response.data));
        show(true, done);
    } catch (failure) {
        show(false, Object.values(failure.response?.data?.errors ?? {}).flat()[0]
            ?? failure.response?.data?.message
            ?? 'That did not work. Please try again.');
    } finally {
        busy.value = false;
    }
};

const base = () => `/hosting/projects/${page.value.slug}`;

// Update saves the words and writers, then the photos if they changed.
const save = () => run(async () => {
    let response = await axios.post(base(), form);
    // The words are saved: take them now (a rename moves the address), so a
    // failed photo save below leaves the page right, its photo change still waiting.
    page.value = response.data;
    if (gallery.value?.changed) {
        response = await axios.post(`/hosting/projects/${response.data.slug}/photos`, gallery.value.formData());
        gallery.value.reset(response.data.photos);
    }
    return response;
}, 'Project updated successfully');

const decide = (request, verdict) => run(() => axios.post(`${base()}/link-requests/${request.id}/${verdict}`), verdict === 'approve' ? 'Joined the project' : 'Declined');

const unlink = (listing) => {
    if (confirm(`Take "${listing.name}" off this project? It becomes a plain listing at its own address.`)) {
        run(() => axios.delete(`${base()}/instances/${listing.slug}`), 'Taken off the project');
    }
};
</script>
