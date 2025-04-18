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
        <div 
            v-else
            class="flex flex-col relative w-full border shadow-custom-6 rounded-4xl bg-white p-10 overflow-auto">
            <div class="w-full mt-2">
                <h2 style="font-family: 'Montserrat', sans-serif;" class="text-4.5xl text-black leading-8 font-bold">Search Events</h2>
            </div>
            <div class="w-full flex-grow flex flex-col mt-10">
                <div class="w-full border border-slate-400 rounded-2xl flex items-center">
                    <svg class="w-8 h-8 fill-black z-50 ml-8">
                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                    </svg>
                    <input 
                        ref="event"
                        class="relative text-4xl p-8 w-full font-bold z-40 bg-transparent focus:border-none placeholder-slate-400"
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
                            class="flex pb-2 items-center gap-8 hover:bg-neutral-100" 
                            v-for="item in filteredEvents"
                            :key="item.model.id"
                            @click="onSelect(item, 'event')">
                            <div class="w-20 flex-shrink-0 aspect-[3/4] rounded-2xl overflow-hidden flex justify-center items-center">
                                <picture v-if="item.model.thumbImagePath" class="w-full h-full block">
                                    <source 
                                        type="image/webp" 
                                        :srcset="`${imageUrl}${item.model.thumbImagePath}`"> 
                                    <img 
                                        :src="`${imageUrl}${item.model.thumbImagePath.slice(0, -4)}jpg`"
                                        class="w-full h-full object-cover">
                                </picture>
                                <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M8 21h8a4 4 0 004-4V7a4 4 0 00-4-4H8a4 4 0 00-4 4v10a4 4 0 004 4z" />
                                    </svg>
                                </div>
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
        <div 
            v-else-if="isVisible==='organizer'" 
            class="flex-grow relative w-full border shadow-custom-6 rounded-4xl bg-white p-10 overflow-auto">
            <div class="w-full mt-2">
                <h2 style="font-family: 'Montserrat', sans-serif;" class="text-4.5xl text-black leading-8 font-bold">Search Organizers</h2>
            </div>
            <div class="w-full flex-grow flex flex-col mt-10 overflow-auto">
                <div class="w-full border border-slate-400 rounded-2xl flex items-center">
                    <svg class="w-8 h-8 fill-black z-50 ml-8">
                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                    </svg>
                    <input 
                        ref="organizer"
                        class="relative text-4xl p-8 w-full font-bold z-40 bg-transparent focus:border-none placeholder-slate-400"
                        v-model="organizerInput"
                        placeholder="Search Organizers"
                        @input="debounceOrganizerSearch"
                        autocomplete="false"
                        type="text">
                </div>
                
                <!-- Organizer List -->
                <ul class="bg-white w-full mx-0 overflow-y-auto py-8 list-none flex-grow">
                    <li 
                        class="flex pb-2 items-center gap-8 hover:bg-neutral-100" 
                        v-for="item in filteredOrganizers"
                        :key="item.model.id"
                        @click="onSelect(item, 'organizer')">
                        <div class="w-20 aspect-[3/4] rounded-2xl overflow-hidden flex justify-center items-center">
                            <picture v-if="item.model.thumbImagePath" class="w-full h-full">       
                                <source 
                                    type="image/webp" 
                                    :srcset="`${imageUrl}${item.model.thumbImagePath}`"> 
                                <img 
                                    :src="`${imageUrl}${item.model.thumbImagePath.slice(0, -4)}jpg`"
                                    class="w-full h-full object-cover">
                            </picture>
                            <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M8 21h8a4 4 0 004-4V7a4 4 0 00-4-4H8a4 4 0 00-4 4v10a4 4 0 004 4z" />
                                </svg>
                            </div>
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
    // Commenting out to prevent 405 error
    // saveSearchData(item, type);
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