<template>
    <div class="w-full h-full flex flex-col gap-8 pb-8">
        <!-- Event Section -->
        <div 
            @click="showEventSection"
            v-if="isVisible==='organizer'" 
            class="relative flex w-full border rounded-4xl bg-white p-8 justify-between">
            <div>
                <p>Event</p>
            </div>
            <div>
                <p>{{ eventInput || 'Search Events' }}</p>
            </div>
        </div>
        <div v-else class="flex flex-col relative w-full border rounded-4xl bg-white p-12">
            <div class="w-full">
                <h2 class="text-4xl leading-8 font-bold">Search Events</h2>
            </div>
            <div class="w-full flex-grow flex flex-col mt-10">
                <div class="w-full border border-slate-400 rounded-2xl flex items-center">
                    <svg class="w-8 h-8 fill-black z-[1002] ml-8">
                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                    </svg>
                    <input 
                        ref="event"
                        class="relative text-1xl p-8 w-full font-bold z-[1001] bg-transparent focus:border-none placeholder-slate-400"
                        v-model="eventInput"
                        placeholder="Search Events"
                        @input="debounceEventSearch"
                        autocomplete="false"
                        type="text">
                </div>
                
                <!-- Event List -->
                <ul class="bg-white w-full mx-0 overflow-hidden py-8 list-none">
                    <template v-if="isLoading">
                        <li class="py-4 px-8 flex items-center gap-8">
                            <div class="animate-pulse flex items-center gap-8 w-full">
                                <div class="w-20 h-20 bg-gray-200 rounded-2xl"></div>
                                <div class="h-6 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </li>
                    </template>
                    <template v-else>
                        <li 
                            class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100" 
                            v-for="item in filteredEvents"
                            :key="item.model.id"
                            @click="onSelect(item, 'event')">
                            <div class="w-12 h-12 rounded-2xl overflow-hidden flex-shrink-0">
                                <picture class="w-full h-full block">
                                    <source 
                                        type="image/webp" 
                                        :srcset="`${imageUrl}${item.model.thumbImagePath}`"> 
                                    <img 
                                        :src="`${imageUrl}${item.model.thumbImagePath.slice(0, -4)}jpg`"
                                        class="w-full h-full object-cover">
                                </picture>
                            </div>
                            <p class="text-2xl leading-8 font-semibold">
                                {{item.model.name}}
                            </p>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <!-- Organizer Section -->
        <div 
            @click="showOrganizerSection"
            v-if="isVisible==='event'" 
            class="relative flex w-full border rounded-4xl bg-white p-8 justify-between">
            <div>
                <p>Organizer</p>
            </div>
            <div>
                <p>{{ organizerInput || 'Search Organizers' }}</p>
            </div>
        </div>
        <div v-else-if="isVisible==='organizer'" class="flex flex-col relative w-full border rounded-4xl bg-white p-12">
            <div class="w-full">
                <h2 class="text-4xl leading-8 font-bold">Search Organizers</h2>
            </div>
            <div class="w-full flex-grow flex flex-col mt-10 overflow-auto">
                <div class="w-full border border-slate-400 rounded-2xl flex items-center">
                    <svg class="w-8 h-8 fill-black z-[1002] ml-8">
                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                    </svg>
                    <input 
                        ref="organizer"
                        class="relative text-1xl p-8 w-full font-bold z-[1001] bg-transparent focus:border-none placeholder-slate-400"
                        v-model="organizerInput"
                        placeholder="Search Organizers"
                        @input="debounceOrganizerSearch"
                        autocomplete="false"
                        type="text">
                </div>
                
                <!-- Organizer List -->
                <ul class="bg-white w-full mx-0 overflow-y-auto py-8 list-none flex-grow">
                    <li 
                        class="py-4 px-8 flex items-center gap-8 hover:bg-neutral-100" 
                        v-for="item in filteredOrganizers"
                        :key="item.model.id"
                        @click="onSelect(item, 'organizer')">
                        <div class="w-12 h-12 rounded-2xl overflow-hidden flex justify-center items-center">
                            <picture v-if="item.model.thumbImagePath">       
                                <source 
                                    type="image/webp" 
                                    :srcset="`${imageUrl}${item.model.thumbImagePath}`"> 
                                <img :src="`${imageUrl}${item.model.thumbImagePath.slice(0, -4)}jpg`">
                            </picture>
                        </div>
                        <p class="text-xl leading-6">
                            {{item.model.name}}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

const isLoaded = ref(true);
const isLoading = ref(false);
const eventInput = ref('');
const organizerInput = ref('');
const events = ref([]);
const organizers = ref([]);
const isVisible = ref('event');
const imageUrl = import.meta.env.VITE_IMAGE_URL;
let timeout;

const filteredEvents = computed(() => events.value);
const filteredOrganizers = computed(() => organizers.value);

const showEventSection = () => {
    isVisible.value = 'event';
    fetchEvents();
};

const showOrganizerSection = () => {
    isVisible.value = 'organizer';
    fetchOrganizers();
};

const debounceEventSearch = () => {
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => {
        fetchEvents();
    }, 200);
};

const debounceOrganizerSearch = () => {
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => {
        fetchOrganizers();
    }, 200);
};

const fetchEvents = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/search/nav/events', { 
            params: { keywords: eventInput.value } 
        });
        
        if (response.data && Array.isArray(response.data)) {
            events.value = response.data;
        } else {
            events.value = [{
                model: {
                    id: 0,
                    name: 'No results found',
                    thumbImagePath: null,
                    slug: ''
                }
            }];
        }
    } catch (error) {
        console.error('Error fetching events:', error);
        events.value = [{
            model: {
                id: 0,
                name: 'Error loading results',
                thumbImagePath: null,
                slug: ''
            }
        }];
    } finally {
        isLoading.value = false;
    }
};

const fetchOrganizers = async () => {
    try {
        const response = await axios.get('/api/search/nav/organizers', { 
            params: { keywords: organizerInput.value } 
        });
        
        if (response.data && Array.isArray(response.data)) {
            organizers.value = response.data;
        } else {
            organizers.value = [{
                model: {
                    id: 0,
                    name: 'No results found',
                    thumbImagePath: null,
                    slug: ''
                }
            }];
        }
    } catch (error) {
        console.error('Error fetching organizers:', error);
        organizers.value = [{
            model: {
                id: 0,
                name: 'Error loading results',
                thumbImagePath: null,
                slug: ''
            }
        }];
    }
};

const onSelect = (item, type) => {
    saveSearchData(item, type);
    if (type === 'organizer') {
        window.location.href = `/organizers/${item.model.slug}`;
    }
    if (type === 'event') {
        window.location.href = `/events/${item.model.slug}`;
    }
};

const saveSearchData = (item, type) => {
    axios.post('/search/storedata', {type, name: item.model.name});
};

const getInitialData = async () => {
    try {
        await Promise.all([
            fetchEvents(),
            fetchOrganizers()
        ]);
        isLoaded.value = true;
    } catch (error) {
        console.error('Error fetching initial data:', error);
        isLoaded.value = true;
    }
};

watch(isVisible, (newValue) => {
    if (newValue === 'event') {
        fetchEvents();
    } else if (newValue === 'organizer') {
        fetchOrganizers();
    }
});

onMounted(() => {
    getInitialData();
});
</script>