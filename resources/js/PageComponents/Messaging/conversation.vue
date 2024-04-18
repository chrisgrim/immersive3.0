<template>
    <div v-if="messages && event" class="flex flex-col justify-between h-[calc(100vh-8rem)]">
        <div class="p-8 border-b border-gray-300 flex relative">
            <picture>
                <source :srcset="`${imageUrl}${event.thumbImagePath}`" type="image/webp">
                <img :src="`${imageUrl}${event.thumbImagePath.slice(0, -4)}jpg`" :alt="`${event.name} Immersive Event`" class="h-20 md:h-44 object-cover rounded-xl">
            </picture>
            <h2 class="ml-4 text-2xl md:text-3xl leading-6 md:leading-8">{{ event.name }}</h2>
        </div>

        <div class="flex flex-col overflow-auto px-8">
            <div v-for="message in messages" :key="message.id" class="my-4 first:mt-8 last:mb-8">
                <div v-if="message && message.user && message.user.thumbImagePath" class="flex items-center">
                    <div :style="`background:${message.user.hexColor}`" class="w-14 h-14 rounded-lg mr-2 overflow-hidden">
                        <img :src="`${imageUrl}${message.user.thumbImagePath.slice(0, -4)}jpg`" class="w-full h-full" alt="">
                    </div>
                    <div class="flex-grow">
                        <a :href="`/users/${message.user.id}`" class="text-2xl font-bold hover:underline">
                            {{ message.user.name }}
                        </a>
                        <span class="text-xl text-slate-400">{{ cleanTime(message.created_at) }}</span>
                        <p v-html="message.message" class="text-2xl" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="editor" class="px-8 mt-[-1rem]">
            <div class="tiptap-editor bg-white border border-gray-300 rounded-lg text-black flex flex-col mb-4">
                <menu-bar :editor="editor" class="p-1 border-b flex"/>
                <editor-content :editor="editor" class="flex-auto overflow-auto p-5 px-4"/>
                <div class="flex justify-between border-t p-2">
                    <button @click="cancel" class="rounded-xl py-2 px-4 bg-white text-black hover:bg-black hover:text-white">
                        Cancel
                    </button>
                    <button @click="onSubmit" class="rounded-xl py-2 px-4 bg-black text-white hover:bg-white hover:text-black">
                        Send
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch , reactive} from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import dayjs from 'dayjs';
import Conversation from './conversation.vue';
import menuBar from './Components/tiptap-bar.vue';

const props = defineProps({
    user: Object,
    value: Object,
});

const messages = ref(props.value.messages);
const event = ref(props.value.event);
const newMessage = ref('');
const imageUrl = import.meta.env.VITE_IMAGE_URL;

const editor = useEditor({
    content: newMessage.value,
    extensions: [StarterKit],
    onUpdate: ({ editor }) => {
        newMessage.value = editor.getHTML(); // This will update every time the content changes
    }
});

const cleanTime = (data) => dayjs(data).format("h:mm A");

const onSubmit = async () => {
    try {
        const response = await axios.post(`/inbox/conversation/${props.value.id}`, {
            message: newMessage.value,
            type: 'message'
        });
        messages.value = response.data.messages;
        newMessage.value = '';
        if (editor) {
            editor.value.commands.setContent('');
        }
    } catch (error) {
        console.error('Error posting message:', error);
    }
};

// Watch for deep changes in messages
watch(messages, (newMessages, oldMessages) => {
    console.log("Messages changed:", newMessages);
}, { deep: true });

// Watch for deep changes in event
watch(event, (newEvent, oldEvent) => {
    console.log("Event changed:", newEvent);
}, { deep: true });
</script>