<template>
    <div class="mx-[-12px] my-2">
        <div 
            @click="onDropdown"
            :class="{'border-black border-2 m-[-2px]': inputVal.category === 'GettingStarted' }"
            class="starting-section items-center flex p-4 rounded-full cursor-pointer hover:bg-gray-200">
            <div class="flex-[0_0_auto] mr-2">
                <svg 
                    :class="{'rotate-90': dropdown}"
                    class="w-8 h-8 fill-gray-400">
                    <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                </svg>
            </div>
            <div 
                :class="{'!text-gray-500': dropdown }"
                class="flex-1 text-gray-400">
                Getting Started
            </div>
            <div 
                v-if="!dropdown"
                class="flex-[0_0_auto]">
                <svg 
                    :class="[{ 'fill-black': hasAll }, { 'fill-orange-400': !hasAll }]"
                    class="w-8 h-8">
                    <use v-if="hasAll" :xlink:href="`/storage/website-files/icons.svg#ri-checkbox-circle-fill`" />
                    <use v-else :xlink:href="`/storage/website-files/icons.svg#ri-question-fill`" />
                </svg>
            </div>
        </div>
        <div v-if="dropdown">
            <ul class="list-none mr-[-9px] ml-12">
                <li @click="showEventType">
                    <div 
                        :class="{'border-black border-2 m-[-2px]': inputVal.page === 'EventType' }"
                        class="flex justify-between items-center p-3 rounded-full cursor-pointer hover:bg-gray-200">
                        <div class="text-xl tracking-wide"> Event Type </div>
                        <div>
                            <svg 
                                :class="[{ 'fill-black-400': hasEventType }, { 'fill-orange-400': !hasEventType }]"
                                class="w-8 h-8">
                                <use v-if="hasEventType" :xlink:href="`/storage/website-files/icons.svg#ri-checkbox-circle-fill`" />
                                <use v-else :xlink:href="`/storage/website-files/icons.svg#ri-question-fill`" />
                            </svg>
                        </div>
                    </div>
                </li>
                <li @click="showCategory">
                    <div 
                        :class="{'border-black border-2 m-[-2px]': inputVal.page === 'Category' }"
                        class="flex justify-between items-center p-3 rounded-full cursor-pointer hover:bg-gray-200">
                        <div class="text-xl tracking-wide"> Category</div>
                        <div>
                            <svg 
                                :class="[{ 'fill-black-400': hasCategory }, { 'fill-orange-400': !hasCategory }]"
                                class="w-8 h-8">
                                <use v-if="hasCategory" :xlink:href="`/storage/website-files/icons.svg#ri-checkbox-circle-fill`" />
                                <use v-else :xlink:href="`/storage/website-files/icons.svg#ri-question-fill`" />
                            </svg>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    value: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['input']);

const inputVal = computed({
    get() {
        return props.value;
    },
    set(val) {
        emit('input', val);
    }
});

const dropdown = ref(['EventType', 'Category'].includes(inputVal.value.page));

const hasEventType = computed(() => {
    return inputVal.value.event.hasLocation !== null;
});

const hasCategory = computed(() => {
    return inputVal.value.event.category_id;
});

const hasAll = computed(() => {
    return hasEventType.value && hasCategory.value;
});

function onDropdown() {
    dropdown.value = !dropdown.value;
    inputVal.value.category = 'GettingStarted';
}

function showEventType() {
    inputVal.value.category = '';
    inputVal.value.page = 'EventType';
}

function showCategory() {
    inputVal.value.category = '';
    inputVal.value.page = 'Category';
}
</script>
