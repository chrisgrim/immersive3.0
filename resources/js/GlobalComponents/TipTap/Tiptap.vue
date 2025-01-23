<template>
    <div class="tiptap-wrapper">
        <MenuBar v-if="editor" :editor="editor" />

        <EditorContent 
            :editor="editor" 
            class="editor-content break-words hyphens-auto whitespace-pre-wrap text-2xl" 
        />

        <div class="flex justify-end gap-2 p-2 border-t">
            <button 
                @click="$emit('cancel')"
                class="px-4 py-2 text-2xl hover:bg-gray-100 rounded-lg"
                :disabled="isDisabled">
                Cancel
            </button>
            <button 
                @click="$emit('save')"
                :disabled="isDisabled"
                class="px-4 py-2 text-2xl bg-black text-white rounded-lg hover:bg-gray-800 disabled:opacity-50">
                Save
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import MenuBar from './MenuBar.vue'
import MenuItem from './MenuItem.vue'
import Link from '@tiptap/extension-link'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    isDisabled: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'save', 'cancel'])

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            paragraph: {
                HTMLAttributes: {
                    class: 'mb-8'
                }
            },
            heading: {
                HTMLAttributes: {
                    class: 'mb-8'
                }
            }
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-blue-600 hover:underline cursor-pointer'
            },
            linkOnPaste: true,
        })
    ],
    onUpdate: ({ editor }) => {
        let html = editor.getHTML()
            .replace(/class="mb-4"/g, 'class="mb-8"')
            .replace(/>\s+</g, '><');
        emit('update:modelValue', html)
    }
})

// Watch for external changes to modelValue
watch(() => props.modelValue, (newContent) => {
    const isSame = editor.value?.getHTML() === newContent
    if (editor.value && !isSame) {
        editor.value.commands.setContent(newContent, false)
    }
})

// Cleanup
onBeforeUnmount(() => {
    editor.value?.destroy()
})
</script>

<style>
.tiptap-wrapper {
    @apply bg-white border rounded-lg;
}

.editor-content {
    @apply p-4 min-h-[100px] max-h-[500px] w-full max-w-none break-words whitespace-pre-wrap text-2xl overflow-y-auto;
}

.ProseMirror {
    @apply min-h-[100px] w-full outline-none;
    height: 100%; /* Make it fill the container */
}

.tiptap.ProseMirror {
    height: 100%;
    min-height: inherit;
}

/* These styles will be applied to both editor and display */
:deep(.ProseMirror p),
.editor-content p {
    @apply text-2xl leading-relaxed mb-4;
}

:deep(.ProseMirror p p),
.editor-content p p {
    @apply mb-4;  /* Add margin between nested paragraphs */
}

:deep(.ProseMirror p:empty),
.editor-content p:empty {
    @apply h-4 mb-4 block min-h-[1rem];  /* Force empty paragraphs to have height */
}

:deep(.ProseMirror-trailingBreak),
.editor-content br {
    @apply block mb-4;  /* Make break tags create actual space */
    content: "";
    display: block;
    height: 1rem;
}

.editor-content h1 {
    @apply text-5xl font-bold mb-4;
}

.editor-content h2 {
    @apply text-4xl font-bold mb-3;
}

.editor-content h3 {
    @apply text-3xl font-bold mb-3;
}

.editor-content h4 {
    @apply text-2xl font-bold mb-2;
}

.editor-content ul {
    @apply list-disc pl-5 mb-4;
}

.editor-content ol {
    @apply list-decimal pl-5 mb-4;
}

.editor-content blockquote {
    @apply border-l-4 border-gray-300 pl-4 italic my-4;
}

.editor-content code {
    @apply bg-gray-100 px-1 rounded;
}

.editor-content pre {
    @apply bg-gray-100 p-4 rounded-lg my-4;
}

.editor-content a {
    @apply text-blue-600 hover:underline;
}

.editor-content hr {
    @apply my-8 border-t border-gray-300;
}

/* Optional: Add custom scrollbar styling */
.editor-content::-webkit-scrollbar {
    width: 8px;
}

.editor-content::-webkit-scrollbar-track {
    @apply bg-gray-100 rounded-r;
}

.editor-content::-webkit-scrollbar-thumb {
    @apply bg-gray-300 rounded-r hover:bg-gray-400;
}
</style>