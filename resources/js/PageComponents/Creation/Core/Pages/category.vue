<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <!-- Header -->
            <div class="w-full">
                <h2 class="text-black">What category does your event fall into?</h2>
            </div>

            <!-- Main Content -->
            <div class="w-full mt-14">
                <div class="w-full relative" ref="categoryDrop" v-click-outside="handleClickOutside">
                    <!-- Dropdown Arrow -->
                    <svg 
                        :class="{'rotate-90': state.dropdown}"
                        class="w-10 h-10 fill-black absolute z-10 right-4 top-8 transition-transform duration-200" 
                        @click="onDropdown"
                    >
                        <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                    </svg>

                    <!-- Category Input -->
                    <input 
                        :class="[
                            'text-2.5xl relative p-8 w-full border rounded-3xl transition-all duration-200',
                            {
                                'border-red-500 hover:border-red-500 focus:border-red-500 focus:shadow-focus-error': showError,
                                'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !showError,
                                'focus:rounded-t-3xl focus:rounded-b-none': state.dropdown
                            }
                        ]"
                        v-model="state.category"
                        placeholder="Select Category"
                        @input="filterCategories"
                        @focus="onDropdown"
                        autocomplete="off"
                        type="text"
                    >
                    
                    <!-- Error Message -->
                    <p v-if="showError" class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Please select a category
                    </p>

                    <!-- Selected Category Preview -->
                    <div class="mt-12" v-if="event.category">
                        <div class="aspect-video w-full rounded-3xl overflow-hidden">
                            <img 
                                v-if="event.category.images?.[0]?.largeImagePath || event.category.largeImagePath"
                                class="w-full h-full object-cover" 
                                :src="`${imageUrl}${event.category.images?.[0]?.largeImagePath || event.category.largeImagePath}`" 
                                :alt="event.category.name"
                            >
                        </div>
                        <p class="text-xl mt-8">{{ event.category.description }}</p>
                    </div>

                    <!-- Category Dropdown -->
                    <ul v-if="state.dropdown" 
                        class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border border-[#222222] shadow-focus-black max-h-[45rem]"
                    >
                        <li v-for="item in state.filteredCategories"
                            :key="item.id"
                            class="py-6 px-6 flex items-center gap-8 hover:bg-neutral-100 transition-colors duration-200 cursor-pointer" 
                            @click="selectCategory(item)"
                            @mousedown.stop.prevent
                        >
                            <img 
                                class="w-16" 
                                :src="`${imageUrl}${item.thumbImagePath}`" 
                                :alt="item.name"
                            >
                            {{ item.name }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
// 1. Imports
import { ref, onMounted, inject, computed } from 'vue';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';
import useVuelidate from '@vuelidate/core';
import { required } from '@vuelidate/validators';

// 2. Injected Dependencies
const event = inject('event');
const errors = inject('errors');

// 3. Constants
const imageUrl = import.meta.env.VITE_IMAGE_URL;

// 4. State Management
const state = ref({
    category: '',
    dropdown: false,
    categoryList: [],
    filteredCategories: []
});
const categoryDrop = ref(null);

// 5. Validation Rules
const rules = { 
    category_id: { required }
};

const $v = useVuelidate(rules, {
    category_id: computed(() => event.category_id)
});

// 6. Computed Properties
const showError = computed(() => {
    return $v.value.$dirty && $v.value.$error;
});

// 7. Methods
const fetchCategories = async () => {
    try {
        const attendanceTypeId = event.attendance_type_id || (event.hasLocation ? 1 : 2);
        const response = await axios.get(`/api/categories?attendance_type_id=${attendanceTypeId}`);
        state.value.categoryList = response.data;
        state.value.filteredCategories = state.value.categoryList;

        if (event.category_id) {
            const selectedCategory = state.value.categoryList.find(cat => cat.id === event.category_id);
            if (selectedCategory) selectCategory(selectedCategory);
        }
    } catch (error) {
        errors.value = { category: ['Failed to load categories'] };
    }
};

const filterCategories = () => {
    const searchTerm = state.value.category.toLowerCase();
    state.value.filteredCategories = state.value.categoryList.filter(item => 
        item.name.toLowerCase().includes(searchTerm)
    );
    if (state.value.category) $v.value.$reset();
};

const selectCategory = (item) => {
    event.category = item;
    event.category_id = item.id;
    state.value.category = item.name;
    state.value.dropdown = false;
    $v.value.$reset();
    
    // Blur the active input to dismiss the keyboard on mobile
    if (document.activeElement) {
        document.activeElement.blur();
    }
};

const handleClickOutside = (event) => {
    const dropdownElement = categoryDrop.value;
    if (dropdownElement && !dropdownElement.contains(event.target) && 
        !dropdownElement.contains(document.activeElement)) {
        state.value.dropdown = false;
    }
};

const onDropdown = () => state.value.dropdown = true;

// 8. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { category: ['Please select a category'] };
        }
        
        return isValid;
    },
    submitData: () => ({
        category_id: event.category_id
    })
});

// 9. Lifecycle Hooks
onMounted(fetchCategories);
</script>
