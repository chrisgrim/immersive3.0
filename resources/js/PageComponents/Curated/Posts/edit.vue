<template>
    <div class="max-w-screen-xl mx-auto">
        <div class="w-full lg:w-1/2 mx-auto px-4 md:px-0">
            <div class="py-12">
                <a 
                    class="hover:underline" 
                    :href="`/communities/${community.slug}/edit`">
                    {{community.name}}
                </a>
                <span class="mx-2">></span>
                <span>{{post.name}}</span>
            </div>
        </div>
        

        <div class="w-full lg:w-1/2 mx-auto px-4 md:px-0">
            <div class="my-8 relative ">
                <div class="relative mb-8">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only" :checked="post.status === 'p'" @change="toggleStatus">
                        <div class="relative w-32 h-10 bg-black rounded-full p-1 transition duration-300 ease-in-out"
                             :class="{'bg-[#ff385c]': post.status === 'p'}">
                            <div class="absolute inset-1 w-[50%] h-8 bg-white rounded-full transform transition-transform duration-300 ease-in-out shadow-md"
                                 :class="{'translate-x-[calc(100%-0.50rem)]': post.status === 'p'}">
                            </div>
                            <div class="relative flex justify-between items-center text-sm font-bold h-full">
                                <span class="flex-1 text-center z-10 transition-opacity duration-300"
                                      :class="post.status === 'd' ? 'text-black opacity-100' : 'opacity-0'">
                                    Draft
                                </span>
                                <span class="flex-1 text-center z-10 transition-opacity duration-300"
                                      :class="post.status === 'p' ? 'text-black opacity-100' : 'opacity-0'">
                                    Live
                                </span>
                            </div>
                        </div>
                    </label>
                </div>
                <template v-if="postEdit">
                    <div class="mb-4">
                        <textarea 
                            type="text" 
                            v-model="post.name"
                            @input="clearErrors"
                            class="text-4xl font-normal border border-[#222222] rounded-2xl p-4 w-full"
                            :class="{ 
                                'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': v$.name.$error,
                                'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !v$.name.$error 
                            }"
                            placeholder="Collection Name"
                            rows="2" />
                        <div class="flex justify-end mt-1" 
                            :class="{'text-red-500': isNameNearLimit, 'text-gray-500': !isNameNearLimit}">
                            {{ post.name?.length || 0 }}/100
                        </div>
                        <div v-if="v$.name.$error" class="px-4">
                            <p class="text-red-500 text-1xl" v-if="!v$.name.required">Please add a name.</p>
                            <p class="text-red-500 text-1xl" v-if="!v$.name.maxLength">The name is too long.</p>
                            <p class="text-red-500 text-1xl" v-if="!v$.name.serverError">Your community already has a post with a similar name</p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <textarea 
                            type="text"
                            v-model="post.blurb"
                            class="text-2xl border border-[#222222] rounded-2xl p-4 w-full"
                            :class="{ 
                                'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': v$.blurb.$error,
                                'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !v$.blurb.$error 
                            }"
                            placeholder="Collection tag line"
                            rows="2" />
                        <div class="flex justify-end mt-1" 
                            :class="{'text-red-500': isBlurbNearLimit, 'text-gray-500': !isBlurbNearLimit}">
                            {{ post.blurb?.length || 0 }}/100
                        </div>
                        <div v-if="v$.blurb.$error" class="px-4">
                            <p class="text-red-500 text-1xl" v-if="!v$.blurb.maxLength">The tag line is too long.</p>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <button 
                            class="rounded-2xl border border-black py-4 px-8 bg-white text-black hover:bg-black hover:text-white" 
                            @click="resetPost">Cancel</button>
                        <button 
                            @click="patchPost"
                            class="rounded-2xl py-4 px-8 bg-black text-white hover:bg-white hover:text-black border border-black">
                            Save Changes
                        </button>
                    </div>
                </template>
                <template v-else>
                    <div 
                        @click="postEdit=true"
                        class="">
                        <h1 class="font-bold">{{ post.name }}</h1>
                    </div>
                    <div 
                        @click="postEdit=true"
                        class="mt-6 relative">
                        <p class="font-bold text-xl">{{ post.blurb }}</p>
                    </div>
                </template>
                <div class="flex gap-8 my-8">
                    <div class="flex justify-between items-center">
                        <div @click.stop>
                            <div v-if="!selectedShelf">
                                <Dropdown
                                    :list="shelves"
                                    :placeholder="'Select Shelf'"
                                    @onSelect="handleShelfSelect" />
                            </div>
                            <DropdownList 
                                v-else
                                :selections="[selectedShelf]"
                                :show-remove="true"
                                @onSelect="removeShelf" />
                        </div>
                    </div>
                </div>

                <!-- Add Featured Image Section -->
                <div class="mb-8">
                    <div>
                        <div 
                            v-if="!hasImage"
                            @click="showImageModal = true"
                            class="relative aspect-[16/9] flex items-center justify-center border border-dashed border-gray-300 rounded-2xl cursor-pointer hover:border-black hover:border-2"
                        >
                            <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
                        </div>
                        <div v-else class="relative aspect-[16/9]">
                            <img 
                                :src="hasImage ? `${imageUrl}${hasImage}` : null" 
                                class="w-full h-full object-cover rounded-2xl" 
                            />
                            <div 
                                @click="deleteImage" 
                                class="absolute top-2 right-2 cursor-pointer bg-white rounded-full"
                                @mouseenter="hoveredImage = true"
                                @mouseleave="hoveredImage = false"
                            >
                                <component :is="hoveredImage ? RiCloseCircleFill : RiCloseCircleLine" />
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-4">
                            <button 
                                v-if="hasImage"
                                class="bg-black px-4 py-2 rounded-full text-white hover:bg-white hover:text-black"
                                @click="updateType">
                                {{ postType }}
                            </button>
                        </div>
                        <div v-if="showEventSearch && !value.thumbImagePath">
                            <Dropdown
                                :list="events"
                                :placeholder="'Search for event'"
                                @onSelect="handleEventSelect"
                                v-model="searchQuery"
                                class="w-full" />
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div v-if="!post.cards || post.cards.length === 0" 
                         class="flex justify-center">
                        <div class="top-0 bg-white flex-col flex w-96 mt-24 rounded-2xl p-4 border">
                            <button 
                                @click="showAddButtonOptions"
                                class="border-none h-16 items-center flex px-4">
                                Add card
                                <svg class="w-8 ml-2">
                                    <use v-if="!buttonOptions" :xlink:href="`/storage/website-files/icons.svg#ri-add-circle-line`" />
                                    <use v-else :xlink:href="`/storage/website-files/icons.svg#ri-close-circle-line`" />
                                </svg>
                            </button>
                            <template v-if="buttonOptions">
                                <button 
                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                    @click="selectButton('i')">
                                    Image Block
                                </button>
                                <button 
                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                    @click="selectButton('t')">
                                    Text Block
                                </button>
                                <button 
                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                    @click="selectButton('e')">
                                    Event Block
                                </button>
                            </template>
                        </div>
                    </div>

                    <draggable
                        v-model="post.cards" 
                        :item-key="card => card.id"
                        @start="handleDragStart" 
                        @end="debounce">
                        <template #item="{ element: card }">
                            <div :key="card.id">
                                <div v-if="activeCardId === card.id && newCardPosition === post.cards.indexOf(card)">
                                    <div v-if="blockType" class="mb-4">
                                        <EventBlock v-if="blockType==='e'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" />
                                        <ImageBlock v-if="blockType==='i'" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" />
                                        <TextBlock v-if="blockType==='t'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" />
                                    </div>
                                </div>
                                <div class="card-wrapper group">
                                    <div class="relative">
                                        <button 
                                            @click="showAddButtonOptionsForCard(card, 'top')"
                                            class="absolute left-1/2 transform -translate-x-1/2 -top-6 bg-white rounded-full border shadow-sm p-2 hover:shadow-md z-10 hidden group-hover:block">
                                            <svg class="w-6 h-6">
                                                <use :xlink:href="`/storage/website-files/icons.svg#ri-add-circle-line`" />
                                            </svg>
                                        </button>
                                        <div 
                                            v-if="activeAddButton === `${card.id}-top`"
                                            class="absolute left-1/2 transform -translate-x-1/2 mt-2 bg-white w-96 rounded-2xl p-4 border shadow-lg z-20">
                                            <button 
                                                class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                @click="addBlockAfterCard('t', card, 'top')">
                                                Text Block
                                            </button>
                                            <button 
                                                class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                @click="addBlockAfterCard('i', card, 'top')">
                                                Image Block
                                            </button>
                                            <button 
                                                class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                @click="addBlockAfterCard('e', card, 'top')">
                                                Event Block
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-12">   
                                        <EditCard 
                                            @update="updatePost"
                                            :parent-card="card" />
                                    </div>
                                    <div class="relative">
                                        <button 
                                            @click="showAddButtonOptionsForCard(card, 'bottom')"
                                            class="absolute left-1/2 transform -translate-x-1/2 -bottom-6 bg-white rounded-full border shadow-sm p-2 hover:shadow-md z-10 hidden group-hover:block">
                                            <svg class="w-6 h-6">
                                                <use :xlink:href="`/storage/website-files/icons.svg#ri-add-circle-line`" />
                                            </svg>
                                        </button>
                                        <div 
                                            v-if="activeAddButton === `${card.id}-bottom`"
                                            class="absolute left-1/2 transform -translate-x-1/2 bottom-full mb-2 bg-white w-96 rounded-2xl p-4 border shadow-lg z-20">
                                            <button 
                                                class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                @click="addBlockAfterCard('t', card, 'bottom')">
                                                Text Block
                                            </button>
                                            <button 
                                                class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                @click="addBlockAfterCard('i', card, 'bottom')">
                                                Image Block
                                            </button>
                                            <button 
                                                class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                @click="addBlockAfterCard('e', card, 'bottom')">
                                                Event Block
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="activeCardId === card.id && newCardPosition === post.cards.indexOf(card) + 1">
                                    <div v-if="blockType" class="mt-4">
                                        <EventBlock v-if="blockType==='e'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" />
                                        <ImageBlock v-if="blockType==='i'" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" />
                                        <TextBlock v-if="blockType==='t'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </draggable>
                </div>
            </div>
        </div>
        <transition name="slide-fade">
            <div 
                v-if="updated" 
                class="updated-notifcation">
                <p>Your collection has been updated.</p>
            </div>
        </transition>
        <div v-if="serverErrors" class="updated-notifcation">
            <transition-group name="slide-fade">
                <ul 
                    v-for="(error, index) in serverErrors"
                    :key="`name${index}`">
                    <li>
                        <p> {{ error.toString() }}</p>
                    </li>
                </ul>
            </transition-group>
        </div>
        <div v-if="showImageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl p-16 max-w-2xl w-full mx-4" @click.stop>
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Add Featured Image</h2>
                    <button @click="showImageModal = false">
                        <component :is="RiCloseCircleLine" class="w-6 h-6" />
                    </button>
                </div>
                
                <div class="space-y-4">
                    <!-- Upload Image Option -->
                    <div 
                        @click="triggerFileInput"
                        class="p-4 border border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-black hover:border-2 flex items-center gap-4"
                    >
                        <component :is="RiImageCircleLine" class="w-8 h-8" />
                        <span class="text-lg">Upload an image</span>
                        <input 
                            type="file" 
                            class="hidden fileInput" 
                            accept="image/*"
                            @change="handleFileChange" 
                        />
                    </div>

                    <!-- Select Event Option -->
                    <div class="p-4 border border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-black hover:border-2">
                        <Dropdown
                            :list="events"
                            :placeholder="'Search for event'"
                            @onSelect="handleEventSelect"
                            @input="debounce"
                            v-model="searchQuery"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import EditCard from './Cards/card-edit.vue'
