<template>
    <div class="w-full grid grid-cols-5 md:h-[calc(100vh-8rem)]">
        <div class="overflow-auto h-screen md:h-full border-r">
            <div class="px-8 h-32 border-b flex items-center">
                <h4>Messages</h4>
            </div>
            <input 
                @input="searchEvents"
                v-model="eventSearch"
                class="px-8 py-4 w-full rounded-lg border-b" 
                placeholder="Filter by event name" 
                type="text">
            <div 
                v-if="eventList"
                v-for="eventConvo in eventList" :key="eventConvo.id"
                @click="fetchConversation(eventConvo.id)"
                class="flex items-center cursor-pointer p-8 hover:bg-slate-800 relative border-b"
                :class="{ 'bg-gray-100': conversation && eventConvo.id === conversation.id }">
                <div class="mr-auto ml-2 text-xl flex items-center">
                    <picture v-if="eventConvo.event">
                        <source :srcset="`${imageUrl}${eventConvo.event.thumbImagePath}`" type="image/webp">
                        <img :src="`${imageUrl}${eventConvo.event.thumbImagePath.slice(0, -4)}jpg`" :alt="`${eventConvo.event.name} Immersive Event`" class="min-h-20 min-w-20 w-20 object-cover rounded-full">
                    </picture>
                    <div class="ml-4">
                        <p class="text-lg">{{ eventConvo.event_name }}</p>
                        <p class="text-sm text-gray-500">
                            Messages: {{ eventConvo.messages?.length || 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-4 flex flex-col relative h-full">
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
            />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted} from 'vue';
import Conversation from './conversation.vue';

const props = defineProps({
    events: {
        type: Array,
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

onMounted(async () => {
    console.log('Initial events:', props.events);
    if (url) {
        await fetchConversation(url);
    } else if (props.events?.length > 0) {
        console.log('Loading first conversation:', props.events[0]);
        await fetchConversation(props.events[0].id);
    }
});

onUnmounted(() => {
    clearTimeout(timeout);
});
</script>