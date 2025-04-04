<template>
    <div class="relative text-1xl font-medium w-full h-[calc(100vh-8rem)] flex flex-col">
        <!-- Main Content Area with separate scrolling -->
        <div class="flex-1 flex h-full">
            <div class="mx-auto flex flex-1 flex-col md:flex-row">
                <!-- Navigation Sidebar with own scroll -->
                <div 
                    class="flex-shrink-0 overflow-y-auto border-r border-gray-200 w-full lg-air:w-[40rem] xl-air:w-[56rem] lg-air:block"
                    :class="{ 'hidden': isMobileAndConversationSelected }"
                >
                    <div class="flex items-center justify-center">
                        <nav class="relative flex flex-col items-center flex-shrink-0 w-full mx-auto pt-12">
                            <!-- Static Header -->
                            <div class="w-full flex flex-col items-center bg-white py-4 px-8">
                                <div class="w-full max-w-[58rem] lg-air:max-w-[40rem] space-y-8 md:px-8">
                                    <div class="flex items-center justify-between w-full h-20">
                                        <template v-if="!isSearching">
                                            <h1 class="text-5xl font-semibold truncate">Messages</h1>
                                            <button 
                                                @click="toggleSearch"
                                                class="w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex items-center justify-center"
                                            >
                                                <svg 
                                                    width="24" 
                                                    height="24" 
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
                                        
                                        <div v-else class="w-full h-full">
                                            <div class="flex border border-black rounded-full items-center h-full">
                                                <input 
                                                    ref="searchInput"
                                                    @input="searchConversations"
                                                    @blur="handleBlur"
                                                    v-model="searchQuery"
                                                    class="w-full px-4 py-3 bg-transparent border-none" 
                                                    placeholder="Search conversations" 
                                                    type="text"
                                                >
                                                <button
                                                    @click="clearSearch"
                                                    class="px-4 text-black hover:text-gray-600"
                                                >
                                                    <svg 
                                                        xmlns="http://www.w3.org/2000/svg" 
                                                        fill="none" 
                                                        viewBox="0 0 24 24" 
                                                        stroke="currentColor" 
                                                        class="w-8 h-8"
                                                    >
                                                        <path 
                                                            stroke-linecap="round" 
                                                            stroke-linejoin="round" 
                                                            stroke-width="2" 
                                                            d="M6 18L18 6M6 6l12 12" 
                                                        />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scrollable Content -->
                            <div class="w-full flex flex-col items-center overflow-y-auto max-h-[calc(100vh-19rem)]">
                                <div class="w-full space-y-8 max-w-[58rem] lg-air:max-w-[40rem] p-8 mb-20 ">
                                    <div 
                                        v-if="conversationList"
                                        v-for="convo in conversationList" 
                                        :key="convo.id"
                                        @click="fetchConversation(convo.id)"
                                        class="flex w-full items-center cursor-pointer hover:bg-neutral-50 relative rounded-3xl border border-neutral-200 p-8"
                                        :class="{ 'border-[#222222] shadow-focus-black bg-neutral-50': conversation && convo.id === conversation.id }"
                                    >
                                        <!-- Unread indicator -->
                                        <div 
                                            v-if="hasUnreadMessages(convo)"
                                            class="absolute top-4 right-4 w-3 h-3 bg-blue-500 rounded-full"
                                        ></div>
                                        
                                        <div class="mr-auto text-xl flex items-center">
                                            <picture v-if="convo.conversable?.thumbImagePath">
                                                <source :srcset="`${imageUrl}${convo.conversable.thumbImagePath}`" type="image/webp">
                                                <img 
                                                    :src="`${imageUrl}${convo.conversable.thumbImagePath.slice(0, -4)}jpg`" 
                                                    :alt="`${convo.subject}`" 
                                                    class="min-h-20 min-w-20 w-20 object-cover rounded-2xl"
                                                >
                                            </picture>
                                            <div class="ml-4">
                                                <p class="text-xl leading-tight">{{ convo.subject }}</p>
                                                <p class="text-sm text-neutral-500">
                                                    Messages: {{ convo.messages?.length || 0 }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>

                <!-- Main Content Column -->
                <div 
                    class="flex-1 flex-col h-full w-full md:w-auto"
                    :class="{ 'hidden md:flex': !isMobileAndConversationSelected, 'flex': isMobileAndConversationSelected }"
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
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import Conversation from './conversation.vue';

const props = defineProps({
    conversations: {
        type: Array,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
});

const hasError = ref(false);
const conversationList = ref(props.conversations || []);
const conversation = ref(null);
const searchQuery = ref('');
const url = new URL(window.location.href).searchParams.get("conversation");
const imageUrl = import.meta.env.VITE_IMAGE_URL;
let timeout = null;
const isSearching = ref(false);
const searchInput = ref(null);
const isDesktop = ref(!window.Laravel.isMobile);
const isMobileAndConversationSelected = computed(() => !isDesktop.value && conversation.value);

const searchConversations = () => {
    clearTimeout(timeout);
    timeout = setTimeout(fetchConversations, 300);
};

const fetchConversations = async () => {
    try {
        const response = await axios.post('/inbox/fetch/conversations', { 
            search: searchQuery.value 
        });
        conversationList.value = response.data;
    } catch (error) {
        console.error('Error fetching conversations:', error);
    }
};

const fetchConversation = async (convoId) => {
    hasError.value = false;
    try {
        const response = await axios.get(`/inbox/fetch/conversation/${convoId}`);
        if (response.data) {
            conversation.value = response.data;
            if (isDesktop.value) {
                history.pushState(null, null, `/inbox?conversation=${convoId}`);
            }
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
    if (!searchQuery.value) {
        isSearching.value = false;
    }
};

const closeConversation = () => {
    conversation.value = null;
    if (isDesktop.value) {
        history.pushState(null, null, '/inbox');
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    isSearching.value = false;
    conversationList.value = props.conversations;
};

const hasUnreadMessages = (convo) => {
    // Check if the conversation has any unread messages
    if (!convo.messages || !convo.messages.length) return false;
    
    return convo.messages.some(message => 
        !message.is_seen && message.user_id !== props.user.id
    );
};

onMounted(async () => {
    if (url) {
        await fetchConversation(url);
    } else if (isDesktop.value && props.conversations?.length > 0) {
        await fetchConversation(props.conversations[0].id);
    }
});

onUnmounted(() => {
    clearTimeout(timeout);
});
</script>