import EventBlock from './Cards/block-event.vue'
import ImageBlock from './Cards/block-image.vue'
import TextBlock from './Cards/block-text.vue'
import Draggable from "vuedraggable"
import Dropdown from '@/GlobalComponents/dropdown.vue'
import DropdownList from '@/GlobalComponents/dropdown-list.vue'
import { RiImageCircleLine, RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue";
import axios from 'axios'

const props = defineProps({
    value: Object,
    user: Object,
    community: Object,
    shelves: Array
})

const emit = defineEmits(['update'])

const post = ref({
    ...props.value,
    cards: props.value?.cards || []
})
const postBeforeEdit = ref(props.value || {})
const onEdit = ref(false)
const buttonOptions = ref(false)
const blockType = ref(null)
const formData = ref(new FormData())
const serverErrors = ref(null)
const updated = ref(false)
const postEdit = ref(false)
const loading = ref(false)
const timeout = ref(null)
const activeAddButton = ref(null)
const activeCardId = ref(null)
const newCardPosition = ref(null)
const topPosition = ref(null)
const isDragging = ref(false)
const hoveredImage = ref(false)
const imageUrl = import.meta.env.VITE_IMAGE_URL
const showEventSearch = ref(false)
const options = ref([])
const showImageModal = ref(false)
const searchQuery = ref('')
const events = ref([])

// Validation rules
const rules = {
    name: { 
        required, 
        maxLength: maxLength(100),
        serverError: () => !serverErrors.value?.name
    },
    blurb: { 
        maxLength: maxLength(100) 
    }
}

const v$ = useVuelidate(rules, post)

const checkVuelidate = async () => {
    const result = await v$.value.$validate()
    return !result
}

const patchPost = async () => {
    if (await checkVuelidate()) return
    
    addPostData()
    try {
        console.log('Sending update with:', Object.fromEntries(formData.value))
        const res = await axios.post(
            `/communities/${props.community.slug}/${post.value.slug}/update`, 
            formData.value
        )
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()
        clear()

        // Check if slug changed and redirect
        if (res.data.slug_changed) {
            setTimeout(() => {
                window.location.href = `/communities/${props.community.slug}/${res.data.slug}/edit`
            }, 1000) // Wait for "Updated" message to show
        }
    } catch (err) {
        // Revert changes on error
        post.value = { ...postBeforeEdit.value }
        onErrors(err)
    }
}

const updatePostOrder = async () => {
    try {
        const list = post.value.cards.map((item, index) => ({
            ...item,
            order: index
        }))
        await axios.put(`/cards/${post.value.slug}/order`, list)
        onUpdated()
    } catch (error) {
        console.error('Error updating card order:', error)
        // Optionally revert changes on error
        post.value = { ...postBeforeEdit.value }
    }
}

const resetPost = () => {
    post.value = { ...postBeforeEdit.value }
    clear()
}

const debounce = () => {
    if (timeout.value) clearTimeout(timeout.value)
    timeout.value = setTimeout(fetchEvents, 300)
}

const addImage = (image) => {
    formData.value = new FormData()
    formData.value.append('image', image)
    loading.value = true
    
    // Send directly as POST request
    axios.post(
        `/communities/${props.community.slug}/${post.value.slug}/update`, 
        formData.value
    )
    .then(res => {
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()
        loading.value = false
    })
    .catch(err => {
        loading.value = false
        onErrors(err)
    })
}


const deleteImage = () => {
    formData.value = new FormData()
    formData.value.append('deleteImage', true)
    
    // Send directly as POST request
    axios.post(
        `/communities/${props.community.slug}/${post.value.slug}/update`, 
        formData.value
    )
    .then(res => {
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()
    })
    .catch(err => {
        onErrors(err)
    })
}

const addPostData = () => {
    formData.value = new FormData()
    formData.value.append('name', post.value.name)
    formData.value.append('blurb', post.value.blurb)
    formData.value.append('community_id', props.community.id)
    
    // Only append shelf_id if it's not null
    if (post.value.shelf_id !== null && post.value.shelf_id !== undefined) {
        formData.value.append('shelf_id', post.value.shelf_id)
    }
    
    // Add event_id if it exists
    if (post.value.event_id !== null && post.value.event_id !== undefined) {
        formData.value.append('event_id', post.value.event_id)
    }
    
    formData.value.append('status', post.value.status)
    formData.value.append('type', post.value.type)
}

const updatePost = (value) => {
    clear()
    post.value = value
    onUpdated()
}

const onUpdated = () => {
    v$.value.$reset()
    updated.value = true
    setTimeout(() => updated.value = false, 3000)
}

const showAddButtonOptions = () => {
    console.log('showAddButtonOptions called')
    clear()
    buttonOptions.value = !buttonOptions.value
    console.log('buttonOptions:', buttonOptions.value)
}

const selectButton = (val) => {
    console.log('selectButton called with:', val)
    buttonOptions.value = false
    blockType.value = val
    newCardPosition.value = 0
    
    // Don't set these for the first card
    if (post.value.cards && post.value.cards.length > 0) {
        activeCardId.value = post.value.cards[post.value.cards.length - 1].id
        topPosition.value = newCardPosition.value
    }
    
    console.log('State after selectButton:', {
        blockType: blockType.value,
        newCardPosition: newCardPosition.value,
        cards: post.value.cards,
        cardsLength: post.value.cards?.length,
        activeCardId: activeCardId.value,
        topPosition: topPosition.value
    })
}

const clear = () => {
    console.log('clear called')
    onEdit.value = false
    postEdit.value = false
    blockType.value = null
    loading.value = false
    formData.value = new FormData()
    activeAddButton.value = null
    activeCardId.value = null
    newCardPosition.value = null
    topPosition.value = null
}

const clearErrors = () => {
    serverErrors.value = null
}

const onErrors = (err) => {
    if (err.response?.data?.errors) {
        serverErrors.value = err.response.data.errors
    }
}

const showAddButtonOptionsForCard = (card, position) => {
    const buttonId = `${card.id}-${position}`
    activeAddButton.value = activeAddButton.value === buttonId ? null : buttonId
}

const addBlockAfterCard = (type, card, position) => {
    const index = post.value.cards.findIndex(c => c.id === card.id)
    console.log('Current cards:', post.value.cards.map(c => ({ id: c.id, order: c.order })))
    console.log('Adding block after card:', { cardId: card.id, index, position })
    
    activeCardId.value = card.id
    blockType.value = type
    topPosition.value = index
    newCardPosition.value = position === 'top' ? index : index + 1
    activeAddButton.value = null
    
    console.log('Final positions:', {
        activeCardId: activeCardId.value,
        topPosition: topPosition.value,
        newCardPosition: newCardPosition.value
    })
}

const handleNewCardUpdate = (updatedPost) => {
    console.log('handleNewCardUpdate START:', {
        currentCards: post.value.cards.map(c => ({ id: c.id, order: c.order })),
        newPosition: newCardPosition.value
    })
    
    if (updatedPost && updatedPost.cards) {
        const newCard = updatedPost.cards[updatedPost.cards.length - 1]
        console.log('New card to insert:', newCard)
        
        updatedPost.cards.pop()
        console.log('Cards after pop:', updatedPost.cards.map(c => ({ id: c.id, order: c.order })))
        
        updatedPost.cards.splice(newCardPosition.value, 0, newCard)
        console.log('Cards after splice:', updatedPost.cards.map(c => ({ id: c.id, order: c.order })))
        
        updatedPost.cards = updatedPost.cards.map((card, index) => ({
            ...card,
            order: index
        }))
        console.log('Final cards array:', updatedPost.cards.map(c => ({ id: c.id, order: c.order })))
    }
    
    post.value = updatedPost
    onUpdated()
    clear()
}

onMounted(() => {
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.relative')) {
            activeAddButton.value = null
        }
    })
})

