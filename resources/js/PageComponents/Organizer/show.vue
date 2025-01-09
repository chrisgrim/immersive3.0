<template>
    <div class="m-auto w-full px-8 py-8 md:py-8 md:px-12 lg:py-0 lg:px-32 lg:max-w-screen-xl lg:pt-24">
        <div class="flex flex-col md:flex-row md:gap-16">
            <!-- Left Column -->
            <div class="md:w-[36rem] space-y-14 mb-16 md:mb-20">
                <!-- Profile Card -->
                <div class="flex flex-row shadow-custom-6 w-full p-8 py-16 rounded-3xl gap-8">
                    <!-- Left Column - Image and Name -->
                    <div class="flex flex-col items-center w-2/3">
                        <!-- Profile Image -->
                        <div class="w-44 flex-shrink-0">
                            <div class="relative w-full">
                                <div class="relative w-full aspect-square">
                                    <!-- Add file input if user has permission -->
                                    <input 
                                        v-if="canEdit"
                                        type="file"
                                        id="image-upload"
                                        @change="handleImageUpload"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="hidden"
                                    />
                                    <label 
                                        v-if="canEdit"
                                        for="image-upload" 
                                        class="absolute inset-0 flex justify-center items-center cursor-pointer z-10"
                                    >
                                        <div class="w-full h-full rounded-full overflow-hidden hover:border-neutral-300 transition-all shadow-sm">
                                            <!-- Loading Spinner -->
                                            <div v-if="isUploading" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                                                <svg class="animate-spin h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                            <picture v-if="organizer.images?.length">
                                                <source 
                                                    type="image/webp" 
                                                    :srcset="`${imageUrl}${organizer.images[0].large_image_path}`"
                                                > 
                                                <img 
                                                    class="w-full h-full object-cover"
                                                    :src="`${imageUrl}${organizer.images[0].large_image_path}`"
                                                    :alt="`${organizer.name} organizer`"
                                                >
                                            </picture>
                                            <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <!-- Non-editable image -->
                                    <div v-else class="w-full h-full rounded-full overflow-hidden shadow-sm">
                                        <picture v-if="organizer.images?.length">
                                            <source 
                                                type="image/webp" 
                                                :srcset="`${imageUrl}${organizer.images[0].large_image_path}`"
                                            > 
                                            <img 
                                                class="w-full h-full object-cover"
                                                :src="`${imageUrl}${organizer.images[0].large_image_path}`"
                                                :alt="`${organizer.name} organizer`"
                                            >
                                        </picture>
                                        <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Name - always text -->
                        <div class="w-full flex justify-center px-4">
                            <h1 class="text-3xl text-black font-medium leading-tight mt-8 text-center break-words word-break-break-word md:max-w-[25rem] overflow-hidden" lang="en">
                                {{ organizer.name }}
                            </h1>
                        </div>
                    </div>

                    <!-- Right Column - Info -->
                    <div class="flex-1 flex flex-col space-y-8 m-auto">
                        <!-- Stats -->
                        <div class="flex flex-col items-start">
                            <p class="text-5xl font-semibold text-gray-900">
                                {{ organizer.events?.length || 0 }}
                            </p>
                            <p class="text-md font-bold text-gray-600">
                                Events
                            </p>
                        </div>
                        
                        <div class="w-24 h-px bg-gray-200"></div>
                        
                        <div class="flex flex-col items-start">
                            <p class="text-5xl font-semibold text-gray-900">
                                {{ calculateYearsOnEI }}
                            </p>
                            <p class="text-md font-bold text-gray-600">
                                Years on EI
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div v-if="canEdit || hasSocialLinks" class="w-full border border-neutral-200 rounded-3xl p-8">
                    <!-- Editor View - Always vertical -->
                    <div v-if="canEdit" class="space-y-8">
                        <!-- Website -->
                        <div class="flex items-center gap-4 group">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiSearchLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <div class="flex-1 leading-none">
                                <input 
                                    v-model="organizerData.website"
                                    @blur="handleUpdate('website')"
                                    placeholder="Add website URL"
                                    :class="[
                                        'text-lg text-gray-600 p-4 border w-full bg-transparent focus:outline-none focus:ring-2 rounded-2xl',
                                        {
                                            'border-red-500 focus:ring-red-500': showWebsiteError,
                                            'border-neutral-300 focus:ring-indigo-500': !showWebsiteError
                                        }
                                    ]"
                                />
                                <p v-if="showWebsiteError" class="text-red-500 text-sm mt-1 px-4">
                                    The website URL is not valid
                                </p>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="flex items-center gap-4 group">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiMailLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <div class="flex-1 leading-none">
                                <input 
                                    v-model="organizerData.email"
                                    @blur="handleUpdate('email')"
                                    placeholder="Add email address"
                                    class="text-lg text-gray-600 p-4 border b w-full bg-transparent focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-2xl"
                                />
                            </div>
                        </div>

                        <!-- Twitter -->
                        <div class="flex items-center gap-4 group">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiTwitterLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <div class="flex-1 leading-none">
                                <input 
                                    v-model="organizerData.twitterHandle"
                                    @input="handleSocialInput('twitterHandle', 15)"
                                    @blur="handleUpdate('twitterHandle')"
                                    placeholder="Add Twitter handle"
                                    :class="[
                                        'text-lg text-gray-600 p-4 border w-full bg-transparent focus:outline-none focus:ring-2 rounded-2xl',
                                        {
                                            'border-red-500 focus:ring-red-500': isSocialNearLimit.twitter || showSocialError.twitter,
                                            'border-neutral-300 focus:ring-indigo-500': !isSocialNearLimit.twitter && !showSocialError.twitter
                                        }
                                    ]"
                                />
                            </div>
                        </div>

                        <!-- Instagram -->
                        <div class="flex items-center gap-4 group">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiInstagramLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <div class="flex-1 leading-none">
                                <input 
                                    v-model="organizerData.instagramHandle"
                                    @input="handleSocialInput('instagramHandle', 30)"
                                    @blur="handleUpdate('instagramHandle')"
                                    placeholder="Add Instagram handle"
                                    :class="[
                                        'text-lg text-gray-600 p-4 border w-full bg-transparent focus:outline-none focus:ring-2 rounded-2xl',
                                        {
                                            'border-red-500 focus:ring-red-500': isSocialNearLimit.instagram || showSocialError.instagram,
                                            'border-neutral-300 focus:ring-indigo-500': !isSocialNearLimit.instagram && !showSocialError.instagram
                                        }
                                    ]"
                                />
                            </div>
                        </div>

                        <!-- Facebook -->
                        <div class="flex items-center gap-4 group">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiFacebookBoxLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <div class="flex-1 leading-none">
                                <input 
                                    v-model="organizerData.facebookHandle"
                                    @input="handleSocialInput('facebookHandle', 75)"
                                    @blur="handleUpdate('facebookHandle')"
                                    placeholder="Add Facebook handle"
                                    :class="[
                                        'text-lg text-gray-600 p-4 border w-full bg-transparent focus:outline-none focus:ring-2 rounded-2xl',
                                        {
                                            'border-red-500 focus:ring-red-500': isSocialNearLimit.facebook || showSocialError.facebook,
                                            'border-neutral-300 focus:ring-indigo-500': !isSocialNearLimit.facebook && !showSocialError.facebook
                                        }
                                    ]"
                                />
                            </div>
                        </div>

                        <!-- Patreon -->
                        <div class="flex items-center gap-4 group">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiPatreonLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <div class="flex-1 leading-none">
                                <input 
                                    v-model="organizerData.patreon"
                                    @input="handleSocialInput('patreon', 30)"
                                    @blur="handleUpdate('patreon')"
                                    placeholder="Add Patreon handle"
                                    :class="[
                                        'text-lg text-gray-600 p-4 border w-full bg-transparent focus:outline-none focus:ring-2 rounded-2xl',
                                        {
                                            'border-red-500 focus:ring-red-500': isSocialNearLimit.patreon || showSocialError.patreon,
                                            'border-neutral-300 focus:ring-indigo-500': !isSocialNearLimit.patreon && !showSocialError.patreon
                                        }
                                    ]"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Viewer View - Horizontal on mobile -->
                    <div v-else class="flex md:block md:space-y-8 justify-between">
                        <!-- Website -->
                        <a v-if="organizer.website" 
                           :href="organizer.website" 
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiSearchLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                Website
                            </span>
                        </a>

                        <!-- Email -->
                        <a v-if="organizer.email" 
                           :href="`mailto:${organizer.email}`"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiMailLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                {{ organizer.email }}
                            </span>
                        </a>

                        <!-- Twitter -->
                        <a v-if="organizer.twitterHandle" 
                           :href="`https://twitter.com/${organizer.twitterHandle}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiTwitterLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                @{{ organizer.twitterHandle }}
                            </span>
                        </a>

                        <!-- Instagram -->
                        <a v-if="organizer.instagramHandle" 
                           :href="`https://instagram.com/${organizer.instagramHandle}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiInstagramLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                @{{ organizer.instagramHandle }}
                            </span>
                        </a>

                        <!-- Facebook -->
                        <a v-if="organizer.facebookHandle" 
                           :href="`https://facebook.com/${organizer.facebookHandle}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiFacebookBoxLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                {{ organizer.facebookHandle }}
                            </span>
                        </a>

                        <!-- Patreon -->
                        <a v-if="organizer.patreon" 
                           :href="`https://patreon.com/${organizer.patreon}`"
                           target="_blank"
                           rel="nofollow noopener noreferrer"
                           class="flex items-center md:gap-4 group md:w-full">
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 group-hover:bg-neutral-200 transition-colors">
                                <component :is="RiPatreonLine" class="w-6 h-6 text-neutral-700" />
                            </div>
                            <span class="text-lg text-gray-600 group-hover:text-gray-900 transition-colors hidden md:inline">
                                {{ organizer.patreon }}
                            </span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column - Content -->
            <div class="flex-1 leading-none">
                <!-- Description -->
                <div class="whitespace-pre-wrap mb-8">
                    <!-- Editable Content for authorized users -->
                    <template v-if="canEdit">
                        <div class="space-y-8">
                            <div>
                                <input 
                                    v-model="organizerData.name"
                                    @input="handleNameInput"
                                    @blur="handleUpdate('name')"
                                    :disabled="hasPendingNameChange"
                                    :class="[
                                        'text-black border p-4 text-5xl font-bold leading-tight w-full bg-transparent focus:outline-none focus:ring-2 rounded-2xl',
                                        {
                                            'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showNameError,
                                            'border-yellow-500 focus:border-yellow-500': isNameApproachingLimit && !showNameError,
                                            'border-neutral-300 focus:border-black focus:shadow-[0_0_0_1.5px_black]': !showNameError && !isNameApproachingLimit,
                                            'opacity-50 cursor-not-allowed': hasPendingNameChange
                                        }
                                    ]"
                                    placeholder="Organization name"
                                />
                                
                                <!-- Name Character Count -->
                                <div class="flex justify-end mt-1" 
                                     :class="{
                                         'text-red-500': isNameNearLimit,
                                         'text-yellow-500': isNameApproachingLimit,
                                         'text-gray-500': !isNameNearLimit && !isNameApproachingLimit
                                     }">
                                    {{ organizerData.name?.length || 0 }}/60
                                </div>

                                <!-- Name Error Messages -->
                                <p v-if="showNameMaxLengthError" 
                                   class="text-red-500 text-sm mt-1 px-4">
                                    Organization name is too long.
                                </p>
                                <p v-if="showNameRequiredError" 
                                   class="text-red-500 text-sm mt-1 px-4">
                                    Organization name is required
                                </p>
                            </div>
                            
                            <div>
                                <textarea 
                                    v-model="organizerData.description"
                                    @blur="handleUpdate('description')"
                                    @input="handleDescriptionInput"
                                    :class="[
                                        'text-3xl w-full border rounded-2xl p-4 bg-transparent focus:outline-none focus:ring-2',
                                        showDescriptionError 
                                            ? 'border-red-500 focus:ring-red-500' 
                                            : 'border-neutral-300 focus:ring-indigo-500'
                                    ]"
                                    placeholder="Add a description of your organization..."
                                ></textarea>
                                <p v-if="showDescriptionError" class="text-red-500 text-sm mt-1">
                                    {{ v$.organizerData.description.$errors[0].$message }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-16">
                            <h3 class="text-black text-4xl font-bold leading-tight mb-12">
                                Events by {{ organizer.name }}
                            </h3>
                            <EventListings 
                                :items="organizer.events"
                                :user="user"
                            />
                        </div>
                    </template>

                    <!-- Static Content for non-editors -->
                    <template v-else>
                        <h3 class="text-black text-5xl font-bold leading-tight">
                            About {{ organizer.name }}
                        </h3>
                        <p class="text-3xl mt-16 mb-24">{{ organizer.description }}</p>
                        
                        <!-- Events Section -->
                        <div class="mt-16">
                            <h3 class="text-black text-4xl font-bold leading-tight mb-12">
                                Events by {{ organizer.name }}
                            </h3>
                            <EventListings 
                                :items="organizer.events"
                                :user="user"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <ToastNotification 
        v-model:show="showToast"
        message="Organizer updated successfully"
    />

    <teleport to="body">
        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Name Change Request</h2>
                    <p class="text-gray-500 font-normal">Submit a request to change your organization's name</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-6">
                        <p class="text-gray-600">
                            Changing the organization name requires admin approval. Once submitted:
                        </p>
                        <ul class="text-gray-600 list-disc ml-5">
                            <li>The current name will remain until approved</li>
                            <li>The name field will be locked until a decision is made</li>
                        </ul>
                        <div class="mt-8">
                            <p class="text-gray-500 font-normal mb-4">New Name</p>
                            <p class="text-4xl font-bold">
                                {{ pendingNameUpdate }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="cancelNameChange"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="confirmNameChange"
                            class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                        >
                            Submit Request
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref, computed } from 'vue';
import { 
    RiSearchLine,
    RiTwitterLine,
    RiInstagramLine,
    RiFacebookBoxLine,
    RiMailLine,
    RiPatreonLine
} from '@remixicon/vue';
import axios from 'axios';
import EventListings from '@/GlobalComponents/Grid/event-grid.vue'
import useVuelidate from '@vuelidate/core';
import { required, maxLength, email, url } from '@vuelidate/validators';
import ToastNotification from '@/GlobalComponents/toast-notifications.vue';

// Initialize with default values
const defaultOrganizer = {
    name: '',
    description: '',
    email: '',
    website: '',
    twitterHandle: '',
    instagramHandle: '',
    facebookHandle: '',
    patreon: '',
    images: []
}

const props = defineProps({
    organizer: {
        type: Object,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
});

// Create reactive copy of organizer
const organizerData = ref({ ...defaultOrganizer, ...props.organizer });
const organizerBeforeEdit = ref({ ...defaultOrganizer, ...props.organizer });

// Update validation rules structure to match the data structure
const rules = {
    organizerData: {
        name: { 
            required,
            maxLength: maxLength(60)
        },
        description: { 
            required,
            maxLength: maxLength(2000)
        },
        email: { 
            email 
        },
        website: { 
            url 
        },
        twitterHandle: {
            maxLength: maxLength(15) // Twitter's official limit
        },
        instagramHandle: {
            maxLength: maxLength(30) // Instagram's official limit
        },
        facebookHandle: {
            maxLength: maxLength(75) // Facebook's username limit
        },
        patreon: {
            maxLength: maxLength(30) // Patreon's username limit
        }
    }
};

// Setup Vuelidate with the correct structure
const v$ = useVuelidate(rules, { organizerData });

// State management
const errors = ref({});
const showToast = ref(false);
const isUploading = ref(false);

// Add these refs for modal state
const showModal = ref(false);
const pendingNameUpdate = ref(null);

// Update computed properties to match the new structure
const showNameError = computed(() => {
    return (v$.value.organizerData.name.$dirty && v$.value.organizerData.name.$error) || 
           isNameNearLimit.value;
});

const showDescriptionError = computed(() => {
    return v$.value.organizerData.description.$dirty && v$.value.organizerData.description.$error;
});

const showEmailError = computed(() => {
    return v$.value.organizerData.email.$dirty && v$.value.organizerData.email.$error;
});

const showWebsiteError = computed(() => {
    return v$.value.organizerData.website.$dirty && v$.value.organizerData.website.$error;
});

const isDescriptionNearLimit = computed(() => {
    const count = organizerData.value.description?.length || 0;
    return count > 1900;
});

// Add these computed properties
const canEdit = computed(() => {
    return ['owner', 'admin', 'moderator'].includes(organizerData.value.user_role);
});

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);

const calculateYearsOnEI = computed(() => {
    const joinDate = new Date(organizerData.value.created_at);
    const now = new Date();
    const years = now.getFullYear() - joinDate.getFullYear();
    
    if (
        now.getMonth() < joinDate.getMonth() ||
        (now.getMonth() === joinDate.getMonth() && now.getDate() < joinDate.getDate())
    ) {
        return years - 1;
    }
    return years;
});

// Add these computed properties for name validation
const showNameMaxLengthError = computed(() => {
    return v$.value.organizerData.name.$dirty && v$.value.organizerData.name.maxLength.$invalid;
});

const showNameRequiredError = computed(() => {
    return v$.value.organizerData.name.$dirty && v$.value.organizerData.name.required.$invalid;
});

const isNameNearLimit = computed(() => {
    const count = organizerData.value.name?.length || 0;
    return count >= 100;
});

// Add this computed property for warning state
const isNameApproachingLimit = computed(() => {
    const count = organizerData.value.name?.length || 0;
    return count > 90 && count < 100;
});

// Add handleNameInput method
const handleNameInput = () => {
    v$.value.organizerData.name.$touch();
    if (organizerData.value.name?.length > 100) {
        organizerData.value.name = organizerData.value.name.slice(0, 100);
    }
};

// Methods
const handleUpdate = async (field) => {
    try {
        errors.value = {};
        
        await v$.value.organizerData[field].$touch();
        
        // Special handling for empty required fields
        if ((field === 'description' || field === 'name') && !organizerData.value[field]?.trim()) {
            organizerData.value[field] = organizerBeforeEdit.value[field];
            v$.value.organizerData[field].$reset();
            return;
        }
        
        if (v$.value.organizerData[field].$error) {
            return;
        }

        const isValid = await v$.value.$validate();
        if (!isValid) {
            return;
        }

        // Compare current value with original value
        if (organizerData.value[field] === organizerBeforeEdit.value[field]) {
            v$.value.organizerData[field].$reset(); // Reset dirty state if no actual change
            return;
        }

        // If it's a name change, show confirmation modal
        if (field === 'name') {
            pendingNameUpdate.value = organizerData.value.name;
            showModal.value = true;
            return;
        }

        // For non-name updates with actual changes, proceed
        await updateOrganizer(field);
        
        // Reset the dirty state after successful update
        v$.value.organizerData[field].$reset();

    } catch (error) {
        console.error('Update error:', error);
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors;
        }
    }
};

