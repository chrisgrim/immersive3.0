<template>
    <div class="w-full h-screen">   
        <nav class="h-full">
            <div class="h-full border-r w-full">
                <div class="h-full overflow-auto px-12 py-8">
                    <!-- Nav menu -->
                    <div class="relative">
                        <ul>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('EventType')" @click="handleStepChange('EventType')">
                                Event Type
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Category')" @click="handleStepChange('Category')">
                                Category
                            </li>
                            <li v-if="event.attendance_type_id === 1 || event.hasLocation" class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Location')" @click="handleStepChange('Location')">
                                Location
                            </li>
                            <li v-else class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Remote')" @click="handleStepChange('Remote')">
                                Remote
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Description')" @click="handleStepChange('Description')">
                                Description
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Name')" @click="handleStepChange('Name')">
                                Name
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Dates')" @click="handleStepChange('Dates')">
                                Dates
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Tickets')" @click="handleStepChange('Tickets')">
                                Tickets
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Images')" @click="handleStepChange('Images')">
                                Images
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Advisories')" @click="handleStepChange('Advisories')">
                                Advisories
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Content')" @click="handleStepChange('Content')">
                                Content
                            </li>
                            <li class="cursor-pointer hover:bg-gray-300" :class="getStepClass('Mobility')" @click="handleStepChange('Mobility')">
                                Mobility
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </div>
</template>

<script setup>
import { defineProps, defineEmits, inject } from 'vue';

const props = defineProps({
    currentStep: {
        type: String,
        required: true
    }
});

const emit = defineEmits(['set-step', 'submit']);
const event = inject('event');

const handleStepChange = (step) => {
    emit('set-step', step);
};

const getStepClass = (step) => {
    return props.currentStep === step 
        ? 'p-4 rounded-full mr-4 mb-4 border-2 border-black' 
        : 'p-4 rounded-full mr-4 mb-4';
};
</script>
