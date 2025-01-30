<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <h2 class="text-black">Tickets</h2>
            <!-- Price Section -->
            <div class="mt-12">
                <div class="flex items-center">
                    <span 
                        class="text-[6rem] md:text-[11rem] font-bold text-heavy leading-tight cursor-pointer"
                        @click="toggleCurrencyDropdown"
                    >{{ state.selectedCurrency }}</span>
                    <input
                        v-if="state.currentMedia !== null && tickets[state.currentMedia]"
                        type="text"
                        name="price"
                        class="text-[6rem] md:text-[11rem] font-bold text-heavy w-full overflow-hidden leading-tight ml-4"
                        :class="{ 
                            'border-red-500 focus:shadow-focus-error': $v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.ticket_price.$error
                        }"
                        v-model="state.formattedPrice"
                        @input="updateTicketPrice"
                        @focus="selectPriceInput"
                    />
                </div>
                <div v-if="$v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.ticket_price.$error" 
                     class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                    <span v-if="$v.tickets.$each.$response.$errors[state.currentMedia].ticket_price.$minValue">
                        Price cannot be negative.
                    </span>
                    <span v-else-if="$v.tickets.$each.$response.$errors[state.currentMedia].ticket_price.$maxValue">
                        Price cannot exceed ${{ MAX_TICKET_PRICE.toLocaleString() }}.
                    </span>
                    <span v-else>
                        Please enter a valid price.
                    </span>
                </div>
                <div v-if="state.showCurrencyDropdown" class="absolute mt-2 border border-neutral-300 rounded-lg bg-white shadow-lg z-50">
                    <ul class="flex flex-col m-0">
                        <li 
                            v-for="currency in CURRENCY_SYMBOLS" 
                            :key="currency" 
                            @click="selectCurrency(currency)"
                            class="w-full p-4 cursor-pointer hover:bg-neutral-50 text-center"
                        >{{ currency }}</li>
                    </ul>
                </div>
            </div>

            <!-- Ticket Name Input -->
            <div class="mt-8">
                <input 
                    class="w-2/3 border border-neutral-300 text-[#222222] text-3xl p-4 rounded-2xl relative transition-all duration-200"
                    :class="{ 
                        'border-red-500 focus:shadow-focus-error': $v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.name.$error,
                        'hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !($v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.name.$error)
                    }"
                    type="text" 
                    v-model="state.formattedName" 
                    @input="updateTicketName"
                    maxlength="40"
                    placeholder="Ticket Name"
                    name="Name"
                >
                <div class="flex justify-end mt-1 w-2/3" 
                     :class="{'text-red-500': isNameNearLimit, 'text-neutral-500': !isNameNearLimit}">
                    {{ state.formattedName?.length || 0 }}/40
                </div>
                <div v-if="$v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.name.$error" 
                     class="text-red-500 text-1xl mt-2 px-4">
                    <span v-for="error in $v.tickets.$each.$response.$errors[state.currentMedia].name" :key="error.$validator">
                        {{
                            error.$validator === 'required' ? 'Ticket name is required.' :
                            error.$validator === 'maxLength' ? 'Too many characters.' :
                            error.$validator === 'uniqueTicketName' ? 'Ticket name must be unique.' :
                            error.$validator === 'ticketNameNotFreeWithNonZeroPrice' ? 'Free ticket must be free.' : ''
                        }}
                    </span>
                </div>
            </div>

            <!-- Additional Details Input -->
            <div class="mt-8">
                <input 
                    class="w-full border border-neutral-300 text-[#222222] text-3xl p-4 rounded-2xl relative transition-all duration-200"
                    :class="{ 
                        'border-red-500 focus:shadow-focus-error': $v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.description.$error,
                        'hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !($v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.description.$error)
                    }"
                    type="text" 
                    v-model="state.formattedDescription" 
                    @input="updateAdditionalDetails"
                    name="AdditionalDetails"
                    placeholder="Additional ticket details (optional)"
                    maxlength="60"
                >
                <div class="flex justify-end mt-1" 
                     :class="{'text-red-500': isDescriptionNearLimit, 'text-neutral-500': !isDescriptionNearLimit}">
                    {{ state.formattedDescription?.length || 0 }}/60
                </div>
                <div v-if="$v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.description.$error" 
                     class="text-red-500 text-1xl mt-2 px-4">
                    Too many characters.
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-24">
                <div 
                    v-for="(ticket, index) in tickets" 
                    :key="index" 
                    @mouseenter="state.hoveredLocation = index"
                    @mouseleave="state.hoveredLocation = null"
                    @click="handleDivClick(index)"
                    :class="{
                        'border-neutral-300': !ticket.ticket_price && state.currentMedia !== index,
                        'border-[#222222] shadow-focus-black bg-neutral-50': state.currentMedia === index,
                    }"
                    class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-all duration-200 hover:border-[#222222] hover:shadow-focus-black"
                >
                    <div 
                        v-if="tickets.length > 1"
                        @click.stop="removeTicket(index)" 
                        class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white p-[0.1rem] rounded-full"
                    >
                        <component :is="state.hoveredLocation === index ? RiCloseCircleFill : RiCloseCircleLine" />
                    </div>
                    <div class="flex items-start justify-start w-full h-16 rounded-2xl">
                        <span class="overflow-hidden w-full break-words hyphens-auto">
                            {{ ticket.name }}
                        </span>
                    </div>
                    <div class="overflow-hidden w-full">
                        <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap text-black">
                            {{ state.selectedCurrency }}{{ formatPrice(ticket.ticket_price) }}
                        </h4>
                    </div>
                </div>
                <div 
                    v-if="tickets.length < 5"
                    @click="addTicket"
                    class="relative h-48 flex flex-col items-center justify-center p-4 border border-dashed border-neutral-300 text-neutral-400 rounded-2xl transition-all duration-200 cursor-pointer hover:border-[#222222] hover:bg-neutral-50"
                >
                    <span class="text-2xl font-bold">+</span>
                    <span class="text-lg">Add Ticket Type</span>
                </div>
            </div>
            <div class="mt-12" ref="ticketUrlSection">
                <h3 class="text-2xl mb-4">Ticket URL <span class="text-red-500">*</span></h3>
                <input 
                    class="w-full border border-neutral-300 text-[#222222] text-xl p-4 rounded-2xl relative transition-all duration-200"
                    :class="{ 
                        'border-red-500 focus:shadow-focus-error': hasUrlError,
                        'hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !hasUrlError
                    }"
                    type="url" 
                    v-model="state.ticketUrl" 
                    placeholder="https://..."
                    @input="validateTicketUrl"
                    maxlength="255"
                >
                <div v-if="state.ticketUrlError || (!state.ticketUrl && $v.$dirty)" 
                     class="text-red-500 text-1xl mt-2 px-4">
                    {{ !state.ticketUrl ? 'Ticket URL is required' : 'Please enter a valid URL (e.g., https://example.com)' }}
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
// 1. Imports
import { ref, reactive, inject, watch, onMounted, computed } from 'vue';
import useVuelidate from '@vuelidate/core';
import { required, maxLength, helpers, minValue, maxValue } from '@vuelidate/validators';
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";