onBeforeUnmount(() => {
    document.removeEventListener('click')
})

// Add these computed properties
const isNameNearLimit = computed(() => {
    const count = post.value.name?.length || 0
    return count > 90
})

const isBlurbNearLimit = computed(() => {
    const count = post.value.blurb?.length || 0
    return count > 90
})

const handleDragStart = async () => {
    if (onEdit.value) {
        // Check if there are unsaved changes
        const hasChanges = JSON.stringify(post.value) !== JSON.stringify(postBeforeEdit.value)
        
        if (hasChanges) {
            await patchPost() // Save changes
        }
    }
    clear() // Close any open edit modes
    isDragging.value = true
}

// Add computed for selected shelf if not already present
const selectedShelf = computed(() => {
    if (!post.value?.shelf_id) return null
    const shelf = props.shelves.find(s => s.id === Number(post.value.shelf_id))
    return shelf || null
})

// Add the updateStatus method if not already present
const updateStatus = () => {
    post.value.status = post.value.status === 'd' ? 'p' : 'd'
    patchPost()
}

// Add the shelf methods if not already present
const handleShelfSelect = async (shelf) => {
    try {
        post.value.shelf_id = Number(shelf.id)
        // Don't call clear() after this update
        const formDataCopy = new FormData()
        formDataCopy.append('shelf_id', shelf.id)
        
        const res = await axios.post(
            `/communities/${props.community.slug}/${post.value.slug}/update`, 
            formDataCopy
        )
        
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()
    } catch (error) {
        console.error('Error updating shelf:', error)
    }
}

