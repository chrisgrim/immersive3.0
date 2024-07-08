<template>
	<div class="relative">
		<div v-if="selections && selections.length > 0">
	        <ul class="flex flex-wrap gap-6 mx-0">
	            <li 
	                v-for="item in selections"
	                :key="item.id"
	                class="border-2 m-0 h-24 border-[#222222] flex text-[#222222] px-6 pb-4 rounded-2xl relative flex flex-col justify-end hover:border-black hover:bg-gray-100"
	                @mouseenter="hoveredLocation = item.id"
	                @mouseleave="hoveredLocation = null">
	                <div 
	                    @click="removeItem(item)" 
	                    class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white rounded-full">
	                    <component :is="hoveredLocation === item.id ? RiCloseCircleFill : RiCloseCircleLine" />
	                </div>
	                <span class="mt-auto">{{ item.name }}</span>
	            </li>
	        </ul>
	    </div>
	</div>
</template>

<script setup>
import { ref, defineEmits } from 'vue';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";

const props = defineProps({
    selections: {
        type: Object,
        required: true
    },
});

const hoveredLocation = ref(null);

const emit = defineEmits(['onSelect']);

const removeItem = (item) => {
    emit('onSelect', item);
};



</script>