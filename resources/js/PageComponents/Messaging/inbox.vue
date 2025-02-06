<template>
    <div class="w-full grid md:grid-cols-[35rem_1fr] md:h-[calc(100vh-8rem)]">
        <div 
            class="md:px-8 overflow-auto h-screen md:h-full border-r"
            :class="{ 'hidden md:block': isMobileAndConversationSelected }"
        >
            <div class="h-32 flex items-center justify-between mt-12 px-8 md:px-0 md:mt-0 mb-8 md:mb-0 border-b border-neutral-200 md:border-none">
                <template v-if="!isSearching">
                    <h1 class="text-5xl md:text-4xl font-medium">Messages</h1>
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
                    @input="searchConversations"
                    @blur="handleBlur"
                    v-model="searchQuery"
                    class="px-8 py-4 w-full rounded-lg" 
                    placeholder="Search conversations" 
                    type="text"
                >
            </div>
            <div 
                v-if="conversationList"
                v-for="convo in conversationList" 
                :key="convo.id"
                @click="fetchConversation(convo.id)"
                class="flex items-center cursor-pointer px-8 md:px-4 md:p-4 hover:bg-neutral-100 relative rounded-2xl mb-10 md:mb-4"
                :class="{ 'bg-neutral-100': conversation && convo.id === conversation.id }"
            >
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
const isDesktop = ref(window.innerWidth >= 768);
const isMobileAndConversationSelected = computed(() => !isDesktop.value && conversation.value);

const searchConversations = () => {
    clearTimeout(timeout);
    timeout = setTimeout(fetchConversations, 300);
};

const fetchConversations = async () => {
    const response = await axios.post(`/inbox/fetch/conversations`, { search: searchQuery.value });
    conversationList.value = response.data;
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

const handleResize = () => {
    isDesktop.value = window.innerWidth >= 768;
};

onMounted(async () => {
    if (url) {
        await fetchConversation(url);
    } else if (isDesktop.value && props.conversations?.length > 0) {
        await fetchConversation(props.conversations[0].id);
    }
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    clearTimeout(timeout);
    window.removeEventListener('resize', handleResize);
});
</script>