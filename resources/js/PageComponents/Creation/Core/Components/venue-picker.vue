<template>
    <div class="relative">
        <input
            class="w-full focus:outline-none"
            :class="{ 'text-red-500': event.location.venue?.length === 80 }"
            placeholder="Add venue name (optional)"
            :value="event.location.venue"
            maxlength="80"
            type="text"
            data-venue-input
            @input="onInput"
        >

        <!-- Never offered for a secret location -->
        <template v-if="!secret">
            <ul v-if="suggestions.length || canPropose" class="mt-4 space-y-2 list-none p-0" data-venue-suggestions>
                <li v-for="venue in suggestions" :key="venue.id">
                    <button type="button" class="w-full text-left px-4 py-3 rounded-xl border border-gray-300 hover:border-black" @click="pick(venue)">
                        <span class="font-semibold">{{ venue.name }}</span>
                        <span v-if="venue.room" class="text-gray-500">, {{ venue.room }}</span>
                        <span class="block text-gray-500">{{ venue.address }}</span>
                    </button>
                </li>
                <li v-if="canPropose">
                    <button type="button" class="w-full text-left px-4 py-3 rounded-xl border border-dashed border-gray-400" @click="propose">
                        Add "{{ event.location.venue }}" as a new venue at {{ event.location.street }}
                    </button>
                </li>
            </ul>

            <button v-if="known" type="button" class="mt-4 w-full text-left px-4 py-3 rounded-xl border border-gray-300 hover:border-black" data-venue-known @click="pick(known)">
                Is this <span class="font-semibold">{{ known.name }}</span>?
                <span class="block text-gray-500">{{ known.address }}</span>
            </button>

            <p v-if="event.location.venue_id" class="mt-2 text-gray-500" data-venue-linked>Linked to a known venue. Changing the address takes it off.</p>
        </template>
        <p v-if="error" class="text-red-500 mt-2">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed, inject, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';

const emit = defineEmits(['picked']);

const event = inject('event');

const suggestions = ref([]);
const known = ref(null);
const searched = ref('');
const error = ref('');
let timer = null;

const secret = computed(() => Boolean(event.location.hiddenLocationToggle));

// Only once there is an address to give it, and nothing already matches.
const canPropose = computed(() => Boolean(
    !event.location.venue_id
    && searched.value
    && searched.value === event.location.venue
    && event.location.venue.trim().length >= 2
    && event.location.street
    && event.location.city
    && event.location.latitude
    && !suggestions.value.some((venue) => venue.name.toLowerCase() === event.location.venue.trim().toLowerCase())
));

const onInput = (input) => {
    event.location.venue = input.target.value;
    event.location.venue_id = null;
    clearTimeout(timer);
    timer = setTimeout(search, 300);
};

const search = async () => {
    const keywords = event.location.venue?.trim();
    if (secret.value || !keywords || keywords.length < 2) {
        suggestions.value = [];
        searched.value = '';
        return;
    }
    const response = await axios.get('/api/venues/search', { params: { keywords } });
    suggestions.value = response.data;
    searched.value = event.location.venue;
};

const FIELDS = ['home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'latitude', 'longitude'];

const pick = (venue) => {
    FIELDS.forEach((field) => { event.location[field] = venue[field]; });
    event.location.venue = venue.name;
    event.location.venue_id = venue.id;
    suggestions.value = [];
    searched.value = '';
    known.value = null;
    emit('picked', venue);
};

const propose = async () => {
    error.value = '';
    try {
        const response = await axios.post('/api/venues', {
            name: event.location.venue.trim(),
            ...Object.fromEntries(FIELDS.map((field) => [field, event.location[field]])),
        });
        pick(response.data.venue);
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors ?? {}).flat()[0] ?? 'That venue could not be added.';
    }
};

// The other way round: an address EI already knows as a venue.
const matchAddress = async () => {
    known.value = null;
    if (secret.value || event.location.venue_id || !event.location.street || !event.location.city) return;
    const { home, street, city } = event.location;
    const response = await axios.get('/api/venues/match', { params: { home, street, city } });
    known.value = response.data.venue;
};

watch(() => [event.location.street, event.location.city, event.location.home], matchAddress, { immediate: true });

// A location going secret loses its venue on the spot; the server refuses it anyway.
watch(secret, (isSecret) => {
    if (isSecret) {
        event.location.venue_id = null;
        suggestions.value = [];
        known.value = null;
    }
});

onUnmounted(() => clearTimeout(timer));
</script>