// Update updateOrganizer to also reset dirty state
const updateOrganizer = async (field) => {
    try {
        const response = await axios.post(`/organizers/${organizerData.value.slug}`, organizerData.value);
        
        if (response.data.organizer) {
            if (field === 'name') {
                const newUrl = `/organizers/${response.data.organizer.slug}`;
                window.history.pushState({ path: newUrl }, '', newUrl);
            }
            
            Object.assign(organizerData.value, response.data.organizer);
            Object.assign(props.organizer, response.data.organizer);
            organizerBeforeEdit.value = { ...organizerData.value };
            
            // Reset dirty state after successful update
            v$.value.organizerData[field].$reset();
            
            showToast.value = true;
            setTimeout(() => showToast.value = false, 1500);
        }
    } catch (error) {
        throw error;
    }
};

// Add confirmation handlers
const confirmNameChange = async () => {
    showModal.value = false;
    await updateOrganizer('name');
};

const cancelNameChange = () => {
    showModal.value = false;
    organizerData.value.name = organizerBeforeEdit.value.name;
    v$.value.$reset();
};

const resetOrganizer = () => {
    organizerData.value = { ...organizerBeforeEdit.value };
    v$.value.$reset();
};

const handleDescriptionInput = () => {
    v$.value.organizerData.description.$touch();
    
    if (organizerData.value.description?.length > 2000) {
        organizerData.value.description = organizerData.value.description.slice(0, 2000);
    }
};

