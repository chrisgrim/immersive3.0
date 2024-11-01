<template>
    <main class="w-full">
        <div class="w-full">
            <div class="flex items-center">
                <div>
                    <input 
                        class="w-96 border border-[#e5e7eb] text-[#222222] text-3xl p-4 rounded-2xl relative focus:border-black focus:shadow-[0_0_0_1.5px_black]" 
                        type="text" 
                        v-model="formattedName" 
                        @input="updateTicketName"
                        name="Name">
                    <div class="text-gray-500 mt-1 text-right text-2xl">
                        {{ formattedName.length }}/40
                    </div>
                    <div v-if="$v.$anyDirty && $v.tickets.$each.$response.$data[currentMedia].name.$error" class="text-red-500 mt-4">
                        <span v-for="error in $v.tickets.$each.$response.$errors[currentMedia].name" :key="error.$validator">
                            {{
                                error.$validator === 'required' ? 'Ticket name is required.' :
                                error.$validator === 'maxLength' ? 'Too many characters.' :
                                error.$validator === 'uniqueTicketName' ? 'Ticket name must be unique.' :
                                error.$validator === 'ticketNameNotFreeWithNonZeroPrice' ? 'Free ticket must be free.' : ''
                            }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="mt-12">
                <div class="flex items-center">
                    <span 
                        class="text-[11rem] font-bold text-heavy leading-tight cursor-pointer"
                        @click="toggleCurrencyDropdown"
                    >{{ selectedCurrency }}</span>
                    <input
                        v-if="currentMedia !== null && tickets[currentMedia]"
                        type="text"
                        name="price"
                        class="text-[11rem] font-bold text-heavy w-full overflow-hidden leading-tight ml-4"
                        v-model="formattedPrice"
                        @input="updateTicketPrice"
                        @focus="selectPriceInput"
                    />
                    <div v-if="$v.$anyDirty && $v.tickets.$each.$response.$data[currentMedia].ticket_price.$error" class="text-red-500">
                        <span v-if="$v.tickets.$each.$response.$errors[currentMedia].ticket_price.$minValue">
                            Price cannot be negative.
                        </span>
                        <span v-else-if="$v.tickets.$each.$response.$errors[currentMedia].ticket_price.$maxValue">
                            Price cannot exceed ${{ MAX_TICKET_PRICE.toLocaleString() }}.
                        </span>
                        <span v-else>
                            Please enter a valid price.
                        </span>
                    </div>
                </div>
                <div v-if="showCurrencyDropdown" class="absolute mt-2 border border-gray-300 rounded-lg bg-white shadow-lg z-50">
                    <ul class="flex flex-col m-0">
                        <li 
                            v-for="currency in currencySymbols" 
                            :key="currency" 
                            @click="selectCurrency(currency)"
                            class="w-full p-4 cursor-pointer hover:bg-gray-100 text-center"
                        >{{ currency }}</li>
                    </ul>
                </div>
            </div>
            <div class="mt-2 h-20 flex items-center">
                <div v-if="showAdditionalDetails" class="relative mt-4 w-full">
                    <input 
                        class="border border-[#e5e7eb] text-[#222222] text-3xl p-4 rounded-2xl relative focus:border-black focus:shadow-[0_0_0_1.5px_black] w-full" 
                        type="text" 
                        v-model="formattedDescription" 
                        @input="updateAdditionalDetails"
                        name="AdditionalDetails"
                        :maxlength="MAX_DESCRIPTION_LENGTH"
                    >
                    <div 
                        @click="toggleAdditionalDetails" 
                        class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white"
                    >
                        <component :is="RiCloseCircleFill" />
                    </div>
                    <div class="flex justify-end mt-1 text-gray-500">
                        <span v-if="$v.$anyDirty && $v.tickets.$each.$response.$data[currentMedia].description.$error" 
                              class="text-red-500 mr-auto">
                            Description is too long
                        </span>
                    </div>
                </div>
                <p v-else class="cursor-pointer underline" @click="toggleAdditionalDetails">Additional ticket details</p>
            </div>
            <div class="grid grid-cols-3 gap-4 mt-28">
                <div 
                    v-for="(ticket, index) in tickets" 
                    :key="index" 
                    @mouseenter="hoveredLocation = index"
                    @mouseleave="hoveredLocation = null"
                    @click="handleDivClick(index)"
                    :class="{
                        'border-[#e5e7eb]': !ticket.ticket_price && currentMedia !== index,
                        'border-black text-black border-2 bg-gray-100': currentMedia === index,
                    }"
                    class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-colors duration-200 hover:border-black hover:border-2"
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
                        <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap text-black">
                            {{ selectedCurrency }}{{ formatPrice(ticket.ticket_price) }}
                        </h4>
                    </div>
                </div>
                <div 
                    @click="addTicket"
                    class="relative h-48 flex flex-col items-center justify-center p-4 border border-dashed border-[#e5e7eb] text-gray-400 rounded-2xl transition-colors duration-200 cursor-pointer hover:bg-gray-100"
                >
                    <span class="text-2xl font-bold">+</span>
                    <span class="text-lg">Add Ticket Type</span>
                </div>
            </div>
        </div>
        <div class="w-full flex justify-end">
            <button class="mt-8 px-12 py-4 text-2xl bg-black text-white rounded-2xl" @click="handleSubmit">Next</button>
        </div>
    </main>
</template>

<script setup>
import { ref, reactive, inject, nextTick, watch, onMounted } from 'vue';
import useVuelidate from '@vuelidate/core';
import { required, maxLength, helpers, minValue, maxValue } from '@vuelidate/validators';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";

// Inject dependencies provided by the parent
const event = inject('event');
const onSubmit = inject('onSubmit');
const setStep = inject('setStep');
const errors = inject('errors');

