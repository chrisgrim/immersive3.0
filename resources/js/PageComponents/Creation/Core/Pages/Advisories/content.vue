<template>
    <main class="w-full py-40 flex items-center min-h-[max(40rem,calc(100vh-6rem))]">
        <div class="flex flex-col w-full">
            <h2>Content Advisories</h2>
            <!-- Initial Sexual Content Selection -->
            <div v-if="!hasSelectedSexual">
                <p class="font-strong mt-6">Is there sexual content in your event?</p>
                <div class="flex flex-row gap-8 relative mt-6">
                    <button 
                        v-for="option in sexualOptions" 
                        :key="option.value"
                        @click="onSelectSexual(option.value)"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center hover:shadow-[0_0_0_1.5px_black] hover:border-black px-12 py-8"
                    >
                        <div class="text-left">
                            <h4 class="font-bold text-3xl">{{ option.label }}</h4>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Additional Advisories Selection -->
            <div v-else class="mt-6">
                <p class="font-strong">Select additional content advisories</p>
                <Dropdown 
                    class="mt-4"
                    :list="contentAdvisoryList"
                    :creatable="true"
                    placeholder="Additional advisories"
                    @onSelect="itemSelected" 
                />
                <List 
                    class="mt-6"
                    :selections="contentAdvisories" 
                    @onSelect="itemRemoved"
                />

                <!-- Sexual Content Description -->
                <div v-if="Boolean(event.advisories.sexual)" class="mt-12">
                    <p class="text-gray-500 font-normal mb-4">Explain more about the sexual content</p>
                    <textarea 
                        v-model="event.advisories.sexualDescription"
                        @input="$v?.event?.advisories?.sexualDescription?.$touch()"
                        class="w-full p-4 text-1xl border rounded-2xl focus:border-black focus:shadow-[0_0_0_1.5px_black] outline-none"
                        :class="{ 'border-red-500': $v?.event?.advisories?.sexualDescription?.$error }"
                        rows="4"
                    ></textarea>
                    <div class="flex justify-end mt-1 text-gray-500">
                        {{ event.advisories.sexualDescription?.length || 0 }}/1000
                    </div>
                    <p v-if="$v?.event?.advisories?.sexualDescription?.$error" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        {{ $v?.event?.advisories?.sexualDescription?.required?.$invalid 
                            ? 'Please explain the sexual content' 
                            : 'Description is too long' }}
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, onMounted, computed } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

// Injected dependencies
const event = inject('event');
const errors = inject('errors');

// Constants
const sexualOptions = [
    { value: true, label: 'Yes' },
    { value: false, label: 'No' }
];

// State
const contentAdvisoryList = ref([]);
const hasSelectedSexual = ref(false);
const sexualAdvisory = ref(null);
const otherAdvisories = ref([]);

// Computed
const contentAdvisories = computed(() => 
    sexualAdvisory.value ? [sexualAdvisory.value, ...otherAdvisories.value] : otherAdvisories.value
);

// Helpers
const createSexualAdvisory = (hasSexualContent) => ({
    id: hasSexualContent ? 'sexual-content' : 'no-sexual-content',
    name: hasSexualContent ? 'Sexual Content' : 'No Sexual Content',
    slug: hasSexualContent ? 'sexual-content' : 'no-sexual-content',
    permanent: true
});

// Event handlers
const onSelectSexual = (hasSexualContent) => {
    sexualAdvisory.value = createSexualAdvisory(hasSexualContent);
    hasSelectedSexual.value = true;
    
    if (!event.advisories) {
        event.advisories = {};
    }
    
    event.advisories.sexual = hasSexualContent;
    event.advisories.sexualDescription = hasSexualContent ? '' : null;
};

const itemSelected = (item) => {
    otherAdvisories.value.push(item);
    contentAdvisoryList.value = contentAdvisoryList.value.filter(advisory => 
        !otherAdvisories.value.find(selected => selected.id === advisory.id)
    );
};

const itemRemoved = (item) => {
    if (['sexual-content', 'no-sexual-content'].includes(item.slug)) {
        hasSelectedSexual.value = false;
        sexualAdvisory.value = null;
        if (event.advisories) {
            event.advisories.sexual = null;
            event.advisories.sexualDescription = null;
        }
        return;
    }
    
    otherAdvisories.value = otherAdvisories.value.filter(advisory => advisory.id !== item.id);
    contentAdvisoryList.value.push(item);
};

// Validation
const rules = {
    event: {
        advisories: {
            sexual: { required },
            sexualDescription: {
                required: (value) => !event.advisories?.sexual || (event.advisories?.sexual && value?.length > 0),
                maxLength: maxLength(1000)
            }
        }
    }
};

const $v = useVuelidate(rules, {
    event: {
        advisories: event.advisories || { sexual: null, sexualDescription: null }
    }
});

// Replace handleSubmit with defineExpose
defineExpose({
    isValid: async () => {
        const isValid = await $v.value.$validate();
        console.log('Content Advisories validation:', {
            hasSelectedSexual: hasSelectedSexual.value,
            sexualContent: event.advisories?.sexual,
            contentAdvisoriesCount: contentAdvisories.value.length,
            validationError: $v.value.$error,
            isValid
        });
        return isValid;
    },
    submitData: () => {
        const data = {
            contentAdvisories: contentAdvisories.value,
            wheelchairReady: event.advisories.wheelchairReady,
            advisories: {
                sexual: event.advisories.sexual,
                sexualDescription: event.advisories.sexualDescription
            }
        };
        console.log('Submitting content advisories data:', data);
        return data;
    }
});

// API
const fetchContentAdvisories = async () => {
    const response = await axios.get(`/api/contentadvisories`);
    contentAdvisoryList.value = response.data.filter(advisory => 
        !['sexual-content', 'no-sexual-content'].includes(advisory.slug)
    );
};

// Lifecycle
onMounted(async () => {
    await fetchContentAdvisories();
    
    if (event.advisories?.sexual !== undefined) {
        hasSelectedSexual.value = true;
        sexualAdvisory.value = createSexualAdvisory(event.advisories.sexual);
        
        if (event.content_advisories?.length) {
            event.content_advisories.forEach(advisory => {
                if (!['sexual-content', 'no-sexual-content'].includes(advisory.slug)) {
                    itemSelected(advisory);
                }
            });
        }
    }
});
</script>
