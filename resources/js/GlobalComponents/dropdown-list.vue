<template>
	<div class="relative">
		<div v-if="selections && selections.length > 0">
	        <ul class="flex flex-wrap gap-6 mx-0">
	            <li 
	                v-for="item in selections"
	                :key="item.id"
	                :class="[
	                    'border-2 m-0 border-[#222222] flex text-[#222222] px-6 p-4 rounded-2xl relative flex flex-col justify-end hover:border-black hover:bg-gray-100',
	                    itemHeight || ''
	                ]"
	                @mouseenter="hoveredLocation = item.id"
	                @mouseleave="hoveredLocation = null">
	                <div 
	                    @click="removeItem(item)" 
	                    class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white p-[0.1rem] rounded-full opacity-100 transition-opacity duration-200 z-10 dropdown-delete-btn"
	                >
	                    <component 
	                        :is="hoveredLocation === item.id ? RiCloseCircleFill : RiCloseCircleLine" 
	                        class="text-red-500 hover:text-red-600 transition-colors dropdown-delete-icon"
	                    />
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
        type: Array,
        required: true
    },
    itemHeight: {
        type: String,
        default: null
    }
});

const hoveredLocation = ref(null);

const emit = defineEmits(['onSelect']);

const removeItem = (item) => {
    emit('onSelect', item);
};



</script>

<style>
/* Simple hover effects matching tickets.vue */
.dropdown-delete-btn:hover .dropdown-delete-icon {
  transform: scale(1.1);
}

.dropdown-delete-icon {
  transition: transform 0.2s ease;
}
</style>