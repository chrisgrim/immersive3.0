<template>
    <main class="w-full">
        <div class="w-full">
            <h2>Add some photos of your event</h2>
            <p class="text-gray-500 font-normal mt-4 mb-8">Add up to 5 images of your event. Drag to reorder </p>
            <div 
                v-if="images.length === 0"
                class="mt-6 outline-dashed rounded-2xl h-[27vw] flex justify-center items-center relative"
                @dragover.prevent
                @drop.prevent="handleDrop"
            >
                <input type="file" multiple @change="handleFileChange" class="hidden" ref="fileInput" accept="image/*" />
                <div class="flex flex-col items-center">
                    <component :is="RiImageCircleLine" style="width:6rem; height: 6rem;"/>
                    <h2 class="font-bold text-4xl mt-4">Drag and Drop</h2>
                    <p class="text-1xl mt-4">Or browse for photos</p>
                    <button @click="browseFiles" class="bg-black text-white py-4 px-8 rounded-2xl mt-4">Browse</button>
                </div>
            </div>
            <draggable 
                v-else 
                class="dragArea grid grid-cols-2 gap-4 w-full" 
                v-model="images"
                handle=".handle"
                :move="onMoveCallback"
            >
                <template v-for="(image, index) in images" :key="index">
                    <div 
                        v-if="image" 
                        :class="index === 0 ? 'col-span-2 aspect-[3/2] relative handle draggable-image' : 'relative aspect-[3/2] handle draggable-image'" 
                    >
                        <img :src="image.url" class="w-full h-full object-cover rounded-2xl" />
                        <button @click="removeImage(index)" class="absolute top-2 right-2 bg-white text-black rounded-full p-1 shadow">X</button>
                    </div>
                </template>
                <template v-for="i in 5 - images.length" :key="'empty-' + i">
                    <div 
                        class="relative aspect-[3/2] flex items-center justify-center border border-dashed border-black rounded-2xl cursor-pointer non-draggable hover:border-black hover:border-2 hover:border-solid"
                        @click="triggerFileInput"
                    >
                        <input type="file" multiple @change="handleFileChange" class="hidden fileInput" ref="fileInput" accept="image/*" />
                        <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
                    </div>
                </template>
            </draggable>
        </div>
        <div class="w-full flex justify-end">
            <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
import { RiImageCircleLine } from "@remixicon/vue";
import useVuelidate from '@vuelidate/core';
import { VueDraggableNext as draggable } from 'vue-draggable-next';

const imageUrl = import.meta.env.VITE_IMAGE_URL;

// Inject dependencies provided by the parent
const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');
const errors = inject('errors');

const dragging = ref(false);
const enabled = ref(true);
const log = (event) => {
  console.log(event);
};

// State for the images
const images = ref([]);

// Reference for the file input
const fileInput = ref(null);

// Method to handle file browsing
const browseFiles = () => {
    if (fileInput.value) {
        fileInput.value.click();
    } else {
        console.error("fileInput is not defined");
    }
};

// Method to handle file changes from input
const handleFileChange = (event) => {
    const files = event.target.files;
    processFiles(files);
};

// Method to handle drop event
const handleDrop = (event) => {
    const files = event.dataTransfer.files;
    processFiles(files);
};

// Method to process files
const processFiles = (files) => {
    if (files.length + images.value.length > 5) {
        alert('You can only add up to 5 images.');
        return;
    }
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const reader = new FileReader();
        reader.onload = (e) => {
            images.value.push({ url: e.target.result, file });
        };
        reader.readAsDataURL(file);
    }
};

// Method to remove an image
const removeImage = (index) => {
    images.value.splice(index, 1);
};

// Load existing images if they exist
onMounted(() => {
    if (event && event.images) {
        event.images.forEach(image => {
            images.value.push({ url: `${imageUrl}${image.large_image_path}` });
        });
    }
});

const handleSubmit = async () => {
    const formData = new FormData();
    const currentImages = [];

    images.value.forEach((image, index) => {
        if (image.file) {
            formData.append(`images[${index}]`, image.file);
            formData.append(`ranks[${index}]`, index);
        } else {
            currentImages.push({ url: image.url.replace(imageUrl, ''), rank: index });
        }
    });

    formData.append('currentImages', JSON.stringify(currentImages));

    await onSubmit(formData);
    setStep('NextStep'); 
};

// Method to trigger the file input from non-draggable divs
const triggerFileInput = (event) => {
    const input = event.currentTarget.querySelector('.fileInput');
    if (input) {
        input.click();
    } else {
        console.error("fileInput element not found");
    }
};
</script>

<style>
div > .sortable-ghost:first-child {
    grid-column: span 2 / span 2;
}
div > .sortable-ghost:first-child + div {
    grid-column: span 1 / span 1;
}
.draggable-image {
    cursor: grab;
}

.draggable-image:active {
    cursor: grabbing;
}
</style>
