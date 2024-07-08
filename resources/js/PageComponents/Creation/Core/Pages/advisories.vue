<template>
    <div>
        <div class="w-full">
            <h4>Guest contact level</h4>
            <div v-if="!selectedContact" class="flex flex-col w-full">
                <div class="grid grid-cols-3 gap-4">
                    <div 
                        v-for="contact in contactLevelList" 
                        :key="contact.id" 
                        @click="selectContactLevel(contact)"
                        class="relative cursor-pointer items-end flex justify-between p-8 h-48 border rounded-2xl hover:shadow-[0_0_0_1.5px_black]">
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
        </div>
        <div>
            <div class="pt-24">
                <h4>Is there sexual content?</h4>
                <div class="pt-8 flex flex-row gap-8 relative">
                    <div class="relative">
                        <button 
                            @click="onSelectSexualContent(false)"
                            :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.advisories.sexual === false }"
                            class="border-gray-300 border rounded-2xl flex justify-between items-center hover:border-2 hover hover:border-black px-12 py-8">
                            <div class="text-left">
                                <h4 class="font-bold text-3xl">
                                    No
                                </h4>
                            </div>
                        </button>
                        <div 
                            @mouseenter="hoveredLocation = 'closeSexual'"
                            @mouseleave="hoveredLocation = null"
                            @click="event.advisories.sexual = null" 
                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                        >
                            <component :is="hoveredLocation === 'closeSexual' ? RiCloseCircleFill : RiCloseCircleLine" />
                        </div>
                    </div>
                    <div v-if="event.advisories.sexual === true" class="w-full relative">
                        <textarea 
                            name="sexualContentDescription" 
                            class="text-2xl font-normal border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full h-full" 
                            v-model="event.advisories.sexualDescription" 
                            @input="showContentAdvisories"
                            placeholder="Explain the sexual content to our readers"
                            rows="2">
                        </textarea>
                        <div 
                            @mouseenter="hoveredLocation = 'closeSexual'"
                            @mouseleave="hoveredLocation = null"
                            @click="event.advisories.sexual = null" 
                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                        >
                            <component :is="hoveredLocation === 'closeSexual' ? RiCloseCircleFill : RiCloseCircleLine" />
                        </div>
                    </div>
                    <button 
                        v-else
                        @click="onSelectSexualContent(true)"
                        :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.advisories.sexual === true }"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center hover:border-2 hover hover:border-black px-12 py-8">
                        <div class="text-left">
                            <h4 class="font-bold text-3xl">
                                Yes
                            </h4>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, inject, onMounted, nextTick } from 'vue';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

const components = { Dropdown, List };

const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

const userAccepts = ref(false);

const contactLevelList = ref([]);
const contentAdvisoryList = ref([]);
const contentAdvisories= ref([]);
const selectedContact = ref(null);
const hoveredLocation = ref(null);
const contactDiv = ref(null);
const contactDivHeight = ref(null);

const itemSelected = async (item) => {
    contentAdvisories.value.push(item);
}

const itemRemoved = async (item) => {
    contentAdvisories = [];
}

const fetchContactLevels = async () => {
    const response = await axios.get(`/api/contactlevels`);
    contactLevelList.value = response.data;
};
const fetchcontentAdvisories = async () => {
    const response = await axios.get(`/api/contentadvisories`);
    contentAdvisoryList.value = response.data;
};

const onSelectSexualContent = (hasSexualContent) => {
    event.advisories.sexual = hasSexualContent;
};

const showContentAdvisories = () => {
    // This will automatically trigger reactivity to show the advisories when the user types in the textarea
};

const handleSubmit = async () => {
    await onSubmit({ hasLocation: event.hasLocation });
};

const selectContactLevel = (contact) => {
    selectedContact.value = contact;
};

const deselectContactLevel = () => {
    selectedContact.value = null;
};

onMounted(() => {
    fetchContactLevels();
    fetchcontentAdvisories();
});

</script>

<style>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 1.25s ease-out;
    overflow: hidden;
}

.slide-up-leave-to {
    height: 0rem !important; /* Final height value */
}
</style>
