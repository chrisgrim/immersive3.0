<template>
    <div class="flex flex-col w-full">
        <div class="mt-24">
            <h2>Mobility Advisories</h2>
            <div class="mt-6" v-if="hasSelectedWheelchair">
            	<p class="font-strong mt-16">Select additinal mobility restrictions</p>
                <Dropdown 
                	class="mt-4"
                    :list="mobilityAdvisoryList"
                    placeholder="Additional advisories"
                    @onSelect="itemSelected" />
                <List 
                	class="mt-6"
                    :selections="mobilityAdvisories" 
                    @onSelect="itemRemoved" />
            </div>
            <div v-else class="mt-14">
            	<p class="font-strong">Is your event Wheelchair accessible?</p>
            	<div class="flex flex-row gap-8 relative mt-6">
            		<button 
	                    @click="onSelectWheelchair(false)"
	                    :class="{ '!border-black !border-2 bg-[#f7f7f7]' : wheelchairAccessible === false }"
	                    class="border-gray-300 border rounded-2xl flex justify-between items-center hover:shadow-[0_0_0_1.5px_black] hover hover:border-black px-12 py-8">
	                    <div class="text-left">
	                        <h4 class="font-bold text-3xl">
	                            No
	                        </h4>
	                    </div>
	                </button>
	                <button 
	                    @click="onSelectWheelchair(true)"
	                    :class="{ '!border-black !border-2 bg-[#f7f7f7]' : wheelchairAccessible === true }"
	                    class="border-gray-300 border rounded-2xl flex justify-between items-center hover:shadow-[0_0_0_1.5px_black] hover hover:border-black px-12 py-8">
	                    <div class="text-left">
	                        <h4 class="font-bold text-3xl">
	                            Yes
	                        </h4>
	                    </div>
	                </button>
            	</div>
            </div>
        </div>
        <div class="w-full flex justify-end mt-24">
            <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
        </div>
    </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
import axios from 'axios';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

const mobilityAdvisoryList = ref([]);
const mobilityAdvisories = ref([]);
const hasSelectedWheelchair = ref(false);
const wheelchairAccessible = ref(null);

const itemSelected = (item) => {
    mobilityAdvisories.value.push(item);
    mobilityAdvisoryList.value = mobilityAdvisoryList.value.filter(advisory => advisory.id !== item.id);
};

const itemRemoved = (item) => {
    mobilityAdvisories.value = mobilityAdvisories.value.filter(advisory => advisory.id !== item.id);
    mobilityAdvisoryList.value.push(item);
};

const fetchMobilityAdvisories = async () => {
    const response = await axios.get(`/api/mobilityadvisories`);
    mobilityAdvisoryList.value = response.data;
};

const handleSubmit = async () => {
    await onSubmit({ mobilityAdvisories: mobilityAdvisories.value });
    setStep('NextStep');
};

const onSelectWheelchair = (isAccessible) => {
    wheelchairAccessible.value = isAccessible;
    hasSelectedWheelchair.value = true;
    
    const wheelchairAdvisory = mobilityAdvisoryList.value.find(advisory => advisory.slug === 'wheelchair-accessible');
    
    if (isAccessible && wheelchairAdvisory) {
        itemSelected(wheelchairAdvisory);
    } else {
        const existingAdvisory = mobilityAdvisories.value.find(advisory => advisory.slug === 'wheelchair-accessible');
        if (existingAdvisory) {
            itemRemoved(existingAdvisory);
        }
    }
};

const preselectMobilityAdvisories = () => {
    if (event.mobility_advisories && Array.isArray(event.mobility_advisories)) {
        event.mobility_advisories.forEach(advisory => {
            const existingAdvisory = mobilityAdvisoryList.value.find(item => item.name === advisory.name);
            if (existingAdvisory) {
                itemSelected(existingAdvisory);
            } else {
                const newAdvisory = { id: Date.now(), name: advisory.name };
                mobilityAdvisoryList.value.push(newAdvisory);
                itemSelected(newAdvisory);
            }
        });
    }
    // Set hasSelectedWheelchair to true if mobilityAdvisories has elements
    if (mobilityAdvisories.value.length > 0) {
        hasSelectedWheelchair.value = true;
    }
};

onMounted(async () => {
    await fetchMobilityAdvisories();
    preselectMobilityAdvisories();
});
</script>
