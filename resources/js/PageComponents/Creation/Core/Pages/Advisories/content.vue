<template>
    <div class="flex flex-col w-full">
        <div class="mt-24">
            <h4 class="mb-8">Content Advisories</h4>
            <Dropdown 
                :list="contentAdvisoryList"
                placeholder="Advisories"
                @onSelect="itemSelected" />
            <List 
                class="mt-6"
                :selections="contentAdvisories" 
                @onSelect="itemRemoved" />
        </div>
        <div class="w-full flex justify-end mt-24">
            <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
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
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

const contentAdvisoryList = ref([]);
const contentAdvisories = ref([]);
const hoveredLocation = ref(null);

const itemSelected = async (item) => {
    contentAdvisories.value.push(item);
    contentAdvisoryList.value = contentAdvisoryList.value.filter(advisory => advisory !== item);
}

const itemRemoved = async (item) => {
    contentAdvisories.value = contentAdvisories.value.filter(advisory => advisory !== item);
    contentAdvisoryList.value.push(item);
}

const fetchContentAdvisories = async () => {
    const response = await axios.get(`/api/contentadvisories`);
    contentAdvisoryList.value = response.data;
};

const handleSubmit = async () => {
    await onSubmit({ contentAdvisories: contentAdvisories.value });
    setStep('NextStep');
};

const preselectContentAdvisories = () => {
    if (event.content_advisories && Array.isArray(event.content_advisories)) {
        event.content_advisories.forEach(advisory => {
            const existingAdvisory = contentAdvisoryList.value.find(item => item.name === advisory.name);
            if (existingAdvisory) {
                itemSelected(existingAdvisory);
            } else {
                // If the advisory is not found in the list, create a new item
                const newAdvisory = { id: Date.now(), name: advisory.name };
                contentAdvisoryList.value.push(newAdvisory);
                itemSelected(newAdvisory);
            }
        });
    }
};

onMounted(async () => {
    await fetchContentAdvisories();
    preselectContentAdvisories();
});
</script>
