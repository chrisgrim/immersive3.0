<template>
    <main class="w-full">
        <div class="w-full">
            <div class="flex items-center">
                <input 
                    class="w-[22rem] border rounded-2xl px-4 text-5xl" 
                    type="text" 
                    v-model="formattedName" 
                    @input="updateTicketName"
                    name="Name">
            </div>
            <div class="mt-12">
                <div class="flex items-center">
                    <span class="text-[11rem] font-bold text-heavy leading-tight">$</span>
                    <input
                        v-if="currentMedia !== null && tickets[currentMedia]"
                        type="text"
                        name="price"
                        class="text-[11rem] font-bold text-heavy w-full overflow-hidden leading-tight"
                        v-model="formattedPrice"
                        @input="updateTicketPrice"
                    />
                </div>
            </div>
            <div class="mt-2">
                <p class="cursor-pointer underline">Additional ticket details</p>
            </div>
            <div class="grid grid-cols-3 gap-4 mt-28">
                <div 
                    v-for="(ticket, index) in tickets" 
                    :key="index" 
                    @mouseenter="hoveredLocation = index"
                    @mouseleave="hoveredLocation = null"
                    @click="handleDivClick(index)"
                    :class="{
                        'border-[#e5e7eb] text-gray-400': !ticket.ticket_price && currentMedia !== index,
                        'border-black text-black border-2 bg-gray-100': ticket.ticket_price || currentMedia === index,
                    }"
                    class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-colors duration-200"
                    >
                    <div 
                        v-if="tickets.length > 1"
                        @click.stop="removeTicket(index)" 
                        class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                    >
                        <component :is="hoveredLocation === index ? RiCloseCircleFill : RiCloseCircleLine" />
                    </div>
                    <div class="flex items-start justify-start w-full h-16 rounded-2xl">
                        {{ ticket.name }}
                    </div>
                    <div class="overflow-hidden w-full">
                        <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap"
                            :class="{
                                'text-[#adadad]': !ticket.ticket_price,
                                'text-black': ticket.ticket_price,
                            }">
                            ${{ ticket.ticket_price.toFixed(2) }}
                        </h4>
                    </div>
                </div>
                <div 
                    @click="addTicket"
                    class="relative h-48 flex flex-col items-center justify-center p-4 border border-dashed border-[#e5e7eb] text-gray-400 rounded-2xl transition-colors duration-200 cursor-pointer hover:bg-gray-100"
                >
                    <span class="text-2xl font-bold">+</span>
                    <span class="text-lg">Add Ticket</span>
                </div>
            </div>
        </div>
        <div class="w-full flex justify-end">
            <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
        </div>
    </main>
</template>

<script setup>
import { ref, reactive, inject, nextTick, watch } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";

// Inject dependencies provided by the parent
const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');
const errors = inject('errors');

const props = defineProps({
    tickets: {
        type: Array,
        default: () => ([
            { name: 'Ticket Name', ticket_price: 10 },
        ])
    },
});

const tickets = reactive(props.tickets);

// Setup Vuelidate for form validation
const rules = {
    event: {
        description: {
            required,
            maxLength: maxLength(30000),
        }
    }
};
const $v = useVuelidate(rules, { event });

const inputRefs = ref({});
const currentMedia = ref(0);
const selectedTicketPrice = ref(tickets[currentMedia.value].ticket_price);
const formattedPrice = ref(tickets[currentMedia.value].ticket_price.toFixed(2));
const formattedName = ref(tickets[currentMedia.value].name);
const hoveredLocation = ref(null);

watch(currentMedia, (newIndex) => {
    if (tickets[newIndex]) {
        selectedTicketPrice.value = tickets[newIndex].ticket_price;
        formattedPrice.value = tickets[newIndex].ticket_price.toFixed(2);
        formattedName.value = tickets[newIndex].name;
    }
});

const handleSubmit = async () => {
    errors.value = {};
    const isFormValid = await $v.value.$validate();
    if (!isFormValid) {
        return;
    }

    // Submit the description and tickets to the parent component
    await onSubmit({ description: event.description, tickets });
    setStep('NextStep'); // Adjust 'NextStep' to the actual next step name
};

const handleDivClick = (index) => {
    currentMedia.value = index;
    selectedTicketPrice.value = tickets[index].ticket_price;
    formattedPrice.value = tickets[index].ticket_price.toFixed(2);
    formattedName.value = tickets[index].name;
    nextTick(() => {
        if (inputRefs.value[index]) {
            inputRefs.value[index].focus();
        }
    });
};

const updateTicketPrice = (e) => {
    let value = e.target.value;

    // Remove invalid characters
    value = value.replace(/[^\d.]/g, '');

    // Ensure only one decimal point is present
    const decimalIndex = value.indexOf('.');
    if (decimalIndex !== -1) {
        value = value.substring(0, decimalIndex + 1) + value.substring(decimalIndex + 1).replace(/\./g, '');
    }

    formattedPrice.value = value;

    if (value) {
        tickets[currentMedia.value].ticket_price = parseFloat(value);
    } else {
        tickets[currentMedia.value].ticket_price = 0;
    }
};

const updateTicketName = (e) => {
    formattedName.value = e.target.value;
    tickets[currentMedia.value].name = e.target.value;
};

const addTicket = () => {
    tickets.push({ name: `Ticket Name ${tickets.length + 1}`, ticket_price: 0 });
};

const removeTicket = (index) => {
    if (tickets.length > 1) {
        tickets.splice(index, 1);
        if (currentMedia.value >= index) {
            currentMedia.value = Math.max(0, currentMedia.value - 1);
        }
    }
};
</script>

<style>
/* Add any necessary styles here */
</style>