const removeShelf = async () => {
    try {
        post.value.shelf_id = null
        // Don't call clear() after this update
        const formDataCopy = new FormData()
        formDataCopy.append('shelf_id', '')
        
        const res = await axios.post(
            `/communities/${props.community.slug}/${post.value.slug}/update`, 
            formDataCopy
        )
        
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()
    } catch (error) {
        console.error('Error removing shelf:', error)
    }
}

console.log('Selected Shelf:', selectedShelf.value)

const toggleStatus = () => {
    post.value.status = post.value.status === 'p' ? 'd' : 'p'
    patchPost()
}

// Add computed
const hasImage = computed(() => {
    if (post.value?.event_id) {
        return post.value.featured_event_image?.thumbImagePath
    } else if (post.value?.images?.length > 0) {
        return post.value.images[0].thumb_image_path || post.value.images[0].large_image_path
    } else {
        return post.value?.thumbImagePath
    }
})

const postType = computed(() => 
    post.value.type === 'h' ? 'Hidden' : 'Visible'
)

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        formData.value = new FormData()
        formData.value.append('image', file)
        addImage(file)
        showImageModal.value = false
    }
}

const triggerFileInput = () => {
    const fileInput = document.querySelector('.fileInput')
    if (fileInput) {
        fileInput.click()
    }
}

const updateType = () => {
    post.value.type = post.value.type === 'h' ? 's' : 'h'
    formData.value = new FormData()
    formData.value.append('type', post.value.type)
    patchPost()
}

const handleEventSelect = (event) => {
    if (event) {
        post.value.event_id = event.id
        patchPost()
        showImageModal.value = false
    }
}

// Function to fetch events based on the search query
const fetchEvents = async () => {
    try {
        const response = await axios.get('/api/search/nav/events', {
            params: { 
                query: searchQuery.value,
                limit: 10 // Optional: limit initial results
            }
        })
        
        events.value = response.data.map(item => ({
            id: item.model.id,
            name: item.model.name,
            thumbImagePath: item.model.thumbImagePath
        }))
    } catch (error) {
        console.error('Error fetching events:', error)
        events.value = []
    }
}

// Add a watch for searchQuery
watch(searchQuery, (newValue) => {
    if (newValue?.length >= 0) { // Changed from > 2 to >= 0
        debounce()
    }
})

// Add this watcher for showImageModal
watch(showImageModal, async (isOpen) => {
    if (isOpen) {
        // Fetch initial events when modal opens
        await fetchEvents()
    }
})
</script>

