<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <h2 class="text-black">Tickets</h2>
            <!-- Price Section -->
            <div class="mt-12">
                <div class="flex items-center">
                    <!-- Currency symbol - hide for Free and PWYC tickets -->
                    <span 
                        v-if="!isPWYCTicket && !isFreeTicket"
                        class="text-[6rem] md:text-[9.5rem] font-bold text-heavy leading-tight cursor-pointer hover:wiggle"
                        @click="toggleCurrencyDropdown"
                    >{{ state.selectedCurrency }}</span>
                    
                    <!-- Show PWYC text when PWYC ticket type is selected -->
                    <span 
                        v-if="isPWYCTicket"
                        class="text-[6rem] md:text-[9.5rem] font-bold text-heavy w-full overflow-hidden leading-tight"
                        :class="{'ml-4': !isPWYCTicket && !isFreeTicket}"
                    >PWYC</span>
                    
                    <!-- Show Free text when Free ticket type is selected -->
                    <span 
                        v-else-if="isFreeTicket"
                        class="text-[6rem] md:text-[9.5rem] font-bold text-heavy w-full overflow-hidden leading-tight"
                        :class="{'ml-4': !isPWYCTicket && !isFreeTicket}"
                    >Free</span>
                    
                    <!-- Regular price input for standard ticket types -->
                    <input
                        v-else-if="state.currentMedia !== null && tickets[state.currentMedia]"
                        type="text"
                        name="price"
                        class="text-[6rem] md:text-[9.5rem] font-bold text-heavy w-full overflow-hidden leading-tight placeholder-neutral-300 placeholder-opacity-50"
                        :class="{'ml-4': !isPWYCTicket && !isFreeTicket}"
                        v-model="state.formattedPrice"
                        @input="updateTicketPrice"
                        @focus="selectPriceInput"
                        placeholder="0.00"
                        ref="priceInput"
                        autofocus
                    />
                </div>
                <div v-if="$v.tickets.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.ticket_price.$error" 
                     class="text-red-500 text-1xl ml-20 mb-8 px-4">
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
            <div>
                <div class="mt-8">
                    <!-- Display selected ticket type as a list -->
                    <List 
                        v-if="currentTicketItem.length > 0"
                        class="mt-6"
                        :item-height="'h-24'"
                        :selections="currentTicketItem" 
                        @onSelect="replaceTicketType"
                    />

                    <Dropdown 
                        v-else
                        id="ticketName"
                        class="w-2/3"
                        :list="state.availableTicketOptions"
                        :creatable="true"
                        :loading="state.isLoadingOptions"
                        placeholder="Select ticket type"
                        @onSelect="updateTicketName"
                        :error="$v.tickets.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.name.$error ? 'Please select a valid ticket type' : null"
                        :max-selections="1"
                        :max-input-length="40"
                        :reset-on-select="true"
                    />
                    
                    
                    <div v-if="$v.tickets.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.name.$error" 
                        class="text-red-500 text-1xl mt-2 px-4">
                        <span v-for="error in $v.tickets.$each.$response.$errors[state.currentMedia].name" :key="error.$validator">
                            {{
                                error.$validator === 'required' ? 'Ticket type is required.' :
                                error.$validator === 'maxLength' ? 'Too many characters.' :
                                error.$validator === 'uniqueTicketName' ? 'Ticket type must be unique.' :
                                error.$validator === 'ticketNameNotFreeWithNonZeroPrice' ? 'Free ticket must be free.' : ''
                            }}
                        </span>
                    </div>
                </div>

                <div v-if="tickets[0].name && (tickets[0].ticket_price !== undefined && tickets[0].ticket_price !== null)">

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
                        <div 
                            v-if="isDescriptionNearLimit"
                            class="flex justify-end mt-1" 
                            :class="{'text-red-500': isDescriptionNearLimit, 'text-neutral-500': !isDescriptionNearLimit}">
                            {{ state.formattedDescription?.length || 0 }}/60
                        </div>
                        <div v-if="$v.$anyDirty && tickets[state.currentMedia] && $v.tickets.$each.$response.$data[state.currentMedia]?.description.$error" 
                            class="text-red-500 text-1xl mt-2 px-4">
                            Too many characters.
                        </div>
                    </div>

                    

                    <div class="grid grid-cols-3 gap-4 mt-12">
                        <div 
                            v-for="(ticket, index) in tickets" 
                            :key="index" 
                            @mouseenter="state.hoveredLocation = index"
                            @mouseleave="state.hoveredLocation = null"
                            @click="handleDivClick(index)"
                            :class="{
                                'border-neutral-300': !ticket.ticket_price && state.currentMedia !== index && !hasTicketError(index),
                                'border-[#222222] shadow-focus-black bg-neutral-50': state.currentMedia === index && !hasTicketError(index),
                                'border-red-500 shadow-focus-error': hasTicketError(index)
                            }"
                            class="relative h-48 flex flex-col items-start justify-between p-4 border rounded-2xl transition-all duration-200 hover:border-[#222222] hover:shadow-focus-black"
                        >
                            <div 
                                v-if="tickets.length > 1 && state.hoveredLocation === index"
                                @click.stop="removeTicket(index)" 
                                class="absolute top-[-1rem] right-[-1rem] bg-white p-[0.1rem] rounded-full opacity-0 transition-opacity duration-200 hover:opacity-100 z-10 ticket-delete-btn"
                                :class="{ 'opacity-100': state.hoveredLocation === index }"
                            >
                                <component 
                                    :is="RiCloseCircleFill" 
                                    class="text-red-500 hover:text-red-600 transition-colors ticket-delete-icon" 
                                />
                            </div>
                            <div class="flex items-start justify-start w-full h-16 rounded-2xl">
                                <span class="overflow-hidden w-full break-words hyphens-auto">
                                    {{ ticket.name }}
                                </span>
                            </div>
                            <div class="overflow-hidden w-full">
                                <h4 class="text-lg h-10 leading-tight overflow-hidden text-ellipsis whitespace-nowrap text-black">
                                    <!-- Show "PWYC" for PWYC tickets, otherwise show currency + price -->
                                    <template v-if="ticket.name && ticket.name.toLowerCase() === 'pwyc'">
                                        PWYC
                                    </template>
                                    <template v-else>
                                        {{ state.selectedCurrency }}{{ formatPrice(ticket.ticket_price) }}
                                    </template>
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
                        <input 
                            class="w-full border border-neutral-300 text-[#222222] text-3xl p-4 rounded-2xl relative transition-all duration-200"
                            :class="{ 
                                'border-red-500 focus:shadow-focus-error': hasUrlError,
                                'hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !hasUrlError
                            }"
                            type="url" 
                            v-model="state.ticketUrl" 
                            placeholder="Ticket Url"
                            @input="validateTicketUrl"
                            maxlength="255"
                        >
                        <div v-if="state.ticketUrlError || (!state.ticketUrl && $v.$dirty)" 
                            class="text-red-500 text-1xl mt-2 px-4">
                            {{ !state.ticketUrl ? 'Ticket URL is required' : 'Please enter a valid URL (e.g., https://example.com)' }}
                        </div>
                    </div>
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
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';

