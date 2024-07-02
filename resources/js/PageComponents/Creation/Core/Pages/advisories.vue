<template>
    <div>
        <div class="flex flex-col w-full">
            <h4>Guest contact level</h4>
            <div class="pt-8 ap-8"></div>
            <div v-if="!selectedContact" class="grid grid-cols-4 gap-4">
                <div 
                    v-for="contact in contactLevelList" 
                    :key="contact.id" 
                    @click="selectContactLevel(contact)"
                    class="relative border-[#222222] cursor-pointer flex flex-col items-start justify-between p-8 border rounded-2xl hover:shadow-[0_0_0_1.5px_black]"
                >
                    <div class="w-full">
                        <h4 class="text-lg leading-tight">
                            {{ contact.level }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="selectedContact" class="relative inline-block  p-8 border rounded-2xl  bg-[#f7f7f7] shadow-[0_0_0_1.5px_black]">
                <div>
                    <h4 class="text-xl leading-tight"
                        :class="{
                            'text-[#adadad]': selectedContact.model,
                            'text-black': selectedContact.model,
                        }">
                        {{ selectedContact.level }}
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
        <div>
            <div v-if="hasContactSelected" class="pt-16">
                <h4>Is there sexual content?</h4>
                <div class="pt-8 flex flex-row gap-8 relative">
                    <div class="relative">
                        <button 
                            @click="onSelectSexualContent(false)"
                            :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.hasSexualContent === false }"
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
                            @click="event.hasSexualContent === null" 
                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                        >
                            <component :is="hoveredLocation === 'closeSexual' ? RiCloseCircleFill : RiCloseCircleLine" />
                        </div>
                    </div>
                    <div v-if="event.hasSexualContent === true" class="w-full relative">
                        <textarea 
                            name="sexualContentDescription" 
                            class="text-2xl font-normal border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full h-full" 
                            v-model="event.sexualContentDescription" 
                            @input="showContentAdvisories"
                            placeholder="Explain the sexual content to our readers"
                            rows="2">
                        </textarea>
                        <div 
                            @mouseenter="hoveredLocation = 'closeSexual'"
                            @mouseleave="hoveredLocation = null"
                            @click="event.hasSexualContent === null" 
                            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                        >
                            <component :is="hoveredLocation === 'closeSexual' ? RiCloseCircleFill : RiCloseCircleLine" />
                        </div>
                    </div>
                    <button 
                        v-else
                        @click="onSelectSexualContent(true)"
                        :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.hasSexualContent === true }"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center hover:border-2 hover hover:border-black px-12 py-8">
                        <div class="text-left">
                            <h4 class="font-bold text-3xl">
                                Yes
                            </h4>
                        </div>
                    </button>
                </div>
            </div>
            <div class="mt-16">
                <h4 class="mb-8">Content Advisories</h4>
                <Dropdown 
                    :list="contentAdvisoryList" 
                    @onSelect="itemSelected" />
                <List 
                    :selections="contentAdvisories" 
                    @onSelect="itemRemoved" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
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
const hasContactSelected = ref(false);
const hoveredLocation = ref(null);

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
    event.hasSexualContent = hasSexualContent;
};

const showContentAdvisories = () => {
    // This will automatically trigger reactivity to show the advisories when the user types in the textarea
};

const handleSubmit = async () => {
    await onSubmit({ hasLocation: event.hasLocation });
};

const selectContactLevel = (contact) => {
    selectedContact.value = contact;
    hasContactSelected.value = true;
};

const deselectContactLevel = () => {
    selectedContact.value = null;
};

onMounted(() => {
    fetchContactLevels();
    fetchcontentAdvisories();
});
</script>
