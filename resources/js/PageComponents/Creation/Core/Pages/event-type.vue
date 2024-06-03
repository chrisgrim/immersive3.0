<template>
    <main class="w-full">
        <div v-if="!userAccepts && event.hasLocation === null">
            <div class="flex justify-center w-full">
                <div class="w-full">
                    <h5 class="font-bold">Step 2</h5>
                    <h3 class="mt-10 text-6xl mb-8">The right fit:</h3>
                    <p class="font-light">Immersive experiences are experiences that have some type of immersive aspect. They must meet these standards:</p>
                    <div class="rounded-2xl overflow-hidden my-12 w-1/2">
                        <img src="https://a0.muscache.com/pictures/aca23391-4bab-4ddb-91e3-3934147bbcac.jpg" alt="">
                    </div>
                    <ul class="text-2xl mt-8 font-light">
                        <li><span class="font-semibold">Immersive: </span>Must include some way for the user to interact</li>
                        <li><span class="font-semibold">Safe: </span>If the event is in person it needs to be safe for the users.</li>
                    </ul>
                </div>
            </div>
            <div class="w-full flex justify-end">
                <button class="p-4 bg-black text-white rounded-2xl" @click="userAccepts=true">I accept</button>
            </div>
        </div>
        <div v-else>
            <div class="flex flex-col w-full">
                <h2>What type of event are you hosting?</h2>
                <div class="pt-16 flex flex-col gap-8">
                    <button 
                        @click="onSelect(true)"
                        :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.hasLocation === true }"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center w-full pb-4 hover:border-2 hover hover:border-black h-48 p-8">
                        <div class="w-full text-left">
                            <h4 class="font-bold text-3xl">
                                In Person
                            </h4>
                            <p class="text-1xl mt-4 text-gray-700 font-light">
                                Real world events that guests will be part of.
                            </p>
                        </div>
                    </button>
                    <button 
                        @click="onSelect(false)"
                        :class="{ '!border-black !border-2 bg-[#f7f7f7]' : event.hasLocation === false }"
                        class="border-gray-300 border rounded-2xl flex justify-between items-center w-full hover:border-2 hover hover:border-black h-48 p-8">
                        <div class="w-full text-left">
                            <h4 class="font-bold text-3xl">
                                Online Only
                            </h4>
                            <p class="text-1xl mt-4 text-gray-700 font-light">
                                Guests will join the event virtually.
                            </p>
                        </div>
                    </button>
                </div>
            </div>
            <div class="w-full flex justify-end">
                <button class="mt-8 p-4 bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, inject } from 'vue';

const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');

const userAccepts = ref(false);

const onSelect = (hasLocation) => {
    event.hasLocation = hasLocation;
};

const handleSubmit = async () => {
    await onSubmit('hasLocation', event.hasLocation);
};
</script>
