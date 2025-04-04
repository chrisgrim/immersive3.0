<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div class="flex flex-col gap-24">
                <h2 class="text-black">Contact Advisories</h2>
                
                <!-- Contact Level Section -->
                <div class="w-full">
                    <h4 class="mb-8">Audience Contact Level</h4>
                    <div v-if="!selectedContact" class="flex flex-col w-full">
                        <div class="grid grid-cols-3 gap-4">
                            <div 
                                v-for="contact in contactLevelList" 
                                :key="contact.id" 
                                @click="selectContactLevel(contact)"
                                class="relative cursor-pointer items-end flex justify-between p-8 h-48 border border-neutral-300 rounded-2xl hover:border-[#222222] hover:shadow-focus-black transition-all duration-200"
                            >
                                <div class="w-full">
                                    <h4 class="text-2xl leading-tight">{{ contact.name }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-neutral-50 transition-all duration-200">
                        <div>
                            <h4 class="text-1xl leading-tight"
                                :class="{
                                    'text-neutral-400': selectedContact.model,
                                    'text-black': selectedContact.model 
                                }"
                            >
                                {{ selectedContact.name }}
                            </h4>
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
                    <div v-if="!selectedAge" class="flex flex-col w-full">
                        <div class="grid grid-cols-3 gap-4">
                            <div 
                                v-for="age in ageLimitList" 
                                :key="age.id" 
                                @click="selectAgeLimit(age)"
                                class="relative cursor-pointer items-end flex justify-between p-8 h-48 border border-neutral-300 rounded-2xl hover:border-[#222222] hover:shadow-focus-black transition-all duration-200"
                            >
                                <div class="w-full">
                                    <h4 class="text-2xl leading-tight">{{ age.name }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-neutral-50 transition-all duration-200">
                        <div>
                            <h4 class="text-1xl leading-tight"
                                :class="{
                                    'text-neutral-400': selectedAge.model,
                                    'text-black': selectedAge.model 
                                }"
                            >
                                {{ selectedAge.name }}
                            </h4>
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
                    <p v-if="$v.selectedAge.$error" 
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
                                <h4 class="text-2xl leading-tight mb-2">{{ interactive.name }}</h4>
                                <p class="text-lg leading-snug text-neutral-600">{{ interactive.description }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else>
                        <div class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-neutral-50 w-full transition-all duration-200">
                            <div class="max-w-2xl">
                                <h4 class="text-1xl leading-tight mb-2"
                                    :class="{
                                        'text-neutral-400': selectedInteractive.model,
                                        'text-black': selectedInteractive.model 
                                    }"
                                >
                                    {{ selectedInteractive.name }}
                                </h4>
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
                                class="w-full p-4 text-1xl border border-neutral-300 rounded-2xl relative outline-none transition-all duration-200"
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
const contentInteractiveList = ref([]);

// 3. Computed
const currentContactLevel = computed(() => 
    event.contact_levels?.length > 0 ? event.contact_levels[0] : null
);

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
