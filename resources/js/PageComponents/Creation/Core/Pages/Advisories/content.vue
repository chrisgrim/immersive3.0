<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <h2>Content Advisories</h2>
            <!-- Initial Sexual Content Selection -->
            <div v-if="!hasSelectedSexual">
                <p class="font-strong mt-6">Is there sexual content in your event?</p>
                <div class="flex flex-row gap-8 relative mt-6">
                    <button 
                        v-for="option in SEXUAL_OPTIONS" 
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

                <!-- Move the error message here -->
                <div v-if="hasSelectedSexual && !hasRequiredAdvisories && $v.hasAdditionalAdvisories.$error" class="mt-4">
                    <p class="text-red-500 text-1xl">
                        Please select at least one additional content advisory
                    </p>
                </div>

                <!-- Sexual Content Description -->
                <div v-if="Boolean(event.advisories.sexual)" class="mt-12">
                    <p class="text-gray-500 font-normal mb-4">Explain more about the sexual content</p>
                    <textarea 
                        v-model="event.advisories.sexualDescription"
                        @input="handleDescriptionInput"
                        class="w-full p-4 text-1xl border rounded-2xl relative focus:border-black focus:shadow-[0_0_0_1.5px_black] outline-none"
                        :class="{ 
                            'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showDescriptionError
                        }"
                        rows="4"
                    ></textarea>
                    <div class="flex justify-end mt-1 relative text-gray-500">
                        {{ event.advisories.sexualDescription?.length || 0 }}/1000
                        <p v-if="showDescriptionError" 
                           class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                            {{ !event.advisories.sexualDescription?.trim() 
                                ? 'Please explain the sexual content' 
                                : 'Description is too long' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Add this after the buttons -->
            <div v-if="!hasSelectedSexual && $v.hasSelectedSexual.$error" class="mt-4">
                <p class="text-red-500 text-1xl px-4">
                    Please select whether there is sexual content
                </p>
            </div>
        </div>
    </main>
</template>
<script setup>
// 1. Imports
import { ref, inject, onMounted, computed } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

// 2. Constants
const SEXUAL_OPTIONS = [
    { value: true, label: 'Yes' },
    { value: false, label: 'No' }
];

const SEXUAL_SLUGS = ['sexual-content', 'no-sexual-content'];

// 3. Injections & State
const event = inject('event');
const errors = inject('errors');

const contentAdvisoryList = ref([]);
const hasSelectedSexual = ref(false);
const sexualAdvisory = ref(null);
const otherAdvisories = ref([]);

// 4. Computed Properties
const contentAdvisories = computed(() => 
    sexualAdvisory.value ? [sexualAdvisory.value, ...otherAdvisories.value] : otherAdvisories.value
);

const hasRequiredAdvisories = computed(() => otherAdvisories.value.length > 0);

const isDescriptionNearLimit = computed(() => {
    const count = event.advisories.sexualDescription?.length || 0;
    return count > 900;
});

const showDescriptionError = computed(() => {
    if (!event.advisories?.sexual) return false;
    
    return $v.value.event.advisories.sexualDescription.$dirty && 
           ($v.value.event.advisories.sexualDescription.$error || 
            !event.advisories.sexualDescription?.trim());
});

// 5. Validation Rules
const rules = {
    hasSelectedSexual: { required },
    hasAdditionalAdvisories: { 
        required: () => hasRequiredAdvisories.value 
    },
    event: {
        advisories: {
            sexualDescription: {
                required: (value) => {
                    if (event.advisories?.sexual !== true) return true;
                    return !!value?.trim();
                },
                maxLength: maxLength(1000)
            }
        }
    }
};

const $v = useVuelidate(rules, { 
    hasSelectedSexual,
    hasAdditionalAdvisories: computed(() => hasRequiredAdvisories.value),
    event 
});

// 6. Helper Functions
const createSexualAdvisory = (hasSexualContent) => ({
    id: hasSexualContent ? 'sexual-content' : 'no-sexual-content',
    name: hasSexualContent ? 'Sexual Content' : 'No Sexual Content',
    slug: hasSexualContent ? 'sexual-content' : 'no-sexual-content',
    permanent: true
});

// 7. Event Handlers
const onSelectSexual = (hasSexualContent) => {
    sexualAdvisory.value = createSexualAdvisory(hasSexualContent);
    hasSelectedSexual.value = true;
    
    event.advisories = {
        ...event.advisories || {},
        sexual: Boolean(hasSexualContent),
        sexualDescription: hasSexualContent ? '' : null
    };

    // Touch validation immediately when Yes is selected
    if (hasSexualContent) {
        $v.value.event.advisories.sexualDescription.$touch();
    }
};

const itemSelected = (item) => {
    otherAdvisories.value.push(item);
    contentAdvisoryList.value = contentAdvisoryList.value.filter(advisory => 
        !otherAdvisories.value.find(selected => selected.id === advisory.id)
    );
};

const itemRemoved = (item) => {
    if (SEXUAL_SLUGS.includes(item.slug)) {
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

const handleDescriptionInput = () => {
    if (event.advisories?.sexual) {
        $v.value.event.advisories.sexualDescription.$touch();
    }
    if (event.advisories.sexualDescription?.length > 1000) {
        event.advisories.sexualDescription = event.advisories.sexualDescription.slice(0, 1000);
    }
};

// 8. API Methods
const fetchContentAdvisories = async () => {
    const response = await axios.get('/api/contentadvisories');
    contentAdvisoryList.value = response.data.filter(advisory => 
        !SEXUAL_SLUGS.includes(advisory.slug)
    );
};

// 9. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$touch();
        await $v.value.$validate();

        if (!hasSelectedSexual.value) {
            errors.value = { advisories: ['Please select whether there is sexual content'] };
            return false;
        }

        if (!hasRequiredAdvisories.value) {
            errors.value = { advisories: ['Please select at least one additional content advisory'] };
            return false;
        }

        if (event.advisories?.sexual === true) {
            const description = event.advisories.sexualDescription?.trim() || '';
            if (!description) {
                $v.value.event.advisories.sexualDescription.$touch();
                errors.value = { advisories: ['Please explain the sexual content'] };
                return false;
            }
            if (description.length > 1000) {
                errors.value = { advisories: ['Sexual content description is too long'] };
                return false;
            }
        }

        return true;
    },
    submitData: () => ({
        contentAdvisories: [...contentAdvisories.value].map(advisory => ({
            id: advisory.id,
            name: advisory.name,
            slug: advisory.slug
        })),
        advisories: {
            sexual: Boolean(event.advisories.sexual),
            sexualDescription: event.advisories.sexualDescription
        }
    })
});

// 10. Lifecycle Hooks
onMounted(async () => {
    await fetchContentAdvisories();
    
    if (event.advisories?.sexual !== undefined && event.advisories?.sexual !== null) {
        hasSelectedSexual.value = true;
        sexualAdvisory.value = createSexualAdvisory(event.advisories.sexual);
        
        if (event.content_advisories?.length) {
            event.content_advisories.forEach(advisory => {
                if (!SEXUAL_SLUGS.includes(advisory.slug)) {
                    otherAdvisories.value.push(advisory);
                    contentAdvisoryList.value = contentAdvisoryList.value.filter(
                        listItem => listItem.id !== advisory.id
                    );
                }
            });
        }
    }
});
</script>

