<template>
    <div v-if="conversation.messages && conversation.event" class="h-[calc(100vh-8rem)] flex flex-col justify-between relative overflow-auto">
        <div class="px-8 h-32 min-h-32 border-b border-gray-300 flex relative items-center">
            <picture>
                <source :srcset="`${imageUrl}${conversation.event.thumbImagePath}`" type="image/webp">
                <img :src="`${imageUrl}${conversation.event.thumbImagePath.slice(0, -4)}jpg`" :alt="`${conversation.event.name} Immersive Event`" class="h-20 object-cover rounded-xl">
            </picture>
            <h4 class="ml-4">{{ conversation.event.name }}</h4>
        </div>

        <div ref="messagesContainer" class="flex flex-col overflow-auto px-8 flex-grow items-start">
            <div class="flex flex-col">
                <div v-for="message in conversation.messages" :key="message.id" class="my-4 first:mt-8 last:mb-8">
                    <div class="flex items-center">
                        <div :style="`background: ${message.user?.hexColor || '#gray'}`" class="w-14 h-14 rounded-full mr-2 overflow-hidden">
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
                        <div class="flex-grow">
                            <a :href="`/users/${message.user_id}`" class="text-2xl font-bold hover:underline">
                                {{ message.user?.name || message.user?.email || 'Unknown User' }}
                            </a>
                            <span class="text-xl text-slate-400">{{ cleanTime(message.created_at) }}</span>
                            <p v-html="message.message" class="text-2xl" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="editor" class="px-8 relative">
            <div class="tiptap-editor bg-white border border-gray-300 rounded-lg text-black flex flex-col mb-4 relative">
                <menu-bar :editor="editor" class="p-1 border-b flex"/>
                <editor-content :editor="editor" class="flex-auto overflow-auto p-5 px-4"/>
                <div class="flex justify-between border-t p-2">
                    <button @click="cancel" class="rounded-xl py-2 px-4 bg-white text-black hover:bg-black hover:text-white">
                        Cancel
                    </button>
                    <button @click="onSubmit" :disabled="disabled" class="rounded-xl py-2 px-4 bg-black text-white hover:bg-white hover:text-black disabled:bg-gray-300 disabled:cursor-not-allowed disabled:text-gray-500">
                        Send
                    </button>
                </div>
                 <!-- Validation Messages -->
                <div v-if="v$.newMessage.$dirty" class="text-red-500">
                    <p v-if="v$.newMessage.required.$invalid">Message is required.</p>
                    <p v-if="v$.newMessage.maxLength.$invalid">Message must be less than 5000 characters.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref , watch, watchEffect, nextTick, onMounted} from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import { useVuelidate } from '@vuelidate/core';
import { required, maxLength } from '@vuelidate/validators';
import StarterKit from '@tiptap/starter-kit';
import dayjs from 'dayjs';
import Conversation from './conversation.vue';
import menuBar from './Components/tiptap-bar.vue';

const props = defineProps({
    value: Object,
});

const emit = defineEmits(['update:value']);

const messagesContainer = ref(null);
const disabled = ref(false);
const conversation = ref(props.value);
const newMessage = ref('');
const imageUrl = import.meta.env.VITE_IMAGE_URL;

const editor = useEditor({
    content: newMessage.value,
    extensions: [StarterKit],
    onUpdate: ({ editor }) => {
        newMessage.value = editor.getHTML(); // This will update every time the content changes
        v$.value.$reset();
    }
});

const rules = {
    newMessage: {
        required,
        maxLength: maxLength(5000)
    }
};

const v$ = useVuelidate(rules, { newMessage });

const cleanTime = (data) => dayjs(data).format("h:mm A");

const onSubmit = async () => {

    v$.value.$touch();
    if (v$.value.$error) return 
    disabled.value = true

    try {
        const response = await axios.post(`/inbox/conversation/${props.value.id}`, {
            message: newMessage.value,
            type: 'message'
        });
        
        // Update the parent component with new data
        emit('update:value', response.data);
        
        // Update local conversation ref
        conversation.value = response.data;
        
        newMessage.value = '';
        if (editor) {
            editor.value.commands.setContent('');
        }
        v$.value.$reset();
        scrollToBottom(); 
    } catch (error) {
        console.error('Error posting message:', error);
    }

    disabled.value = false
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

onMounted(() => {
    scrollToBottom(); 
});

watchEffect(() => {
    if (props.value) {
        conversation.value = props.value;
    }
});

</script>

<style>
    .tiptap.ProseMirror:focus {
        outline: none;
    }
</style>