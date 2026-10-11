<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div class="flex flex-col gap-24">
                <h2 class="text-black">Contact Advisories</h2>
                
                <!-- Contact Level Section -->
                <div class="w-full">
                    <h4 class="mb-8">Audience Contact Level</h4>
                    <div v-if="!selectedContact" class="flex flex-col w-full">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div 
                                v-for="contact in contactLevelList" 
                                :key="contact.id" 
                                @click="selectContactLevel(contact)"
                                class="relative cursor-pointer items-end flex justify-between p-8 min-h-48 border border-neutral-300 rounded-2xl hover:border-[#222222] hover:shadow-focus-black transition-all duration-200"
                            >
                                <div class="w-full">
                                    <p class="text-2xl leading-tight break-words hyphens-auto">{{ contact.name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-neutral-50 transition-all duration-200">
                        <div>
                            <p class="text-1xl leading-tight break-words hyphens-auto"
                                :class="{
                                    'text-neutral-400': selectedContact.model,
                                    'text-black': selectedContact.model 
                                }"
                            >
                                {{ selectedContact.name }}
                            </p>
                        </div>
                        <div 
                            @mouseenter="hoveredLocation = 'close'"
                            @mouseleave="hoveredLocation = null"
                            @click="deselectContactLevel" 
                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                        >
                            <component :is="hoveredLocation === 'close' ? RiCloseCircleFill : RiCloseCircleLine" />
                        </div>
                    </div>
                    <p v-if="$v.selectedContact.$error" 
                       class="text-red-500 text-1xl mt-2 py-2 leading-tight">
                        Please select a contact level
                    </p>
                </div>

                <!-- Age Limit Section -->
                <div class="w-full">
                    <h4 class="mb-8">Age Requirement</h4>
                    <!-- Organizers pick from the usual ages. -->
                    <div v-if="!selectedAge && !isStaff" class="flex flex-col w-full">
                        <div class="grid grid-cols-4 md:grid-cols-3 gap-4">
                            <div 
                                v-for="age in standardAgeList" 
                                :key="age.id" 
                                @click="selectAgeLimit(age)"
                                class="relative cursor-pointer items-end flex justify-between p-8 min-h-32 md:min-h-48 border border-neutral-300 rounded-2xl hover:border-[#222222] hover:shadow-focus-black transition-all duration-200"
                            >
                                <div class="w-full">
                                    <p class="text-2xl leading-tight break-words hyphens-auto">{{ age.name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Staff can type any youngest age from 1 to 21: the usual list
                         misses shows like 9 + VR or 4 + light walks. -->
                    <div v-else-if="!selectedAge" class="flex flex-col md:flex-row md:items-center gap-6 w-full">
                        <button
                            v-if="allAges"
                            type="button"
                            @click="selectAgeLimit(allAges)"
                            class="px-8 py-6 text-2xl text-left border border-neutral-300 rounded-2xl hover:border-[#222222] hover:shadow-focus-black transition-all duration-200"
                        >
                            {{ allAges.name }}
                        </button>
                        <p class="text-2xl text-neutral-500">or</p>
                        <form class="flex items-center gap-4" novalidate @submit.prevent="selectTypedAge">
                            <label for="age-minimum" class="text-2xl">Ages</label>
                            <input
                                id="age-minimum"
                                v-model="typedAge"
                                type="number"
                                inputmode="numeric"
                                :min="MIN_AGE"
                                :max="MAX_AGE"
                                placeholder="9"
                                class="w-28 px-6 py-5 text-[1.6rem] md:text-2xl border border-neutral-300 rounded-2xl focus:border-black focus:shadow-[0_0_0_1px_black] focus:outline-none"
                                :class="{ 'border-red-500': typedAgeError }"
                            >
                            <span class="text-2xl">and up</span>
                            <button type="submit" class="px-8 py-5 bg-black text-white rounded-2xl hover:bg-neutral-800 text-xl font-semibold">
                                Set
                            </button>
                        </form>
                    </div>
                    <div v-else class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-neutral-50 transition-all duration-200">
                        <div>
                            <p class="text-1xl leading-tight break-words hyphens-auto"
                                :class="{
                                    'text-neutral-400': selectedAge.model,
                                    'text-black': selectedAge.model 
                                }"
                            >
                                {{ selectedAge.name }}
                            </p>
                        </div>
                        <div 
                            @mouseenter="hoveredLocation = 'closeAge'"
                            @mouseleave="hoveredLocation = null"
                            @click="deselectAgeLimit" 
                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                        >
                            <component :is="hoveredLocation === 'closeAge' ? RiCloseCircleFill : RiCloseCircleLine" />
                        </div>
                    </div>
                    <p v-if="!selectedAge && typedAgeError" class="text-red-500 text-1xl mt-2 py-2 leading-tight">
                        {{ typedAgeError }}
                    </p>
                    <p v-else-if="$v.selectedAge.$error" 
                       class="text-red-500 text-1xl mt-2 py-2 leading-tight">
                        Please select an age requirement
                    </p>
                </div>

                <!-- Interactive Level Section -->
                <div class="w-full">
                    <h4 class="mb-8">Audience Interaction Level</h4>
                    <div v-if="!selectedInteractive" class="flex flex-col w-full gap-4">
                        <div 
                            v-for="interactive in contentInteractiveList" 
                            :key="interactive.id" 
                            @click="selectInteractiveLevel(interactive)"
                            class="relative cursor-pointer flex flex-col p-8 border border-neutral-300 rounded-2xl hover:border-[#222222] hover:shadow-focus-black transition-all duration-200"
                        >
                            <div class="w-full">
                                <p class="text-2.5xl leading-tight mb-2 break-words hyphens-auto">{{ interactive.name }}</p>
                                <p class="text-lg leading-snug text-neutral-600 break-words hyphens-auto">{{ interactive.description }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else>
                        <div class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-neutral-50 w-full transition-all duration-200">
                            <div class="max-w-2xl">
                                <p class="text-2.5xl leading-tight mb-2"
                                    :class="{
                                        'text-neutral-400': selectedInteractive.model,
                                        'text-black': selectedInteractive.model 
                                    }"
                                >
                                    {{ selectedInteractive.name }}
                                </p>
                                <p class="text-lg leading-snug text-neutral-600">{{ selectedInteractive.description }}</p>
                            </div>
                            <div 
                                @mouseenter="hoveredLocation = 'closeInteractive'"
                                @mouseleave="hoveredLocation = null"
                                @click="deselectInteractiveLevel" 
                                class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                            >
                                <component :is="hoveredLocation === 'closeInteractive' ? RiCloseCircleFill : RiCloseCircleLine" />
                            </div>
                        </div>
                        
                        <!-- Audience Role Textarea -->
                        <div class="mt-8">
                            <h4 class="mb-4 text-1xl">Audience Role</h4>
                            <textarea 
                                v-model="event.advisories.audience"
                                @input="handleAudienceInput"
                                class="w-full p-4 text-2.5xl md:text-1xl border border-neutral-300 rounded-2xl relative outline-none transition-all duration-200"
                                :class="{ 
                                    'border-red-500': 
                                        $v.event.advisories.audience.$error || 
                                        event.advisories.audience?.length === 1000,
                                    'focus:border-red-500 focus:shadow-focus-error':
                                        $v.event.advisories.audience.$error || 
                                        event.advisories.audience?.length === 1000,
                                    'hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': 
                                        !$v.event.advisories.audience.$error && 
                                        event.advisories.audience?.length < 1000
                                }"
                                placeholder="Describe the role your audience will play..."
                                rows="4"
                                maxlength="1000"
                            ></textarea>
                            <div class="flex justify-end mt-1 relative text-neutral-500"
                                 :class="{ 'text-red-500': isAudienceNearLimit }">
                                {{ event.advisories.audience?.length || 0 }}/1000
                                <p v-if="$v.event.advisories.audience.$error" 
                                   class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                    {{ $v.event.advisories.audience.required.$invalid ? 'Audience role is required' : 'Audience role is too long' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-if="$v.selectedInteractive.$error" 
                        class="text-red-500 text-1xl mt-2 py-2 leading-tight">
                        Please select an interaction level
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
// 1. Imports
import { ref, inject, onMounted, computed } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";

// 2. Injections & State
const event = inject('event');
const hoveredLocation = ref(null);
const selectedContact = ref(null);
const selectedAge = ref(null);
const selectedInteractive = ref(null);
const contactLevelList = ref([]);
const ageLimitList = ref([]);
const typedAge = ref('');
const typedAgeError = ref('');
const contentInteractiveList = ref([]);

// 3. Computed
const currentContactLevel = computed(() => 
    event.contact_levels?.length > 0 ? event.contact_levels[0] : null
);

// Mirrors the "1 +" to "21 +" rows in age_limits; "All ages" is its own row.
const MIN_AGE = 1;
const MAX_AGE = 21;
const allAges = computed(() => ageLimitList.value.find((age) => age.name === 'All ages') || null);

// Organizers see the usual ages only; moderators and admins type any age.
const user = inject('user');
const isStaff = computed(() => !!user?.isModerator);
const STANDARD_AGES = ['6 +', '8 +', '10 +', '12 +', '13 +', '16 +', '18 +', '21 +', 'All ages'];
const standardAgeList = computed(() => ageLimitList.value.filter((age) => STANDARD_AGES.includes(age.name)));

const currentAgeLimit = computed(() => 
    event.age_limits || null
);

const isAudienceNearLimit = computed(() => {
    const count = event.advisories.audience?.length || 0;
    return count > 900;
});

// 4. Validation Rules
const rules = {
    selectedContact: { required },
    selectedAge: { required },
    selectedInteractive: { required },
    event: {
        advisories: {
            audience: { 
                required,
                maxLength: maxLength(1000)
            }
        }
    }
};

const $v = useVuelidate(rules, { 
    selectedContact,
    selectedAge,
    selectedInteractive,
    event
});

// 5. API Methods
const fetchContactLevels = async () => {
    const response = await axios.get('/api/contactlevels');
    contactLevelList.value = response.data;
};

const fetchAgeLimits = async () => {
    const response = await axios.get('/api/agelimits');
    ageLimitList.value = response.data;
};

const fetchInteractiveLevel = async () => {
    const response = await axios.get('/api/interactivelevels');
    contentInteractiveList.value = response.data;
};

// 6. Event Handlers
const selectContactLevel = (contact) => {
    selectedContact.value = contact;
};

const deselectContactLevel = () => {
    selectedContact.value = null;
};

const selectAgeLimit = (age) => {
    selectedAge.value = age;
};

const deselectAgeLimit = () => {
    selectedAge.value = null;
    typedAge.value = '';
};

const selectTypedAge = () => {
    const age = Number(typedAge.value);
    const match = Number.isInteger(age) && ageLimitList.value.find((row) => row.name === `${age} +`);
    if (!match) {
        typedAgeError.value = `Please type a whole age from ${MIN_AGE} to ${MAX_AGE}`;
        return;
    }
    typedAgeError.value = '';
    selectAgeLimit(match);
};

const selectInteractiveLevel = (interactive) => {
    selectedInteractive.value = interactive;
};

const deselectInteractiveLevel = () => {
    selectedInteractive.value = null;
};

const handleAudienceInput = () => {
    $v.value.event.advisories.audience.$touch();
    if (event.advisories.audience?.length > 1000) {
        event.advisories.audience = event.advisories.audience.slice(0, 1000);
    }
};

// 7. Component API
defineExpose({
    isValid: async () => {
        // A typed age counts even if they went straight to Next without Set.
        if (!selectedAge.value && typedAge.value !== '') selectTypedAge();
        const isValid = await $v.value.$validate();
        return isValid;
    },
    submitData: () => ({
        contactLevel: selectedContact.value,
        ageLimit: selectedAge.value,
        interactiveLevel: selectedInteractive.value,
        advisories: {
            audience: event.advisories.audience
        }
    })
});

// 8. Lifecycle Hooks
onMounted(async () => {
    await Promise.all([
        fetchContactLevels(),
        fetchAgeLimits(),
        fetchInteractiveLevel()
    ]);
    
    if (currentContactLevel.value) {
        selectContactLevel(currentContactLevel.value);
    }
    if (currentAgeLimit.value) {
        selectAgeLimit(currentAgeLimit.value);
    }
    if (event.interactive_level) {
        selectInteractiveLevel(event.interactive_level);
    }
});
</script>

<style>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 1.25s ease-out;
    overflow: hidden;
}

.slide-up-leave-to {
    height: 0rem !important;
}
</style>
