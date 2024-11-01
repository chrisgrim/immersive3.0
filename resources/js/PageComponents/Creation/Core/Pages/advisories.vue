<template>
    <div class="flex flex-col gap-24">
        <!-- Contact Level Section -->
        <div class="w-full">
            <h4 class="mb-8">Audience Contact Level</h4>
            <div v-if="!selectedContact" class="flex flex-col w-full">
                <div class="grid grid-cols-3 gap-4">
                    <div 
                        v-for="contact in contactLevelList" 
                        :key="contact.id" 
                        @click="selectContactLevel(contact)"
                        class="relative cursor-pointer items-end flex justify-between p-8 h-48 border rounded-2xl hover:shadow-[0_0_0_1.5px_black]"
                    >
                        <div class="w-full">
                            <h4 class="text-2xl leading-tight">
                                {{ contact.name }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-gray-100">
                <div>
                    <h4 class="text-1xl leading-tight"
                        :class="{
                            'text-[#adadad]': selectedContact.model,
                            'text-black': selectedContact.model }">
                        {{ selectedContact.name }}
                    </h4>
                </div>
                <div 
                    @mouseenter="hoveredLocation = 'close'"
                    @mouseleave="hoveredLocation = null"
                    @click="deselectContactLevel" 
                    class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white">
                    <component :is="hoveredLocation === 'close' ? RiCloseCircleFill : RiCloseCircleLine" />
                </div>
            </div>
            <p v-if="$v.selectedContact.$error" 
               class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                Please select a contact level
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
                    class="relative cursor-pointer flex flex-col p-8 border rounded-2xl hover:shadow-[0_0_0_1.5px_black]"
                >
                    <div class="w-full">
                        <h4 class="text-2xl leading-tight mb-2">
                            {{ interactive.name }}
                        </h4>
                        <p class="text-lg leading-snug text-gray-600">
                            {{ interactive.description }}
                        </p>
                    </div>
                </div>
            </div>
            <div v-else>
                <div class="relative inline-block p-8 border-2 rounded-2xl border-[#222222] hover:bg-gray-100 w-full">
                    <div class="max-w-2xl">
                        <h4 class="text-1xl leading-tight mb-2"
                            :class="{
                                'text-[#adadad]': selectedInteractive.model,
                                'text-black': selectedInteractive.model }">
                            {{ selectedInteractive.name }}
                        </h4>
                        <p class="text-lg leading-snug text-gray-600">
                            {{ selectedInteractive.description }}
                        </p>
                    </div>
                    <div 
                        @mouseenter="hoveredLocation = 'closeInteractive'"
                        @mouseleave="hoveredLocation = null"
                        @click="deselectInteractiveLevel" 
                        class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white">
                        <component :is="hoveredLocation === 'closeInteractive' ? RiCloseCircleFill : RiCloseCircleLine" />
                    </div>
                </div>
                
                <!-- Audience Role Textarea -->
                <div class="mt-8">
                    <h4 class="mb-4 text-1xl">Audience Role</h4>
                    <textarea 
                        v-model="event.advisories.audience"
                        @input="$v.event.advisories.audience.$touch"
                        class="w-full p-4 text-1xl border rounded-2xl focus:border-black focus:shadow-[0_0_0_1.5px_black] outline-none"
                        :class="{ 'border-red-500': $v.event.advisories.audience.$error }"
                        placeholder="Describe the role your audience will play..."
                        rows="4"
                    ></textarea>
                    <div class="flex justify-end mt-1 text-gray-500">
                        {{ event.advisories.audience?.length || 0 }}/1000
                    </div>
                    <p v-if="$v.event.advisories.audience.$error" 
                       class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                        {{ $v.event.advisories.audience.required.$invalid ? 'Audience role is required' : 'Audience role is too long' }}
                    </p>
                </div>
            </div>
            <p v-if="$v.selectedInteractive.$error" 
               class="text-white bg-red-500 text-lg mt-1 px-4 py-2 leading-tight">
                Please select an interaction level
            </p>
        </div>

        <div class="w-full flex justify-end">
            <button 
                class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" 
                @click="handleSubmit"
            >
                Next
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, inject, onMounted, computed } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');
const errors = inject('errors');

// State
const contactLevelList = ref([]);
const contentInteractiveList = ref([]);
const selectedContact = ref(null);
const selectedInteractive = ref(null);
const hoveredLocation = ref(null);

const currentContactLevel = computed(() => {
    if (event.contact_levels && event.contact_levels.length > 0) {
        return event.contact_levels[0];
    }
    return null;
});

// API calls
const fetchContactLevels = async () => {
    const response = await axios.get(`/api/contactlevels`);
    contactLevelList.value = response.data;
};

const fetchInteractiveLevel = async () => {
    const response = await axios.get(`/api/interactivelevels`);
    contentInteractiveList.value = response.data;
};

// Event handlers
const selectContactLevel = (contact) => {
    selectedContact.value = contact;
};

const deselectContactLevel = () => {
    selectedContact.value = null;
};

const selectInteractiveLevel = (interactive) => {
    selectedInteractive.value = interactive;
};

const deselectInteractiveLevel = () => {
    selectedInteractive.value = null;
};

const rules = {
    selectedContact: { required },
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
    selectedInteractive,
    event
});

const handleSubmit = async () => {
    errors.value = {};
    const isFormValid = await $v.value.$validate();
    
    if (!isFormValid) {
        return;
    }

    await onSubmit({ 
        contactLevel: selectedContact.value,
        interactiveLevel: selectedInteractive.value,
        advisories: {
            audience: event.advisories.audience
        }
    });
    setStep('NextStep');
};

// Lifecycle
onMounted(() => {
    fetchContactLevels();
    fetchInteractiveLevel();
    
    // Initialize from existing event data if it exists
    if (currentContactLevel.value) {
        selectContactLevel(currentContactLevel.value);
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