const props = defineProps({
    tickets: {
        type: Array,
        default: () => ([])
    },
});

// Initialize tickets from event if they exist, otherwise use default
const tickets = reactive(event?.tickets?.length ? event.tickets : [{ name: 'General', ticket_price: 10, description: '', currency: '$' }]);

const currencySymbols = ['$', '€', '£', '¥', 'C$'];
const selectedCurrency = ref(tickets.length > 0 ? tickets[0].currency : '$');

const uniqueTicketName = helpers.withMessage('Ticket name must be unique', value => {
    const names = tickets.map(ticket => ticket.name);
    return names.indexOf(value) === names.lastIndexOf(value);
});

const ticketNameNotFreeWithNonZeroPrice = helpers.withMessage('Ticket name cannot be "Free" if the price is not zero', (value, siblings) => {
    return !(value.toLowerCase() === 'free' && siblings.ticket_price !== 0);
});

// Constants
const MAX_TICKET_PRICE = 9999.99;
const MAX_DESCRIPTION_LENGTH = 200;

// Setup Vuelidate for form validation
const rules = {
    tickets: {
        $each: helpers.forEach({
            name: { 
                required, 
                maxLength: maxLength(40), 
                uniqueTicketName, 
                ticketNameNotFreeWithNonZeroPrice 
            },
            ticket_price: { 
                required,
                minValue: minValue(0),
                maxValue: maxValue(MAX_TICKET_PRICE)
            },
            description: { 
                maxLength: maxLength(MAX_DESCRIPTION_LENGTH) 
            }
        })
    }
};
const $v = useVuelidate(rules, { tickets });

const inputRefs = ref({});
const currentMedia = ref(0);
const formattedPrice = ref(parseFloat(tickets[currentMedia.value]?.ticket_price).toFixed(2) || '0.00');
const formattedName = ref(tickets[currentMedia.value]?.name || '');
const formattedDescription = ref(tickets[currentMedia.value]?.description || '');
const hoveredLocation = ref(null);
const showAdditionalDetails = ref(false);
const showCurrencyDropdown = ref(false);

onMounted(() => {
    if (tickets.length > 0) {
        formattedPrice.value = parseFloat(tickets[currentMedia.value].ticket_price).toFixed(2);
        formattedName.value = tickets[currentMedia.value].name;
        formattedDescription.value = tickets[currentMedia.value].description;
        showAdditionalDetails.value = Boolean(tickets[currentMedia.value].description);
    }
});

watch(currentMedia, (newIndex) => {
    if (tickets[newIndex]) {
        formattedPrice.value = parseFloat(tickets[newIndex].ticket_price).toFixed(2);
        formattedName.value = tickets[newIndex].name;
        formattedDescription.value = tickets[newIndex].description;
        showAdditionalDetails.value = Boolean(tickets[newIndex].description);
    }
});

const handleSubmit = async () => {
    $v.value.$touch();
    if ($v.value.tickets.$invalid) {
        return;
    }

    // Format tickets with all required fields
    const formattedTickets = tickets.map(ticket => ({
        name: ticket.name,
        ticket_price: parseFloat(ticket.ticket_price),  // Ensure it's a number
        description: ticket.description || '',
        currency: ticket.currency || selectedCurrency.value  // Ensure currency is always set
    }));

    await onSubmit({ tickets: formattedTickets });
    setStep('NextStep');
};

const handleDivClick = (index) => {
    currentMedia.value = index;
    formattedPrice.value = parseFloat(tickets[index].ticket_price).toFixed(2);
    formattedName.value = tickets[index].name;
    formattedDescription.value = tickets[index].description;
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
        value = value.substring(0, decimalIndex + 1) + 
               value.substring(decimalIndex + 1).replace(/\./g, '').substring(0, 2);
    }

    // Limit the whole number part
    const parts = value.split('.');
    if (parts[0].length > 4) { // Updated to 4 digits for 9999.99 max
        parts[0] = parts[0].substring(0, 4);
        value = parts.join('.');
    }

    // Check if value exceeds maximum
    if (parseFloat(value) > MAX_TICKET_PRICE) {
        value = MAX_TICKET_PRICE.toFixed(2);
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
    if (tickets.length < 5) {
        tickets.push({ name: `Ticket Type`, ticket_price: 0, description: '', currency: selectedCurrency.value });
        handleDivClick(tickets.length - 1); // Automatically select the new ticket
    } else {
        alert('You cannot add more than 5 ticket types.');
    }
};

const removeTicket = (index) => {
    if (tickets.length > 1) {
        tickets.splice(index, 1);
        if (currentMedia.value >= index) {
            currentMedia.value = Math.max(0, currentMedia.value - 1);
        }
    }
};

const selectPriceInput = (e) => {
    setTimeout(() => {
        e.target.select();
    }, 0);
};

const toggleAdditionalDetails = () => {
    showAdditionalDetails.value = !showAdditionalDetails.value;
    if (!showAdditionalDetails.value) {
        formattedDescription.value = '';
        tickets[currentMedia.value].description = '';
    }
};

const updateAdditionalDetails = (e) => {
    let value = e.target.value;
    if (value.length > MAX_DESCRIPTION_LENGTH) {
        value = value.substring(0, MAX_DESCRIPTION_LENGTH);
    }
    formattedDescription.value = value;
    tickets[currentMedia.value].description = value;
};

const toggleCurrencyDropdown = () => {
    showCurrencyDropdown.value = !showCurrencyDropdown.value;
};

const selectCurrency = (currency) => {
    selectedCurrency.value = currency;
    tickets.forEach(ticket => ticket.currency = currency);
    showCurrencyDropdown.value = false;
};

// Method to format price
const formatPrice = (price) => {
    return parseFloat(price).toFixed(2);
};
</script>