// 2. Constants
const MAX_TICKET_PRICE = 9999.99;
const MAX_DESCRIPTION_LENGTH = 60;
const CURRENCY_SYMBOLS = ['$', '€', '£', '¥', 'C$'];
const MAX_URL_LENGTH = 255;

// 3. Props & Injections
const event = inject('event');

// 4. State Management
const state = ref({
    currentMedia: 0,
    hoveredLocation: null,
    showAdditionalDetails: false,
    showCurrencyDropdown: false,
    selectedCurrency: '$',
    formattedPrice: '0.00',
    formattedName: '',
    formattedDescription: '',
    ticketUrl: '',
    ticketUrlError: false,
});

// Get tickets from the first show
const show = event?.shows?.[0];
const tickets = reactive(show?.tickets?.length 
    ? show.tickets.map(ticket => ({
        name: ticket.name || 'General',
        ticket_price: parseFloat(ticket.ticket_price) || 0, // Parse the price to float
        description: ticket.description || '',
        currency: ticket.currency || state.value.selectedCurrency
    }))
    : [{
        name: 'General',
        ticket_price: 0,
        description: '',
        currency: state.value.selectedCurrency
    }]
);

// Update ticketUrl initialization
state.value.ticketUrl = show?.ticket_url || '';

// Update initial state values after tickets are initialized
if (tickets.length > 0) {
    state.value.formattedPrice = parseFloat(tickets[0].ticket_price).toFixed(2);
    state.value.formattedName = tickets[0].name;
    state.value.formattedDescription = tickets[0].description;
    state.value.selectedCurrency = tickets[0].currency;
    state.value.ticketUrl = event?.ticketUrl || '';
}

