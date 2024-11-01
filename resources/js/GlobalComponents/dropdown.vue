<template>
    <div class="w-full m-auto">
        <div class="w-full relative" ref="dropdownRef" v-click-outside="handleClickOutside">
            <div class="w-full relative">
                <svg 
                    :class="{'rotate-90': dropdown}"
                    class="w-10 h-10 fill-black absolute z-10 right-4 top-8">
                    <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                </svg>
                <input 
                    ref="searchInput"
                    class="text-2xl border-gray-300 relative p-8 w-full border rounded-3xl focus:rounded-t-3xl focus:rounded-b-none h-24"
                    v-model="searchTerm"
                    :placeholder="placeholder"
                    @input="filterRemoteLocations"
                    @focus="onDropdown"
                    @keydown.enter.prevent="handleEnter"
                    autocomplete="off"
                    type="text">
                <ul 
                    class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border-[#e5e7eb] border max-h-[40rem]" 
                    v-if="dropdown">
                    <li 
                        class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300" 
                        v-for="item in filteredItems"
                        :key="item.id"
                        @click="selectItem(item)"
                        @mousedown.stop.prevent>
                        {{ item.name }}
                    </li>
                    <li 
                        v-if="creatable && searchTerm && !filteredItems.length"
                        class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300 cursor-pointer"
                        @click="createItem(searchTerm)"
                        @mousedown.stop.prevent>
                        Create: "{{ searchTerm }}"
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, defineEmits, defineProps, computed } from 'vue';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';

const props = defineProps({
    list: {
        type: Array,
        required: true
    },
    creatable: {
        type: Boolean,
        default: false
    },
    placeholder: {
    	type: String,
    	default: "", 
    }
});

const searchTerm = ref('');
const dropdown = ref(false);
const searchInput = ref(null);
const dropdownRef = ref(null);

const emit = defineEmits(['onSelect', 'onCreate']);

const filteredItems = computed(() => {
    if (!searchTerm.value) {
        return props.list;
    }
    const lowercasedSearchTerm = searchTerm.value.toLowerCase();
    return props.list.filter(item =>
        item.name.toLowerCase().includes(lowercasedSearchTerm)
    );
});

const selectItem = (item) => {
    emit('onSelect', item);

    searchTerm.value = '';
    dropdown.value = false;
    searchInput.value.blur();  // Remove focus from the input field
};

const createItem = (name) => {
    const newItem = { id: Date.now(), name }; // Generate a temporary ID for the new item
    emit('onSelect', newItem);

    searchTerm.value = '';
    dropdown.value = false;
    searchInput.value.blur();  // Remove focus from the input field
};

const onDropdown = () => {
    dropdown.value = true;
};

const closeDropdown = () => {
    dropdown.value = false;
};

const handleClickOutside = (event) => {
    const dropdownElement = dropdownRef.value;
    if (dropdownElement && !dropdownElement.contains(event.target)) {
        closeDropdown();
    }
};

const handleEnter = () => {
    if (props.creatable && searchTerm.value && !filteredItems.value.length) {
        createItem(searchTerm.value);
    } else if (filteredItems.value.length > 0) {
        selectItem(filteredItems.value[0]);
    }
};

</script>
