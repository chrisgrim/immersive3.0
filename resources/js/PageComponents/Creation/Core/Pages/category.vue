<template>
    <div>
        <div class="flex justify-center w-full p-8">
            <div class="">
                <h2>What Category does your event fall into?</h2>
            </div>
        </div>
        <div class="w-full m-auto">
            <div class="w-full relative" ref="categoryDrop" v-click-outside="handleClickOutside">
                <svg 
                    :class="{'rotate-90': dropdown}"
                    class="w-10 h-10 fill-black absolute z-10 right-4 top-8 cursor-pointer" 
                    @click="onDropdown">
                    <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                </svg>
                <input 
                    class="text-2xl relative p-8 w-full border mb-12 rounded-3xl focus:rounded-3xl"
                    v-model="category"
                    placeholder="Select Category"
                    @input="filterCategories"
                    @focus="onDropdown"
                    autocomplete="off"
                    type="text">
                <div v-if="event.category">
                    <img 
                        class="h-[30rem] w-full object-cover rounded-3xl" 
                        :src="`${imageUrl}${event.category.largeImagePath}`" 
                        alt="">
                    <p class="text-xl mt-8">
                        {{ event.category.description }}
                    </p>
                </div>
                <ul 
                    class="overflow-auto bg-white w-full list-none rounded-b-3xl absolute top-24 m-0 z-10 border-[#e5e7eb] border max-h-[45rem]" 
                    v-if="dropdown">
                    <li 
                        class="py-6 px-6 flex items-center gap-8 hover:bg-gray-300 cursor-pointer" 
                        v-for="item in filteredCategories"
                        :key="item.id"
                        @click="selectCategory(item)">
                        <img 
                            class="w-16" 
                            :src="`${imageUrl}${item.thumbImagePath}`" 
                            alt="">
                        {{ item.name }}
                    </li>
                </ul>
            </div>
        </div>
        <div class="w-full flex justify-end">
            <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js';

const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const category = ref('');
const dropdown = ref(false);
const categoryList = ref([]);
const filteredCategories = ref([]);
const categoryDrop = ref(null);

const fetchCategories = async () => {
    try {
        const remote = event.hasLocation ? 0 : 1;
        const response = await axios.get(`/api/categories?remote=${remote}`);
        categoryList.value = response.data;
        filteredCategories.value = categoryList.value;

        // Automatically select the category if event.category_id is set
        if (event.category_id) {
            const selectedCategory = categoryList.value.find(cat => cat.id === event.category_id);
            if (selectedCategory) {
                selectCategory(selectedCategory);
            }
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
};

const onDropdown = () => {
    dropdown.value = true;
};

const selectCategory = (item) => {
    event.category = item;
    event.category_id = item.id; // Ensure event.category_id is updated
    category.value = item.name;
    dropdown.value = false;
};

const handleSubmit = async () => {
    await onSubmit({ category_id: event.category.id });
    setStep('NextStep');
};

const handleClickOutside = (event) => {
    const dropdownElement = categoryDrop.value;
    if (dropdownElement && !dropdownElement.contains(event.target) && !dropdownElement.contains(document.activeElement)) {
        dropdown.value = false;
    }
};

// Fetch categories when component mounts
onMounted(fetchCategories);
</script>

<style scoped>
.rotate-90 {
    transform: rotate(90deg);
}
</style>