// 2. Constants
const MAX_TICKET_PRICE = 9999.99;
const MAX_DESCRIPTION_LENGTH = 60;
const CURRENCY_SYMBOLS = ['$', '€', '£', '¥', 'C$'];
const MAX_URL_LENGTH = 255;
const TICKET_NAME_OPTIONS = [
  { id: 'general', name: 'General' },
  { id: 'student', name: 'Student' },
  { id: 'senior', name: 'Senior' },
  { id: 'vip', name: 'VIP' },
  { id: 'free', name: 'Free' },
  { id: 'pwyc', name: 'PWYC' }
];

// 3. Props & Injections
const event = inject('event');

// 4. State Management
const state = ref({
    currentMedia: 0,
    hoveredLocation: null,
    showAdditionalDetails: false,
    showCurrencyDropdown: false,
    selectedCurrency: '$',
    formattedPrice: '',
    formattedName: '',
    formattedDescription: '',
    ticketUrl: '',
    ticketUrlError: false,
    availableTicketOptions: [],
    isLoadingOptions: false,
});

// Get tickets from the first show
const show = event?.shows?.[0];
const tickets = reactive(show?.tickets?.length 
    ? show.tickets.map(ticket => ({
        name: ticket.name || '',
        ticket_price: ticket.ticket_price !== undefined ? parseFloat(ticket.ticket_price) : '',
        description: ticket.description || '',
        currency: ticket.currency || state.value.selectedCurrency
    }))
    : [{
        name: '',
        ticket_price: '',
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
    
    // Format price if it exists, otherwise keep it empty
    if (tickets[index].ticket_price !== '') {
        state.value.formattedPrice = parseFloat(tickets[index].ticket_price).toFixed(2);
    } else {
        state.value.formattedPrice = '';
    }
    
    state.value.formattedName = tickets[index].name;
    state.value.formattedDescription = tickets[index].description;
    
    // Update available ticket options
    updateAvailableTicketOptions();
    
    // Focus the price input safely
    setTimeout(() => {
        try {
            if (priceInput && priceInput.value) {
                priceInput.value.focus();
            } else {
                // Fallback to finding the input by name
                const pInput = document.querySelector('input[name="price"]');
                if (pInput) pInput.focus();
            }
        } catch (e) {
            console.log('Could not focus price input', e);
        }
    }, 100);
};

const addTicket = () => {
    if (tickets.length < 5) {
        tickets.push({ 
            name: '', 
            ticket_price: '', 
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

    // If it's empty, keep it empty
    if (value === '') {
        state.value.formattedPrice = '';
        tickets[state.value.currentMedia].ticket_price = '';
        return;
    }

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
    tickets[state.value.currentMedia].ticket_price = value ? parseFloat(value) : '';
};

// 8. Methods - Form Updates
const updateTicketName = (item) => {
    // Update the ticket name with the selected option
    state.value.formattedName = item.name;
    tickets[state.value.currentMedia].name = item.name;
    
    // Update available options after selection
    updateAvailableTicketOptions();
    
    // Set price to 0 for Free tickets, auto-fill 0.01 for PWYC (as a minimum)
    if (item.name.toLowerCase() === 'free') {
        state.value.formattedPrice = '0.00';
        tickets[state.value.currentMedia].ticket_price = 0;
        
        // Focus the description input safely
        setTimeout(() => {
            try {
                const descriptionInput = document.querySelector('input[name="AdditionalDetails"]');
                if (descriptionInput) descriptionInput.focus();
            } catch (e) {
                console.log('Could not focus description input', e);
            }
        }, 100);
    } else if (item.name.toLowerCase() === 'pwyc') {
        // For PWYC tickets, set a minimal price
        state.value.formattedPrice = '0.01';
        tickets[state.value.currentMedia].ticket_price = 0.01;
        
        // Focus the description input since price can't be edited for PWYC
        setTimeout(() => {
            try {
                const descriptionInput = document.querySelector('input[name="AdditionalDetails"]');
                if (descriptionInput) descriptionInput.focus();
            } catch (e) {
                console.log('Could not focus description input', e);
            }
        }, 100);
    } else {
        // For other types, focus price field safely
        setTimeout(() => {
            try {
                if (priceInput && priceInput.value) {
                    priceInput.value.focus();
                } else {
                    // Fallback to finding the input by name
                    const pInput = document.querySelector('input[name="price"]');
                    if (pInput) pInput.focus();
                }
            } catch (e) {
                console.log('Could not focus price input', e);
            }
        }, 100);
    }
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
    
    // Ensure URL starts with https://
    if (state.value.ticketUrl && !state.value.ticketUrl.match(/^https:\/\//i)) {
        // If URL doesn't start with http:// or https://, prepend https://
        if (!state.value.ticketUrl.match(/^http:\/\//i)) {
            state.value.ticketUrl = 'https://' + state.value.ticketUrl.replace(/^[\/\\]+/, '');
        }
    }
    
    // Only touch the ticketUrl field, not the entire validation object
    $v.value.ticketUrl.$touch();
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
    if (price === '' || price === null || price === undefined) {
        return '';
    }
    return parseFloat(price).toFixed(2);
};

// 11. Component API
defineExpose({
    isValid: async () => {
        // This is where we want to check validation - only when the user tries to proceed
        $v.value.$touch(); // Mark all fields as touched to trigger validation
        
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
            ticket_price: ticket.ticket_price === '' ? 0 : parseFloat(ticket.ticket_price), // Convert empty to 0 for submission
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
        if (tickets[newIndex].ticket_price !== '') {
            state.value.formattedPrice = parseFloat(tickets[newIndex].ticket_price).toFixed(2);
        } else {
            state.value.formattedPrice = '';
        }
        state.value.formattedName = tickets[newIndex].name;
        state.value.formattedDescription = tickets[newIndex].description;
        state.value.showAdditionalDetails = Boolean(tickets[newIndex].description);
        
        updateAvailableTicketOptions();
    }
});

// 13. Lifecycle Hooks
onMounted(() => {
    if (tickets.length > 0) {
        // For existing tickets with price data, format it. Otherwise, leave it empty
        if (tickets[state.value.currentMedia].ticket_price !== '') {
            state.value.formattedPrice = parseFloat(tickets[state.value.currentMedia].ticket_price).toFixed(2);
        } else {
            state.value.formattedPrice = '';
        }
        
        state.value.formattedName = tickets[state.value.currentMedia].name;
        state.value.formattedDescription = tickets[state.value.currentMedia].description;
        state.value.showAdditionalDetails = Boolean(tickets[state.value.currentMedia].description);
        
        // Initialize available ticket options
        updateAvailableTicketOptions();
        
        // Focus the price input with a small delay to ensure rendering
        setTimeout(() => {
            try {
                if (priceInput && priceInput.value) {
                    priceInput.value.focus();
                } else {
                    // Fallback to finding the input by name
                    const pInput = document.querySelector('input[name="price"]');
                    if (pInput) pInput.focus();
                }
            } catch (e) {
                console.log('Could not focus price input', e);
            }
        }, 100);
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
const priceInput = ref(null);

// Update the updateAvailableTicketOptions method to optionally include current ticket
const updateAvailableTicketOptions = (includeCurrentOption = false) => {
    // Get all currently used ticket names (except current ticket)
    const usedNames = tickets
        .filter((_, index) => includeCurrentOption ? index !== state.value.currentMedia : true)
        .map(ticket => ticket.name.toLowerCase());
    
    // Filter out used options
    state.value.availableTicketOptions = TICKET_NAME_OPTIONS.filter(
        option => !usedNames.includes(option.name.toLowerCase())
    );
    
    // If we're including the current option but it's not in the predefined options,
    // add it as a custom option
    if (includeCurrentOption && tickets[state.value.currentMedia]) {
        const currentName = tickets[state.value.currentMedia].name;
        const currentNameLower = currentName.toLowerCase();
        
        // Check if the current name is not already in the available options
        const exists = state.value.availableTicketOptions.some(
            option => option.name.toLowerCase() === currentNameLower
        );
        
        if (!exists && currentName) {
            state.value.availableTicketOptions.push({
                id: currentNameLower,
                name: currentName
            });
        }
    }
};

// Add this computed property for current ticket name in proper format for the dropdown
const currentTicketNameForDropdown = computed(() => {
    const currentTicket = tickets[state.value.currentMedia];
    if (!currentTicket || !currentTicket.name) return [];
    
    return [{
        id: currentTicket.name.toLowerCase(),
        name: currentTicket.name
    }];
});

// Add this computed property for the current ticket to display in List
const currentTicketItem = computed(() => {
    const currentTicket = tickets[state.value.currentMedia];
    if (!currentTicket || !currentTicket.name) return [];
    
    return [{
        id: currentTicket.name.toLowerCase(),
        name: currentTicket.name
    }];
});

// Add a method to handle replacing the ticket type when clicking the "x" on the list item
const replaceTicketType = (item) => {
    // Clear the ticket name to make the dropdown visible again
    tickets[state.value.currentMedia].name = '';
    state.value.formattedName = '';
    
    // Update the available options to include previously used options
    updateAvailableTicketOptions(true);
};

// Add this computed property for PWYC detection
const isPWYCTicket = computed(() => {
    return state.value.currentMedia !== null && 
           tickets[state.value.currentMedia] && 
           tickets[state.value.currentMedia].name && 
           tickets[state.value.currentMedia].name.toLowerCase() === 'pwyc';
});

// Add a new computed property for Free tickets
const isFreeTicket = computed(() => {
    return state.value.currentMedia !== null && 
           tickets[state.value.currentMedia] && 
           tickets[state.value.currentMedia].name && 
           tickets[state.value.currentMedia].name.toLowerCase() === 'free';
});

// Add this helper method to validate tickets more accurately
const isValidTicket = (ticket) => {
    // If the ticket has no name, it's not valid
    if (!ticket.name) return false;
    
    // Special handling for Free and PWYC tickets
    const lowerName = ticket.name.toLowerCase();
    if (lowerName === 'free' || lowerName === 'pwyc') return true;
    
    // For regular tickets, check that the price is not undefined/null/empty
    return ticket.ticket_price !== undefined && 
           ticket.ticket_price !== null && 
           ticket.ticket_price !== '';
};

// Add this helper method to check if a ticket has validation errors
const hasTicketError = (index) => {
    // Skip validation if ticket is not dirty
    if (!$v.value.tickets.$dirty) return false;
    
    const ticket = tickets[index];
    
    // Check for price errors (exclude Free and PWYC tickets)
    const isPWYC = ticket.name && ticket.name.toLowerCase() === 'pwyc';
    const isFree = ticket.name && ticket.name.toLowerCase() === 'free';
    
    if (!isPWYC && !isFree) {
        // Validate price is present and valid
        if (ticket.ticket_price === undefined || 
            ticket.ticket_price === null || 
            ticket.ticket_price === '') {
            return true;
        }
        
        // Check numeric constraints
        if (parseFloat(ticket.ticket_price) < 0 || 
            parseFloat(ticket.ticket_price) > MAX_TICKET_PRICE) {
            return true;
        }
    }
    
    // Check name errors
    if (!ticket.name) {
        return true;
    }
    
    return false;
};
</script>
<style>
/* Your existing styles can remain unchanged */

@keyframes wiggle {
  0% { transform: rotate(0deg); }
  25% { transform: rotate(-2deg); }
  50% { transform: rotate(0deg); }
  75% { transform: rotate(2deg); }
  100% { transform: rotate(0deg); }
}

.hover\:wiggle:hover {
  animation: wiggle 0.6s ease-in-out infinite;
  color: #444; /* Darken the text slightly on hover */
  transition: color 0.3s ease;
  position: relative;
}

/* Add a subtle underline animation on hover */
.hover\:wiggle::after {
  content: '';
  position: absolute;
  bottom: 5px;
  left: 0;
  width: 100%;
  height: 3px;
  background-color: #000;
  transform: scaleX(0);
  transition: transform 0.3s ease;
}

.hover\:wiggle:hover::after {
  transform: scaleX(1);
}

/* Force cursor for delete button */
html body .ticket-delete-btn,
html body .ticket-delete-btn *,
html body .ticket-delete-icon,
html body .ticket-delete-icon *,
html body .ticket-delete-btn svg,
html body .ticket-delete-icon svg {
  cursor: pointer !important;
  pointer-events: auto !important;
}

/* Add hover effect for delete button */
.ticket-delete-btn:hover .ticket-delete-icon {
  transform: scale(1.1);
}

.ticket-delete-icon {
  transition: transform 0.2s ease;
}
</style>

