<template>
    <div class="w-full grid md:grid-cols-[35rem_1fr] md:h-[calc(100vh-8rem)]">
        <div 
            class="px-8 overflow-auto h-screen md:h-full border-r"
            :class="{ 'hidden md:block': isMobileAndConversationSelected }"
        >
            <div class="h-32 flex items-center justify-between">
                <template v-if="!isSearching">
                    <h4>Messages</h4>
                    <button 
                        @click="toggleSearch"
                        class="p-4 rounded-full bg-neutral-100 hover:bg-gray-100"
                    >
                        <svg 
                            width="18" 
                            height="18" 
                            viewBox="0 0 24 24" 
                            fill="none" 
                            stroke="currentColor" 
                            stroke-width="2" 
                            stroke-linecap="round" 
                            stroke-linejoin="round"
                        >
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </template>
                <input 
                    v-else
                    ref="searchInput"
                    @input="searchEvents"
                    @blur="handleBlur"
                    v-model="eventSearch"
                    class="px-8 py-4 w-full rounded-lg" 
                    placeholder="Filter by event name" 
                    type="text"
                >
            </div>
            <div 
                v-if="eventList"
                v-for="eventConvo in eventList" :key="eventConvo.id"
                @click="fetchConversation(eventConvo.id)"
                class="flex items-center cursor-pointer p-4 hover:bg-neutral-100 relative rounded-2xl mb-4"
                :class="{ 'bg-neutral-100': conversation && eventConvo.id === conversation.id }">
                <div class="mr-auto ml-2 text-xl flex items-center">
                    <picture v-if="eventConvo.event">
                        <source :srcset="`${imageUrl}${eventConvo.event.thumbImagePath}`" type="image/webp">
                        <img :src="`${imageUrl}${eventConvo.event.thumbImagePath.slice(0, -4)}jpg`" :alt="`${eventConvo.event.name} Immersive Event`" class="min-h-20 min-w-20 w-20 object-cover rounded-2xl">
                    </picture>
                    <div class="ml-4">
                        <p class="text-xl leading-tight">{{ eventConvo.event_name }}</p>
                        <p class="text-sm text-neutral-500">
                            Messages: {{ eventConvo.messages?.length || 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div 
            class="flex flex-col relative h-full w-full"
            :class="{ 'hidden md:flex': !isMobileAndConversationSelected }"
        >
            <div v-if="hasError" class="flex flex-col items-center justify-center p-4 h-full w-full">
                <div>
                    <h3>Sorry, something went wrong.</h3>
                </div>
                <div class="mt-8">
                    <p>Please reach out to support@everythingimmersive.com </p>
                </div>
            </div>
            <Conversation 
                v-if="conversation"
                v-model:value="conversation"
                @update:value="updateConversation"
                :showBackButton="!isDesktop"
                @backClick="closeConversation"
                :currentUserId="props.user.id"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import Conversation from './conversation.vue';

const props = defineProps({
    events: {
        type: Array,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
});

const hasError = ref(false);
const eventList = ref(props.events || []);
const conversation = ref(null);
const eventSearch = ref('');
const url = new URL(window.location.href).searchParams.get("event");
const imageUrl = import.meta.env.VITE_IMAGE_URL;
let timeout = null;
const isSearching = ref(false);
const searchInput = ref(null);
const isDesktop = ref(window.innerWidth >= 768);
const isMobileAndConversationSelected = computed(() => !isDesktop.value && conversation.value);

const searchEvents = () => {
    clearTimeout(timeout);
    timeout = setTimeout(fetchEvents, 300);
};

const fetchEvents = async () => {
    const response = await axios.post(`/inbox/fetch/events`, { search: eventSearch.value });
    eventList.value = response.data;
};

const fetchConversation = async (convoId) => {
    hasError.value = false;
    try {
        const response = await axios.get(`/inbox/fetch/conversation/${convoId}`);
        console.log('Response:', response.data);
        if (response.data) {
            conversation.value = response.data;
            history.pushState(null, null, `/inbox?event=${convoId}`);
        }
    } catch (error) {
        console.error('Error fetching conversation:', error);
        hasError.value = true;
    }
};

const updateConversation = (newValue) => {
    conversation.value = newValue;
};

const toggleSearch = () => {
    isSearching.value = true;
    nextTick(() => {
        searchInput.value?.focus();
    });
};

const handleBlur = () => {
    if (!eventSearch.value) {
        isSearching.value = false;
    }
};

const closeConversation = () => {
    conversation.value = null;
    history.pushState(null, null, '/inbox');
};

const handleResize = () => {
    isDesktop.value = window.innerWidth >= 768;
};

onMounted(async () => {
    console.log('Initial events:', props.events);
    if (url) {
        await fetchConversation(url);
    } else if (props.events?.length > 0) {
        console.log('Loading first conversation:', props.events[0]);
        await fetchConversation(props.events[0].id);
    }
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    clearTimeout(timeout);
    window.removeEventListener('resize', handleResize);
});
</script>