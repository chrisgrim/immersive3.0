<template>
    <!-- shadow-[...] md:shadow-none md:border below: Airbnb's own mobile
         profile card sits on the page as an elevated card (soft shadow, no
         visible outline) — the border-only treatment stays for md+, where
         it's already pixel-matched to Airbnb's desktop card. -->
    <div class="border-0 md:border md:border-neutral-200 shadow-[0_2px_16px_rgba(0,0,0,0.08)] md:shadow-none rounded-3xl p-10">
        <!-- flex-row (not flex-col below sm) below: Airbnb's mobile card is a
             two-column layout the whole way down — avatar/name on the left,
             stats stacked on the right — not stats dropping below the avatar
             on narrow screens. -->
        <div class="flex flex-row justify-between items-start gap-4 sm:gap-8 mb-8">
            <div class="flex-1 flex flex-col items-center sm:flex-row sm:items-center gap-4 sm:gap-6 min-w-0">
                <div class="relative flex-shrink-0">
                    <div class="w-[104px] h-[104px] rounded-full overflow-hidden bg-neutral-100">
                        <template v-if="isAbsoluteAvatar">
                            <img class="w-full h-full object-cover" :src="avatarPath" :alt="`${user.name}'s account`">
                        </template>
                        <template v-else-if="avatarPath">
                            <picture>
                                <source type="image/webp" :srcset="`${imageUrl}${avatarPath}?v=${avatarVersion}`">
                                <img class="w-full h-full object-cover" :src="`${imageUrl}${avatarPath.slice(0, -4)}jpg?v=${avatarVersion}`" :alt="`${user.name}'s account`">
                            </picture>
                        </template>
                        <template v-else-if="avatarGravatar">
                            <img :src="avatarGravatar" class="w-full h-full object-cover" :alt="`${user.name}'s account`">
                        </template>
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <span class="text-4xl font-bold text-neutral-400">{{ user.name?.[0]?.toUpperCase() }}</span>
                        </div>
                    </div>

                    <template v-if="isOwner">
                        <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="handleAvatarChange">
                        <button
                            type="button"
                            :disabled="uploadingAvatar"
                            class="absolute bottom-0 right-0 w-10 h-10 rounded-full bg-black text-white flex items-center justify-center border-2 border-white hover:bg-neutral-800 disabled:opacity-50"
                            @click="triggerAvatarUpload"
                        >
                            <span class="sr-only">Change photo</span>
                            <component :is="RiPencilLine" class="w-4 h-4" />
                        </button>
                    </template>
                </div>

                <div class="min-w-0 text-center sm:text-left">
                    <h1 class="text-3xl sm:text-4.5xl font-semibold">{{ user.name }}</h1>
                    <p v-if="avatarError" class="text-red-600 text-lg mt-2">{{ avatarError }}</p>
                </div>
            </div>

            <div class="flex items-start gap-4 sm:gap-8 flex-shrink-0">
                <div class="flex flex-col divide-y divide-neutral-200 w-[96px] flex-shrink-0">
                    <div class="py-3 first:pt-0 last:pb-0">
                        <p class="text-4xl font-semibold">{{ yearsOnEi }}</p>
                        <p class="text-md text-neutral-500 font-medium">Years on EI</p>
                    </div>
                    <template v-if="isOwner && stats">
                        <div class="py-3 first:pt-0 last:pb-0">
                            <p class="text-4xl font-semibold">{{ stats.saved_events_count }}</p>
                            <p class="text-md text-neutral-500 font-medium">Saved events</p>
                        </div>
                        <div class="py-3 first:pt-0 last:pb-0">
                            <p class="text-4xl font-semibold">{{ stats.followed_organizers_count }}</p>
                            <p class="text-md text-neutral-500 font-medium">Following</p>
                        </div>
                    </template>
                    <!-- Non-owner: only shown if THIS user opted their saved-events
                         count into public visibility (see Account Settings > Privacy). -->
                    <div v-if="!isOwner && publicExtras?.saved_events_count !== null && publicExtras?.saved_events_count !== undefined" class="py-3 first:pt-0 last:pb-0">
                        <p class="text-4xl font-semibold">{{ publicExtras.saved_events_count }}</p>
                        <p class="text-md text-neutral-500 font-medium">Saved events</p>
                    </div>
                </div>

                <!-- Hidden below sm: "Account settings" is now one of the mobile
                     nav rows below this card (see navSidebar.vue's chevron
                     rows), same destination as this button — redundant on a
                     screen already this width-constrained. -->
                <a v-if="isOwner" href="/account-settings" class="hidden sm:inline-flex flex-shrink-0 px-5 py-2.5 rounded-full border border-black text-1xl font-medium hover:bg-neutral-100 transition-colors">
                    Edit profile
                </a>
            </div>
        </div>

        <!-- Bio -->
        <div class="border-t border-neutral-200 pt-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-4xl font-semibold">About me</h2>
                <button v-if="isOwner && !editingBio" type="button" class="text-1xl font-medium underline hover:no-underline" @click="startEditingBio">
                    {{ bio ? 'Edit' : 'Add' }}
                </button>
            </div>
            <template v-if="!editingBio">
                <p v-if="bio" class="text-1xl whitespace-pre-wrap">{{ bio }}</p>
                <p v-else class="text-1xl text-neutral-400">{{ isOwner ? 'Add a bio to tell people a bit about yourself.' : 'No bio yet.' }}</p>
            </template>
            <form v-else @submit.prevent="saveBio">
                <textarea
                    v-model="bioDraft"
                    maxlength="500"
                    rows="4"
                    class="w-full border border-neutral-300 rounded-xl px-4 py-3 text-xl mb-3 focus:outline-none focus:border-black"
                    placeholder="Tell people a bit about yourself…"
                ></textarea>
                <p class="text-lg text-neutral-400 mb-4">{{ bioDraft.length }}/500</p>
                <div class="flex gap-4">
                    <button type="submit" :disabled="savingBio" class="px-6 py-3 rounded-lg bg-black text-white text-1xl font-medium hover:bg-neutral-800 disabled:opacity-50">
                        {{ savingBio ? 'Saving…' : 'Save' }}
                    </button>
                    <button type="button" class="px-6 py-3 rounded-lg text-1xl font-medium hover:bg-neutral-100" @click="editingBio = false">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Followed Orgs carousel -->
        <div v-if="isOwner" class="border-t border-neutral-200 pt-8 mt-8">
            <h2 class="text-4xl font-semibold mb-6">Followed organizers</h2>
            <FollowedOrgsCarousel v-if="followedOrganizers.length" :organizers="followedOrganizers" />
            <p v-else class="text-neutral-400 text-1xl">
                Follow an organizer's page to see them here.
            </p>
        </div>
        <!-- Non-owner: only shown if THIS user opted followed organizers into
             public visibility (see Account Settings > Privacy). -->
        <div v-else-if="publicExtras?.followed_organizers?.length" class="border-t border-neutral-200 pt-8 mt-8">
            <h2 class="text-4xl font-semibold mb-6">Followed organizers</h2>
            <FollowedOrgsCarousel :organizers="publicExtras.followed_organizers" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RiPencilLine } from '@remixicon/vue';
