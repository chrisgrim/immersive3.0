<template>
    <main class="w-full">
        <div class="w-full">
            <h2>Ticket Pricing</h2>
            <div class="mt-24">
                <input 
                    class="border rounded-2xl p-4" 
                    type="text" 
                    v-model="formattedName" 
                    @input="updateTicketName"
                    name="Name">
                <div class="flex items-center">
                    <span class="text-[14rem] font-bold text-heavy leading-tight">$</span>
                    <input
                        v-if="currentMedia !== null && tickets[currentMedia]"
                        type="text"
                        name="price"
                        class="text-[12rem] font-bold text-heavy w-full overflow-hidden leading-tight"
                        v-model="formattedPrice"
                        @input="updateTicketPrice"
                    />
                </div>
            </div>
            <div class="mt-2">
                <p class="cursor-pointer underline">Additional ticket details</p>
            </div>
            <div class="grid grid-cols-3 gap-4 mt-32">
                <div 
                    v-for="(ticket, index) in tickets" 
                    :key="index" 
                    @click="handleDivClick(index)"
                    :class="{
                        'border-[#e5e7eb] text-gray-400': !ticket.ticket_price && currentMedia !== index,
                        'border-black text-black border-2 bg-gray-100': ticket.ticket_price || currentMedia === index,
                    }"
                    class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-colors duration-200"
                >
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
            </div>
            <div class="mt-4">
                <button class="px-4 py-2 bg-green-500 text-white rounded-2xl" @click="addTicket">Add Ticket</button>
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

// Inject dependencies provided by the parent
const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');
const errors = inject('errors');

const props = defineProps({
    tickets: {
        type: Array,
        default: () => ([
            { name: 'General', ticket_price: 10 },
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
    tickets.push({ name: `General`, ticket_price: 0 });
};
</script>

<style>
/* Add any necessary styles here */
</style>
