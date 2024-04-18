<template>
    <div class="w-full grid grid-cols-5 md:h-[calc(100vh-8rem)]">
        <div class="bg-slate-700 overflow-auto h-screen md:h-full py-8">
            <div 
                @click="eventsOpen = !eventsOpen;"
                class="flex items-center cursor-pointer mx-4">
                <svg :class="{'rotate-[-90deg]': !eventsOpen}" class="w-8 h-8 fill-white">
                    <use xlink:href="/storage/website-files/icons.svg#ri-arrow-down-s-line" />
                </svg>
                <p class="text-xl text-white">Events</p>
            </div>
            <div v-if="eventsOpen" class="mt-4 px-8">
                <input 
                    @input="searchEvents"
                    v-model="eventSearch"
                    class="p-2 bg-slate-600 text-white text-opacity-50 rounded-lg" 
                    placeholder="Filter by name" 
                    type="text">
                <div 
                    v-for="eventConvo in eventList" :key="eventConvo.id"
                    @click="fetchConversation(eventConvo.id)"
                    class="flex items-center cursor-pointer py-2 hover:bg-slate-800 relative"
                    :class="{'text-opacity-100': eventConvo.id == url, 'text-opacity-50': eventConvo.id != url}">
                    <span class="mr-auto ml-2 whitespace-nowrap overflow-hidden text-ellipsis text-xl text-white">
                        {{ eventConvo.event_name }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-span-4 flex flex-col relative h-full">
            <Conversation 
                v-if="conversation"
                :user="user"
                v-model:value="conversation" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted} from 'vue';
import Conversation from './conversation.vue';

const props = defineProps({
    user: Object,
    events: Array
});

const eventList = ref(props.events);
const conversation = ref(null);
const eventsOpen = ref(true);
const eventSearch = ref('');
const url = new URL(window.location.href).searchParams.get("event");
let timeout = null;

const searchEvents = () => {
    clearTimeout(timeout);
    timeout = setTimeout(fetchEvents, 300);
};

const fetchEvents = async () => {
    const response = await axios.post(`/inbox/fetch/events`, { search: eventSearch.value });
    eventList.value = response.data;
};

const fetchConversation = async (convo) => {
    const response = await axios.get(`/inbox/fetch/conversation/${convo}`);
    conversation.value = response.data;
    history.pushState(null, null, `/inbox?event=${convo}`);
};

onMounted(() => {
    if (url) fetchConversation(url);
});

onUnmounted(() => {
    clearTimeout(timeout);
});
</script>