// 5. Validation Rules
const uniqueTicketName = helpers.withMessage(
    'Ticket name must be unique', 
    value => {
        const names = tickets.map(ticket => ticket.name);
        return names.indexOf(value) === names.lastIndexOf(value);
    }
);

const ticketNameNotFreeWithNonZeroPrice = helpers.withMessage(
    'Ticket name cannot be "Free" if the price is not zero',
    (value, siblings) => !(value.toLowerCase() === 'free' && siblings.ticket_price !== 0)
);

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
    },
    ticketUrl: { 
        required,
        maxLength: maxLength(MAX_URL_LENGTH),
        url: helpers.withMessage(
            'Please enter a valid URL (e.g., https://example.com)',
            (value) => {
                if (!value) return true;
                try {
                    new URL(value);
                    return true;
                } catch {
                    return false;
                }
            }
        )
    }
};

const $v = useVuelidate(rules, { 
    tickets,
    ticketUrl: computed(() => state.value.ticketUrl)
});

// 6. Methods - Ticket Management
const handleDivClick = (index) => {
    state.value.currentMedia = index;
    state.value.formattedPrice = parseFloat(tickets[index].ticket_price).toFixed(2);
    state.value.formattedName = tickets[index].name;
    state.value.formattedDescription = tickets[index].description;
};

const addTicket = () => {
    if (tickets.length < 5) {
        tickets.push({ 
            name: `Ticket Name`, 
            ticket_price: 0, 
            description: '', 
            currency: state.value.selectedCurrency 
        });
        handleDivClick(tickets.length - 1);
    }
};

const removeTicket = (index) => {
    if (tickets.length > 1) {
        tickets.splice(index, 1);
        if (state.value.currentMedia >= index) {
            state.value.currentMedia = Math.max(0, state.value.currentMedia - 1);
        }
    }
};

// 7. Methods - Input Handling
const updateTicketPrice = (e) => {
    let value = e.target.value.replace(/[^\d.]/g, '');

    const decimalIndex = value.indexOf('.');
    if (decimalIndex !== -1) {
        value = value.substring(0, decimalIndex + 1) + 
               value.substring(decimalIndex + 1).replace(/\./g, '').substring(0, 2);
    }

    const parts = value.split('.');
    if (parts[0].length > 4) {
        parts[0] = parts[0].substring(0, 4);
        value = parts.join('.');
    }

    if (parseFloat(value) > MAX_TICKET_PRICE) {
        value = MAX_TICKET_PRICE.toFixed(2);
    }

    state.value.formattedPrice = value;
    tickets[state.value.currentMedia].ticket_price = value ? parseFloat(value) : 0;
};

// 8. Methods - Form Updates
const updateTicketName = (e) => {
    let value = e.target.value;
    if (value.length > 40) {
        value = value.slice(0, 40);
    }
    state.value.formattedName = value;
    tickets[state.value.currentMedia].name = value;
};

const updateAdditionalDetails = (e) => {
    let value = e.target.value;
    if (value.length > MAX_DESCRIPTION_LENGTH) {
        value = value.slice(0, MAX_DESCRIPTION_LENGTH);
    }
    state.value.formattedDescription = value;
    tickets[state.value.currentMedia].description = value;
};

