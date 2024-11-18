<template>
    <div class="flex flex-wrap gap-1.5 p-2.5 border-b">
        <template v-for="(item, index) in items" :key="`menu-item-${index}`">
            <div 
                v-if="item.type === 'divider'" 
                class="w-px h-7 bg-gray-200 mx-1.5" />
            <menu-item 
                v-else 
                v-bind="item" />
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import MenuItem from './MenuItem.vue'

const props = defineProps({
    editor: {
        type: Object,
        required: true
    }
})

const items = computed(() => [
    {
        icon: 'bold',
        title: 'Bold',
        action: () => props.editor.chain().focus().toggleBold().run(),
        isActive: () => props.editor.isActive('bold')
    },
    {
        icon: 'italic',
        title: 'Italic',
        action: () => props.editor.chain().focus().toggleItalic().run(),
        isActive: () => props.editor.isActive('italic')
    },
    {
        icon: 'strikethrough',
        title: 'Strike',
        action: () => props.editor.chain().focus().toggleStrike().run(),
        isActive: () => props.editor.isActive('strike')
    },
    { type: 'divider' },
    {
        icon: 'h-3',
        title: 'Heading 3',
        action: () => props.editor.chain().focus().toggleHeading({ level: 3 }).run(),
        isActive: () => props.editor.isActive('heading', { level: 3 })
    },
    {
        icon: 'h-4',
        title: 'Heading 4',
        action: () => props.editor.chain().focus().toggleHeading({ level: 4 }).run(),
        isActive: () => props.editor.isActive('heading', { level: 4 })
    },
    {
        icon: 'paragraph',
        title: 'Paragraph',
        action: () => props.editor.chain().focus().setParagraph().run(),
        isActive: () => props.editor.isActive('paragraph')
    },
    { type: 'divider' },
    {
        icon: 'list-unordered',
        title: 'Bullet List',
        action: () => props.editor.chain().focus().toggleBulletList().run(),
        isActive: () => props.editor.isActive('bulletList')
    },
    {
        icon: 'list-ordered',
        title: 'Ordered List',
        action: () => props.editor.chain().focus().toggleOrderedList().run(),
        isActive: () => props.editor.isActive('orderedList')
    },
    {
        icon: 'barcode-box-line',
        title: 'Code Block',
        action: () => props.editor.chain().focus().toggleCodeBlock().run(),
        isActive: () => props.editor.isActive('codeBlock')
    },
    { type: 'divider' },
    {
        icon: 'double-quotes-l',
        title: 'Blockquote',
        action: () => props.editor.chain().focus().toggleBlockquote().run(),
        isActive: () => props.editor.isActive('blockquote')
    },
    {
        icon: 'separator',
        title: 'Horizontal Rule',
        action: () => props.editor.chain().focus().setHorizontalRule().run()
    },
    { type: 'divider' },
    {
        icon: 'format-clear',
        title: 'Clear Format',
        action: () => props.editor.chain().focus().clearNodes().unsetAllMarks().run()
    },
    { type: 'divider' },
    {
        icon: 'arrow-go-back-line',
        title: 'Undo',
        action: () => props.editor.chain().focus().undo().run()
    },
    {
        icon: 'arrow-go-forward-line',
        title: 'Redo',
        action: () => props.editor.chain().focus().redo().run()
    }
])
</script>