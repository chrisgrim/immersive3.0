<template>
    <div>
        <template v-for="(item, index) in items">
            <div 
                class="bg-black bg-opacity-10 h-5 ml-2 mr-3 w-[2px]" 
                v-if="item.type === 'divider'"
                :key="'divider-' + index" /> 
             <button
             	v-else
				class="bg-transparent border-none rounded-md text-black h-10 w-10 mr-1 p-1"
				:key="'menuitem-' + index" >
				<svg class="fill-current h-full w-full">
                    <use :xlink:href="`/storage/website-files/icons.svg#ri-${item.icon}`" />
				</svg>
			</button>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';

// Assuming the editor is passed as a prop.
const props = defineProps({
    editor: {
        type: Object,
        required: true,
    },
});

const items = reactive([
    {
        icon: 'bold',
        title: 'Bold',
        action: () => props.editor.chain().focus().toggleBold().run(),
        isActive: () => props.editor.isActive('bold'),
    },
    {
        icon: 'italic',
        title: 'Italic',
        action: () => props.editor.chain().focus().toggleItalic().run(),
        isActive: () => props.editor.isActive('italic'),
    },
    {
        icon: 'strikethrough',
        title: 'Strike',
        action: () => props.editor.chain().focus().toggleStrike().run(),
        isActive: () => props.editor.isActive('strike'),
    },
    {
        type: 'divider',
    },
    {
        icon: 'link',
        title: 'Link',
        action: () => props.editor.chain().focus().setLink({ href: window.prompt('URL') }).run(),
        isActive: () => props.editor.isActive('link'),
    },
    {
        icon: 'link-unlink',
        title: 'UnLink',
        action: () => props.editor.chain().focus().unsetLink().run(),
        isActive: () => props.editor.isActive('link'),
    },
    {
        icon: 'list-unordered',
        title: 'Bullet List',
        action: () => props.editor.chain().focus().toggleBulletList().run(),
        isActive: () => props.editor.isActive('bulletList'),
    },
    {
        icon: 'list-ordered',
        title: 'Ordered List',
        action: () => props.editor.chain().focus().toggleOrderedList().run(),
        isActive: () => props.editor.isActive('orderedList'),
    },
    {
        icon: 'code-box-line',
        title: 'Code Block',
        action: () => props.editor.chain().focus().toggleCodeBlock().run(),
        isActive: () => props.editor.isActive('codeBlock'),
    },
    {
        type: 'divider',
    },
    {
        icon: 'double-quotes-l',
        title: 'Blockquote',
        action: () => props.editor.chain().focus().toggleBlockquote().run(),
        isActive: () => props.editor.isActive('blockquote'),
    },
    {
        icon: 'separator',
        title: 'Horizontal Rule',
        action: () => props.editor.chain().focus().setHorizontalRule().run(),
    },
    {
        type: 'divider',
    },
    {
        icon: 'format-clear',
        title: 'Clear Format',
        action: () => props.editor.chain()
          .focus()
          .clearNodes()
          .unsetAllMarks()
          .run(),
    },
    {
        type: 'divider',
    },
    {
        icon: 'arrow-go-back-line',
        title: 'Undo',
        action: () => props.editor.chain().focus().undo().run(),
    },
    {
        icon: 'arrow-go-forward-line',
        title: 'Redo',
        action: () => props.editor.chain().focus().redo().run(),
    },
]);

</script>