// Add computed properties for social media validation
const showSocialError = computed(() => ({
    twitter: v$.value.organizerData.twitterHandle.$dirty && v$.value.organizerData.twitterHandle.$error,
    instagram: v$.value.organizerData.instagramHandle.$dirty && v$.value.organizerData.instagramHandle.$error,
    facebook: v$.value.organizerData.facebookHandle.$dirty && v$.value.organizerData.facebookHandle.$error,
    patreon: v$.value.organizerData.patreon.$dirty && v$.value.organizerData.patreon.$error
}));

// Add character count display helpers
const socialCharCount = computed(() => ({
    twitter: organizerData.value.twitterHandle?.length || 0,
    instagram: organizerData.value.instagramHandle?.length || 0,
    facebook: organizerData.value.facebookHandle?.length || 0,
    patreon: organizerData.value.patreon?.length || 0
}));

// Add these methods to handle social media input
const handleSocialInput = (field, limit) => {
    v$.value.organizerData[field].$touch();
    if (organizerData.value[field]?.length > limit) {
        organizerData.value[field] = organizerData.value[field].slice(0, limit);
    }
};

// Add computed properties for social media limits
const isSocialNearLimit = computed(() => ({
    twitter: (organizerData.value.twitterHandle?.length || 0) >= 15,
    instagram: (organizerData.value.instagramHandle?.length || 0) >= 30,
    facebook: (organizerData.value.facebookHandle?.length || 0) >= 75,
    patreon: (organizerData.value.patreon?.length || 0) >= 30
}));

