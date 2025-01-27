<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>Add your social media links</h2>
                <p class="text-gray-500 font-normal mt-4">Connect with your audience across platforms</p>
                
                <div class="mt-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div 
                            v-for="media in socialMediaList" 
                            :key="media.name" 
                            @click="handleDivClick(media.name)"
                            :class="{
                                'border-[#e5e7eb] text-gray-400': !organizer[media.model] && currentMedia !== media.name,
                                'border-black text-black border-2 bg-neutral-100': organizer[media.model] || currentMedia === media.name,
                            }"
                            class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-colors duration-200"
                        >
                            <div class="flex items-start justify-start w-full h-16 rounded-2xl">
                                <component
                                    :is="media.icon"
                                    class="w-12 h-12"
                                    :class="{
                                        'text-[#adadad]': !organizer[media.model] && currentMedia !== media.name,
                                        'text-black': organizer[media.model] || currentMedia === media.name,
                                    }"
                                />
                            </div>
                            <div v-if="currentMedia === media.name" class="w-full">
                                <textarea 
                                    :placeholder="media.placeholder" 
                                    v-model="organizer[media.model]" 
                                    @blur="handleInputBlur(media.name)"
                                    rows="2"
                                    class="p-2 mt-2 border-none focus:border-black focus:ring-black rounded-md focus:shadow-lg w-full text-lg resize-none"
                                    @click.stop
                                    :ref="el => inputRefs[media.name] = el"
                                ></textarea>
                            </div>
                            <div v-else class="overflow-hidden w-full">
                                <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap"
                                    :class="{
                                        'text-[#adadad]': !organizer[media.model],
                                        'text-black': organizer[media.model],
                                    }">
                                    {{ organizer[media.model] || media.placeholder }}
                                </h4>
                            </div>
                            <!-- Validation messages -->
                            <p v-if="media.name === 'email' && v$.organizer.email.$dirty && v$.organizer.email.email.$invalid" 
                               class="text-red-500 text-sm mt-1">
                                Please enter a valid email address
                            </p>
                            <p v-if="media.name === 'website' && v$.organizer.website.$dirty && v$.organizer.website.url.$invalid" 
                               class="text-red-500 text-sm mt-1">
                                Please enter a valid URL (include https:// and domain)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, nextTick } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { email, url } from '@vuelidate/validators';
import { 
    RiMailLine,
    RiInstagramLine,
    RiTwitterLine,
    RiFacebookLine,
    RiPatreonLine,
    RiGlobalLine
} from "@remixicon/vue";

const organizer = inject('organizer');
const errors = inject('errors');

const currentMedia = ref(null);
const inputRefs = ref({});

const socialMediaList = [
    {
        name: 'website',
        model: 'website',
        icon: RiGlobalLine,
        placeholder: 'Add your website URL'
    },
    {
        name: 'email',
        model: 'email',
        icon: RiMailLine,
        placeholder: 'Add your email address'
    },
    {
        name: 'instagram',
        model: 'instagramHandle',
        icon: RiInstagramLine,
        placeholder: 'Add your Instagram handle'
    },
    {
        name: 'twitter',
        model: 'twitterHandle',
        icon: RiTwitterLine,
        placeholder: 'Add your Twitter handle'
    },
    {
        name: 'facebook',
        model: 'facebookHandle',
        icon: RiFacebookLine,
        placeholder: 'Add your Facebook URL'
    },
    {
        name: 'patreon',
        model: 'patreon',
        icon: RiPatreonLine,
        placeholder: 'Add your Patreon URL'
    }
];

// Validation rules
const rules = {
    organizer: {
        email: { email },
        website: { url }
    }
};

const v$ = useVuelidate(rules, { organizer });

const handleDivClick = async (mediaName) => {
    currentMedia.value = mediaName;
    await nextTick();
    if (inputRefs.value[mediaName]) {
        inputRefs.value[mediaName].focus();
    }
};

const handleInputBlur = (mediaName) => {
    currentMedia.value = null;
    if (mediaName === 'email') {
        v$.value.organizer.email.$touch();
    }
    if (mediaName === 'website') {
        v$.value.organizer.website.$touch();
    }
};

// Component API
defineExpose({
    isValid: async () => {
        const result = await v$.value.$validate();
        if (!result) {
            errors.value = {
                social: ['Please correct the validation errors in your social media links']
            };
        }
        return result;
    },
    submitData: () => {
        return {
            website: organizer.website,
            email: organizer.email,
            instagramHandle: organizer.instagramHandle,
            twitterHandle: organizer.twitterHandle,
            facebookHandle: organizer.facebookHandle,
            patreon: organizer.patreon
        };
    }
});
</script>