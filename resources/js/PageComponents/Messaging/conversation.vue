<template>
    <div v-if="conversation.messages" class="bg-white z-[1002] md:z-0 md:h-[calc(100vh-8rem)] flex flex-col justify-between relative overflow-auto">

        <div class="p-8 border-b border-neutral-200 flex relative items-center gap-8 sticky top-0 bg-white z-10">
            <div v-if="showBackButton" class="relative bg-white py-6 z-10">
                <button 
                    @click="$emit('backClick')" 
                    class="flex items-center gap-2 bg-neutral-100 p-2 rounded-full"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </button>
            </div>
            
            <!-- Action buttons instead of image -->
            <div class="flex gap-4 ml-8 md:ml-0">
                <!-- Edit button (for all types) -->
                <a 
                    v-if="conversation.conversable"
                    :href="getEditLink"
                    class="cursor-pointer"
                >
                    <div class="rounded-full bg-gray-100 w-16 h-16 flex items-center justify-center hover:bg-gray-200">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path 
                                stroke-linecap="round" 
                                stroke-linejoin="round" 
                                stroke-width="2" 
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" 
                            />
                        </svg>
                    </div>
                </a>
                
                <!-- Listing button (only for Organizer and Community) -->
                <a 
                    v-if="(isOrganizer || isCommunity) && conversation.conversable"
                    :href="getListingLink"
                    class="cursor-pointer"
                >
                    <div class="rounded-full bg-gray-100 w-16 h-16 flex items-center justify-center hover:bg-gray-200">
                        <svg 
                            class="w-8 h-8" 
                            viewBox="0 0 24 24" 
                            fill="none" 
                            stroke="currentColor" 
                            stroke-width="2"
                        >
                            <g stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="4" cy="6" r="1" fill="currentColor"/>
                                <line x1="8" y1="6" x2="20" y2="6"/>
                                <circle cx="4" cy="12" r="1" fill="currentColor"/>
                                <line x1="8" y1="12" x2="20" y2="12"/>
                                <circle cx="4" cy="18" r="1" fill="currentColor"/>
                                <line x1="8" y1="18" x2="20" y2="18"/>
                            </g>
                        </svg>
                    </div>
                </a>
            </div>

            <!-- Show link if conversable exists, otherwise just show text -->
            <a 
                v-if="conversation.conversable"
                :href="getConversableLink"
                target="_blank" 
                rel="noopener noreferrer" 
                class="text-2xl md:text-3xl ml-8 md:ml-0 hover:underline"
            >
                {{ conversation.subject }}
            </a>
            <span 
                v-else 
                class="text-2xl md:text-3xl ml-8 md:ml-0 text-neutral-500"
            >
                {{ conversation.subject }} (Removed)
            </span>
        </div>

        <div ref="messagesContainer" class="flex flex-col overflow-y-auto overflow-x-hidden px-8 flex-grow items-start pb-40 md:pb-0">
            <div class="flex flex-col w-full">
                <div 
                    v-for="message in conversation.messages" 
                    :key="message.id" 
                    class="my-4 first:mt-8 last:mb-8 w-full"
                >
                    <div 
                        class="flex items-end gap-4 max-w-full"
                        :class="{
                            'ml-auto flex-row-reverse': message.user_id === props.currentUserId,
                            'mr-auto': message.user_id !== props.currentUserId
                        }"
                    >
                        <!-- Avatar -->
                        <div :style="`background: ${message.user?.hexColor || '#gray'}`" class="w-14 h-14 flex-shrink-0 rounded-full overflow-hidden">
                            <img 
                                v-if="message.user?.thumbImagePath" 
                                :src="`${imageUrl}${message.user.thumbImagePath.slice(0, -4)}jpg`" 
                                class="w-full h-full" 
                                alt=""
                            >
                            <div v-else class="w-full h-full bg-gray-300 flex items-center justify-center">
                                {{ message.user?.name?.[0] || '?' }}
                            </div>
                        </div>

                        <!-- Message content -->
                        <div class="flex flex-col max-w-[66%] min-w-0">
                            <div class="flex items-center gap-2"
                                 :class="{ 'flex-row-reverse': message.user_id === props.currentUserId }">
                                <a :href="`/users/${message.user_id}`" class="text-2xl font-bold hover:underline">
                                    {{ message.user?.name || message.user?.email || 'Unknown User' }}
                                </a>
                                <span class="text-xl text-slate-400">{{ cleanTime(message.created_at) }}</span>
                            </div>
                            <div 
                                v-html="message.message" 
                                class="text-2xl p-6 mt-2 inline-block [&_p]:m-0 break-all overflow-hidden"
                                :class="messageClasses(message)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="fixed md:relative bottom-0 left-0 right-0 px-8 pb-8 pt-6 md:pt-0 bg-white md:border-0">
            <div class="relative flex items-center">
                <textarea
                    ref="textareaRef"
                    v-model="newMessage"
                    placeholder="Type a message"
                    rows="1"
                    @input="autoGrow"
                    class="w-full px-8 pr-16 py-4 rounded-5xl border-2 border-black focus:outline-none focus:border-neutral-400 text-2xl resize-none overflow-hidden"
                    @keydown.enter.prevent="onSubmit"
                    @keydown.shift.enter.prevent="newMessage += '\n'"
                />
                <button 
                    v-show="newMessage.trim()"
                    @click="onSubmit" 
                    :disabled="disabled"
                    class="absolute right-4 flex items-center justify-center rounded-full p-2 bg-black text-white hover:bg-neutral-700 disabled:bg-neutral-300 disabled:cursor-not-allowed"
                >
                    <svg v-if="!disabled" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 19V5M12 5l7 7M12 5l-7 7"/>
                    </svg>
                    <svg v-else class="animate-spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22C6.5 22 2 17.5 2 12S6.5 2 12 2s10 4.5 10 10" />
                    </svg>
                </button>
            </div>
            <p v-if="error" class="text-red-500 mt-2">{{ error }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, watchEffect, nextTick, onMounted, computed } from 'vue';