const validateTicketUrl = () => {
    if (state.value.ticketUrl?.length > MAX_URL_LENGTH) {
        state.value.ticketUrl = state.value.ticketUrl.slice(0, MAX_URL_LENGTH);
    }
    $v.value.$touch();
    state.value.ticketUrlError = $v.value.ticketUrl.$error;
};

// 9. Methods - UI Interactions
const selectPriceInput = (e) => {
    setTimeout(() => e.target.select(), 0);
};

const toggleAdditionalDetails = () => {
    state.value.showAdditionalDetails = !state.value.showAdditionalDetails;
    if (!state.value.showAdditionalDetails) {
        state.value.formattedDescription = '';
        tickets[state.value.currentMedia].description = '';
    }
};

const toggleCurrencyDropdown = () => {
    state.value.showCurrencyDropdown = !state.value.showCurrencyDropdown;
};

const selectCurrency = (currency) => {
    state.value.selectedCurrency = currency;
    tickets.forEach(ticket => ticket.currency = currency);
    state.value.showCurrencyDropdown = false;
};

// 10. Helper Methods
const formatPrice = (price) => {
    return parseFloat(price).toFixed(2);
};

// 11. Component API
defineExpose({
    isValid: async () => {
        $v.value.$touch();
        
        if (!state.value.ticketUrl) {
            state.value.ticketUrlError = true;
        }

        const isValid = !$v.value.tickets.$invalid && 
                       state.value.ticketUrl && 
                       !state.value.ticketUrlError;
        
        // If not valid and there's a ticket URL error, scroll to the section
        if (!isValid && (state.value.ticketUrlError || !state.value.ticketUrl)) {
            ticketUrlSection.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
                       
        console.log('Tickets validation:', {
            ticketsCount: tickets.length,
            validationErrors: $v.value.tickets.$errors,
            ticketUrlValid: Boolean(state.value.ticketUrl) && !state.value.ticketUrlError,
            isValid
        });
        return isValid;
    },
    submitData: () => {
        const formattedTickets = tickets.map(ticket => ({
            name: ticket.name,
            ticket_price: parseFloat(ticket.ticket_price), // Keep as ticket_price
            description: ticket.description || '',
            currency: ticket.currency || state.value.selectedCurrency
        }));

        return {
            tickets: formattedTickets,
            ticketUrl: state.value.ticketUrl || null
        };
    }
});

// 12. Watchers
watch(() => state.value.currentMedia, (newIndex) => {
    if (tickets[newIndex]) {
        state.value.formattedPrice = parseFloat(tickets[newIndex].ticket_price).toFixed(2);
        state.value.formattedName = tickets[newIndex].name;
        state.value.formattedDescription = tickets[newIndex].description;
        state.value.showAdditionalDetails = Boolean(tickets[newIndex].description);
    }
});

// 13. Lifecycle Hooks
onMounted(() => {
    if (tickets.length > 0) {
        state.value.formattedPrice = parseFloat(tickets[state.value.currentMedia].ticket_price).toFixed(2);
        state.value.formattedName = tickets[state.value.currentMedia].name;
        state.value.formattedDescription = tickets[state.value.currentMedia].description;
        state.value.showAdditionalDetails = Boolean(tickets[state.value.currentMedia].description);
    }
});

// Add this computed property:
const isNameNearLimit = computed(() => {
    const count = state.value.formattedName?.length || 0;
    return count > 35;
});

// Add computed property for description limit
const isDescriptionNearLimit = computed(() => {
    const count = state.value.formattedDescription?.length || 0;
    return count > 55; // Show warning when near limit
});

// Add this computed property
const hasUrlError = computed(() => 
    state.value.ticketUrlError || 
    (!state.value.ticketUrl && $v.value.$dirty) || 
    (state.value.ticketUrl?.length >= MAX_URL_LENGTH)
);

const ticketUrlSection = ref(null);
</script>
<style>
/* Your existing styles can remain unchanged */
</style>