// Add computed property to check if any social links exist
const hasSocialLinks = computed(() => {
    return Boolean(
        organizerData.value.website ||
        organizerData.value.email ||
        organizerData.value.twitterHandle ||
        organizerData.value.instagramHandle ||
        organizerData.value.facebookHandle ||
        organizerData.value.patreon
    );
});

// Add this computed property
const hasPendingNameChange = computed(() => {
    return organizerData.value.name_change_requests?.length > 0;
});

// Expose methods for parent components
defineExpose({
    isValid: async () => {
        await v$.value.$validate();
        return !v$.value.$error;
    },
    submitData: () => {
        const { images, ...organizerData } = props.organizer;
        return organizerData;
    }
});

const handleImageUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Check file type
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        alert('Please upload a valid image file (JPEG, PNG, or WebP)');
        return;
    }

    // Check file size (e.g., 5MB limit)
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (file.size > maxSize) {
        alert('File size should be less than 5MB');
        return;
    }

    try {
        isUploading.value = true;
        
        const formData = new FormData();
        formData.append('image', file);
        formData.append('type', 'organizer-images'); // Add type for the image path
        
        const response = await axios.post(
            `/organizers/${organizerData.value.slug}/image`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        );
        
        if (response.data.message === 'Image updated successfully') {
            // Update both the reactive data and the props with the new image path
            organizerData.value.images = response.data.images;
            props.organizer.images = response.data.images;
            
            showToast.value = true;
            setTimeout(() => showToast.value = false, 1500);
        } else {
            throw new Error(response.data.message || 'Failed to upload image');
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert(error.response?.data?.message || 'Failed to upload image. Please try again.');
    } finally {
        isUploading.value = false;
        // Reset the file input
        event.target.value = '';
    }
};
</script>