import dayjs from 'dayjs';

const props = defineProps({
    value: Object,
    showBackButton: Boolean,
    currentUserId: Number
});

const emit = defineEmits(['update:value', 'backClick']);

const messagesContainer = ref(null);
const disabled = ref(false);
const conversation = ref(props.value);
const newMessage = ref('');
const imageUrl = import.meta.env.VITE_IMAGE_URL;
const textareaRef = ref(null);
const error = ref(null);

const getConversableType = computed(() => {
    return conversation.value?.conversable_type?.split('\\').pop() || null;
});

const isEvent = computed(() => getConversableType.value === 'Event');
const isOrganizer = computed(() => getConversableType.value === 'Organizer');
const isCommunity = computed(() => getConversableType.value === 'Community');

const messageClasses = computed(() => message => ({
    'bg-neutral-700 text-white [&_p]:text-white rounded-t-2xl rounded-l-2xl': message.user_id === props.currentUserId,
    'bg-neutral-100 rounded-t-2xl rounded-r-2xl': message.user_id !== props.currentUserId
}));

const getConversableLink = computed(() => {
    const conversable = conversation.value?.conversable;
    if (!conversable) return '#';
    
    // Get type from the conversable_type if available
    const type = conversation.value?.conversable_type?.split('\\').pop() || conversable.type;
    
    switch(type) {
        case 'Event':
            return `/events/${conversable.slug}`;
        case 'Organizer':
            return `/organizers/${conversable.slug}`;
        case 'Community':
            return `/communities/${conversable.slug}`;
        default:
            return '#';
    }
});

const getEditLink = computed(() => {
    const conversable = conversation.value?.conversable;
    if (!conversable) return '#';
    
    const type = getConversableType.value;
    
    switch(type) {
        case 'Event':
            return `/hosting/event/${conversable.slug}/edit`;
        case 'Organizer':
            return `/organizers/${conversable.slug}/edit`;
        case 'Community':
            return `/communities/${conversable.slug}/edit`;
        default:
            return '#';
    }
});

const getListingLink = computed(() => {
    const type = getConversableType.value;
    
    switch(type) {
        case 'Organizer':
            return '/hosting/events';
        case 'Community':
            return '/communities/posts';
        default:
            return '#';
    }
});

const onSubmit = async () => {
    if (!newMessage.value.trim() || disabled.value) return;
    disabled.value = true;
    error.value = null;

    try {
        const response = await axios.post(`/inbox/conversation/${props.value.id}`, {
            message: newMessage.value,
            type: 'message'
        });
        
        emit('update:value', response.data);
        conversation.value = response.data;
        newMessage.value = '';
        if (textareaRef.value) {
            textareaRef.value.style.height = 'auto';
        }
        scrollToBottom(); 
    } catch (error) {
        console.error('Error posting message:', error);
        error.value = 'Failed to send message. Please try again.';
    }

    disabled.value = false;
};

const cleanTime = (data) => dayjs(data).format("h:mm A");

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

const autoGrow = (e) => {
    const textarea = e.target;
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
};

onMounted(() => {
    scrollToBottom(); 
});

watchEffect(() => {
    if (props.value) {
        conversation.value = props.value;
        console.log('Conversation updated:', conversation.value);
        nextTick(() => {
            scrollToBottom();
        });
    }
});
</script>