import FollowedOrgsCarousel from '../Components/followed-orgs-carousel.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    isOwner: {
        type: Boolean,
        default: false,
    },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;

// Local, mutable copies of the avatar fields so a fresh upload can update
// the picture immediately without mutating the `user` prop directly.
const avatarPath = ref(props.user.thumbImagePath || null);
const avatarGravatar = ref(props.user.gravatar || null);
const avatarVersion = ref(Date.now());
const avatarInput = ref(null);
const uploadingAvatar = ref(false);
const avatarError = ref('');

// Google/GitHub OAuth signups store an absolute avatar URL straight in
// thumbImagePath (see SocialAuthController) rather than a DO Spaces-relative
// path — prepending VITE_IMAGE_URL and rewriting the extension to .jpg would
// turn that into a broken URL, so absolute URLs are used as-is.
const isAbsoluteAvatar = computed(() => /^https?:\/\//.test(avatarPath.value || ''));

const triggerAvatarUpload = () => avatarInput.value?.click();

// Reuses the existing users.update endpoint (POST /users/{user}) — an
// image-only submission short-circuits server-side and returns the fresh
// user record, same contract the old Auth/Pages/image.vue tab relied on.
const handleAvatarChange = async (event) => {
    const file = event.target.files[0];
    event.target.value = '';
    if (!file) return;

    uploadingAvatar.value = true;
    avatarError.value = '';

    const formData = new FormData();
    formData.append('image', file);

    try {
        const { data } = await axios.post(`/users/${props.user.id}`, formData);
        avatarPath.value = data.thumbImagePath || null;
        avatarGravatar.value = null;
        avatarVersion.value = Date.now();
    } catch (error) {
        avatarError.value = 'Could not update your photo. Please try again.';
        console.error('[profile] failed to upload avatar', error);
    } finally {
        uploadingAvatar.value = false;
    }
};

const bio = ref(props.user.blurb || '');
const editingBio = ref(false);
const bioDraft = ref('');
const savingBio = ref(false);

const stats = ref(null);
const followedOrganizers = ref([]);
const publicExtras = ref(null);

// Public for any viewer (matches the original page's behavior) — computed
// locally from the created_at already present on the user prop, rather than
// the owner-only /api/profile/stats endpoint.
const yearsOnEi = computed(() => {
    const created = new Date(props.user.created_at);
    const years = (Date.now() - created.getTime()) / (1000 * 60 * 60 * 24 * 365.25);
    return Math.max(1, Math.ceil(years));
});

const startEditingBio = () => {
    bioDraft.value = bio.value;
    editingBio.value = true;
};

const saveBio = async () => {
    savingBio.value = true;
    try {
        await axios.patch('/api/profile/bio', { blurb: bioDraft.value || null });
        bio.value = bioDraft.value;
        editingBio.value = false;
    } catch (error) {
        console.error('[profile] failed to save bio', error);
    } finally {
        savingBio.value = false;
    }
};

const fetchOwnerExtras = async () => {
    try {
        const [statsRes, orgsRes] = await Promise.all([
            axios.get('/api/profile/stats'),
            axios.get('/api/profile/followed-organizers'),
        ]);
        stats.value = statsRes.data;
        followedOrganizers.value = orgsRes.data.organizers;
    } catch (error) {
        console.error('[profile] failed to load owner extras', error);
    }
};

const fetchPublicExtras = async () => {
    try {
        const { data } = await axios.get(`/api/profile/${props.user.id}/public-extras`);
        publicExtras.value = data;
    } catch (error) {
        console.error('[profile] failed to load public extras', error);
    }
};

onMounted(() => {
    if (props.isOwner) {
        fetchOwnerExtras();
    } else {
        fetchPublicExtras();
    }
});
</script>
