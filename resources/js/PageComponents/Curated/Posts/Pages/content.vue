<template>
    <main class="w-full min-h-fit">
        <!-- Toggle Sidebar Button -->
        <div class="absolute z-[500] left-12 top-12">
            <button @click="toggleSidebar" class="bg-white flex border-none p-6 rounded-2xl items-center justify-center shadow-lg">
                <span class="text-2xl font-medium">{{ isSidebarHidden ? 'Collapse' : 'Expand' }}</span>
            </button>
        </div>
        <div class="flex flex-col w-full">
            <!-- Cards List -->
            <div class="relative">
                <draggable
                    v-model="post.cards" 
                    :item-key="card => card.id"
                    :disabled="isAnyCardInEditMode"
                    @start="handleDragStart" 
                    @end="debouncePostOrder"
                    class="space-y-4"
                >
                    <template #item="{ element: card }">
                        <div :key="card.id">
                            <!-- New Block Position (Top) -->
                            <div v-if="activeCardId === card.id && newCardPosition === post.cards.indexOf(card)">
                                <div v-if="blockType" class="mb-4">
                                    <component 
                                        :is="blockComponents[blockType]"
                                        @cancel="clear"
                                        @update="handleNewCardUpdate"
                                        :post="post"
                                        :position="newCardPosition"
                                        :community="community"
                                    />
                                </div>
                            </div>

                            <!-- Card Wrapper -->
                            <div class="card-wrapper group">
                                <div class="relative">
                                    <!-- Add Button (Top) -->
                                    <button 
                                        @click="showAddButtonOptionsForCard(card, 'top')"
                                        class="absolute left-1/2 transform -translate-x-1/2 -translate-y-1/2 top-0 bg-white rounded-full border shadow-sm p-2 hover:shadow-md z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                    >
                                        <component :is="RiAddLine" class="w-6 h-6" />
                                    </button>

                                    <!-- Add Options Menu (Top) -->
                                    <div 
                                        v-if="activeAddButton === `${card.id}-top`"
                                        class="absolute left-1/2 transform -translate-x-1/2 -translate-y-full top-0 bg-white w-96 rounded-2xl p-4 border shadow-lg z-20"
                                    >
                                        <button 
                                            v-for="(label, type) in blockTypes"
                                            :key="type"
                                            class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-neutral-400 hover:text-white"
                                            @click="addBlockAfterCard(type, card, 'top')"
                                        >
                                            {{ label }}
                                        </button>
                                    </div>

                                    <!-- Card Content -->
                                    <EditCard 
                                        @update="updatePost"
                                        @edit-mode-change="handleEditModeChange"
                                        :parent-card="{ ...card, post }"
                                        :community="community"
                                    />

                                    <!-- Add Button (Bottom) -->
                                    <button 
                                        @click="showAddButtonOptionsForCard(card, 'bottom')"
                                        class="absolute left-1/2 transform -translate-x-1/2 translate-y-1/2 bottom-0 bg-white rounded-full border shadow-sm p-2 hover:shadow-md z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                    >
                                        <component :is="RiAddLine" class="w-6 h-6" />
                                    </button>

                                    <!-- Add Options Menu (Bottom) -->
                                    <div 
                                        v-if="activeAddButton === `${card.id}-bottom`"
                                        class="absolute left-1/2 transform -translate-x-1/2 translate-y-full bottom-0 bg-white w-96 rounded-2xl p-4 border shadow-lg z-20"
                                    >
                                        <button 
                                            v-for="(label, type) in blockTypes"
                                            :key="type"
                                            class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-neutral-400 hover:text-white"
                                            @click="addBlockAfterCard(type, card, 'bottom')"
                                        >
                                            {{ label }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- New Block Position (Bottom) -->
                            <div v-if="activeCardId === card.id && newCardPosition === post.cards.indexOf(card) + 1">
                                <div v-if="blockType" class="mt-4">
                                    <component 
                                        :is="blockComponents[blockType]"
                                        @cancel="clear"
                                        @update="handleNewCardUpdate"
                                        :post="post"
                                        :position="newCardPosition"
                                        :community="community"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                </draggable>

                <!-- Add New Block Button -->
                <div class="flex w-full mt-8">
                    <div class="w-full flex-col flex rounded-2xl p-4 border">
                        <template v-if="!blockType">
                            <button 
                                @click="showButtonOptions"
                                class="border-none h-16 items-center flex px-4"
                            >
                                Add block
                                <component :is="RiAddLine" class="w-8 ml-2" />
                            </button>
                            <template v-if="buttonOptions">
                                <button 
                                    v-for="(label, type) in blockTypes"
                                    :key="type"
                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-neutral-400 hover:text-white"
                                    @click="selectButton(type)"
                                >
                                    {{ label }}
                                </button>
                            </template>
                        </template>
                        <template v-else>
                            <component 
                                :is="blockComponents[blockType]"
                                @cancel="clear"
                                @update="handleNewCardUpdate"
                                :post="post"
                                :position="post.cards?.length || 0"
                                :community="community"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, onMounted, onBeforeUnmount } from 'vue';
