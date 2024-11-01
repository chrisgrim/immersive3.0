<template>
    <div>
        <!-- Header -->
        <div class="flex justify-center w-full p-8">
            <h2>What Category does your event fall into?</h2>
        </div>

        <!-- Main Content -->
        <div class="w-full m-auto">
            <div class="w-full relative" ref="categoryDrop" v-click-outside="handleClickOutside">
                <!-- Dropdown Arrow -->
                <svg 
                    :class="{'transform rotate-90': dropdown}"
                    class="w-10 h-10 fill-black absolute z-10 right-4 top-8 cursor-pointer" 
                    @click="onDropdown"
                >
                    <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                </svg>

                <!-- Category Input -->
                <input 
                    :class="{ 'border-red-500': showError }"
                    class="text-2xl relative p-8 w-full border mb-12 rounded-3xl focus:rounded-3xl"
                    v-model="category"
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
                <div v-if="event.category">
                    <img 
                        class="h-[30rem] w-full object-cover rounded-3xl" 
                        :src="`${imageUrl}${event.category.largeImagePath}`" 
                        :alt="event.category.name"
                    >
                    <p class="text-xl mt-8">{{ event.category.description }}</p>
                </div>

                <!-- Category Dropdown -->
                <ul v-if="dropdown" 
                    class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border-[#e5e7eb] border max-h-[45rem]"
                >
                    <li v-for="item in filteredCategories"
                        :key="item.id"
                        class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300 cursor-pointer" 
                        @click="selectCategory(item)"
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

        <!-- Submit Button -->
        <div class="w-full flex justify-end">
            <button 
                class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl disabled:opacity-50 disabled:cursor-not-allowed" 
                @click="handleSubmit"
                :disabled="isSubmitting"
            >
                Next
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, inject, computed } from 'vue';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';
import useVuelidate from '@vuelidate/core';
import { required } from '@vuelidate/validators';

// Injected values
const event = inject('event');
const isSubmitting = inject('isSubmitting');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

// Constants
const imageUrl = import.meta.env.VITE_IMAGE_URL;

// Reactive refs
const category = ref('');
const dropdown = ref(false);
const categoryList = ref([]);
const filteredCategories = ref([]);
const categoryDrop = ref(null);

// Validation setup
const rules = { 'event.category_id': { required } };
const $v = useVuelidate(rules, { event });

// Computed
const showError = computed(() => $v.value.$dirty && !event.category_id);

// Methods
const fetchCategories = async () => {
    try {
        const remote = event.hasLocation ? 0 : 1;
        const response = await axios.get(`/api/categories?remote=${remote}`);
        categoryList.value = response.data;
        filteredCategories.value = categoryList.value;

        if (event.category_id) {
            const selectedCategory = categoryList.value.find(cat => cat.id === event.category_id);
            if (selectedCategory) selectCategory(selectedCategory);
        }
    } catch (error) {
        console.error('Failed to fetch categories:', error);
    }
};

const filterCategories = () => {
    const searchTerm = category.value.toLowerCase();
    filteredCategories.value = categoryList.value.filter(item => 
        item.name.toLowerCase().includes(searchTerm)
    );
    if (category.value) $v.value.$reset();
};

const selectCategory = (item) => {
    event.category = item;
    event.category_id = item.id;
    category.value = item.name;
    dropdown.value = false;
    $v.value.$reset();
};

const handleSubmit = async () => {
    $v.value.$touch();
    if (!event.category_id) return;
    
    await onSubmit({ category_id: event.category_id });
    setStep('NextStep');
};

const handleClickOutside = (event) => {
    const dropdownElement = categoryDrop.value;
    if (dropdownElement && !dropdownElement.contains(event.target) && 
        !dropdownElement.contains(document.activeElement)) {
        dropdown.value = false;
    }
};

const onDropdown = () => dropdown.value = true;

// Lifecycle
onMounted(fetchCategories);
</script>