import { RiAddLine } from '@remixicon/vue';
import draggable from 'vuedraggable';
import EditCard from '../Cards/card-edit.vue';
import EventBlock from '../Cards/block-event.vue';
import ImageBlock from '../Cards/block-image.vue';
import TextBlock from '../Cards/block-text.vue';
import axios from 'axios';

const post = inject('post');
const community = inject('community');
const emit = defineEmits(['toggle-sidebar']);

// Track sidebar state
const isSidebarHidden = ref(false);

// Refs
const blockType = ref(null);
const activeAddButton = ref(null);
const activeCardId = ref(null);
const newCardPosition = ref(null);
const topPosition = ref(null);
const buttonOptions = ref(false);
const isDragging = ref(false);
const isAnyCardInEditMode = ref(false);
const timeout = ref(null);

// Block components mapping
const blockComponents = {
    't': TextBlock,
    'i': ImageBlock,
    'e': EventBlock
};

// Block type labels
const blockTypes = {
    't': 'Text Block',
    'i': 'Image Block',
    'e': 'Event Block'
};

// Click outside handler function
const handleClickOutside = (e) => {
    if (!e.target.closest('.relative')) {
        activeAddButton.value = null;
    }
};

// Methods
const showButtonOptions = () => {
    clear();
    buttonOptions.value = !buttonOptions.value;
};

const selectButton = (val) => {
    buttonOptions.value = false;
    blockType.value = val;
    newCardPosition.value = post.cards?.length || 0;
    activeCardId.value = null;
    topPosition.value = null;
};

const showAddButtonOptionsForCard = (card, position) => {
    const buttonId = `${card.id}-${position}`;
    activeAddButton.value = activeAddButton.value === buttonId ? null : buttonId;
};

const addBlockAfterCard = (type, card, position) => {
    const index = post.cards.findIndex(c => c.id === card.id);
    activeCardId.value = card.id;
    blockType.value = type;
    topPosition.value = index;
    newCardPosition.value = position === 'top' ? index : index + 1;
    activeAddButton.value = null;
};

const handleNewCardUpdate = (updatedPost) => {
    if (updatedPost) {
        Object.assign(post, updatedPost);
    }
    clear();
};

const updatePost = (value) => {
    Object.assign(post, value);
    clear();
};

const handleEditModeChange = (isInEditMode) => {
    isAnyCardInEditMode.value = isInEditMode;
};

const clear = () => {
    blockType.value = null;
    activeAddButton.value = null;
    activeCardId.value = null;
    newCardPosition.value = null;
    topPosition.value = null;
    buttonOptions.value = false;
};

const debouncePostOrder = () => {
    if (timeout.value) clearTimeout(timeout.value);
    timeout.value = setTimeout(() => {
        const orderedCards = post.cards.map((card, index) => ({
            id: card.id,
            order: index
        }));
        saveCardOrder(orderedCards);
    }, 500);
};

const saveCardOrder = async (orderedCards) => {
    try {
        // Use the proper cards/order endpoint
        const response = await axios.put(
            `/communities/${community.slug}/posts/${post.slug}/cards/order`,
            orderedCards
        );
        
        // If we get a response with updated data, update the post
        if (response.data) {
            Object.assign(post, response.data);
        }
    } catch (error) {
        console.error('Error saving card order:', error);
    }
};

const handleDragStart = () => {
    isDragging.value = true;
    clear();
};

// Method to toggle the sidebar
const toggleSidebar = () => {
    isSidebarHidden.value = !isSidebarHidden.value;
    emit('toggle-sidebar');
};

// Lifecycle hooks with proper cleanup
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    if (timeout.value) {
        clearTimeout(timeout.value);
    }
});

// Component API
defineExpose({
    isValid: async () => true,
    submitData: (additionalData = null) => {
        if (additionalData) {
            return additionalData;
        }
        return null;
    }
});
</script>

<style scoped>
.card-wrapper {
    position: relative;
    padding: 1rem 0;
}

/* Add smooth transitions for all interactive elements */
.transition-opacity {
    transition: opacity 0.2s ease;
}
</style>