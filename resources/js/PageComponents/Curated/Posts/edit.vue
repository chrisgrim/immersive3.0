<template>
    <div class="max-w-screen-2xl-air mx-auto">
        <div class="w-full lg:w-1/2 mx-auto px-4">
            <div class="my-8 pb-80 relative ">
                <div class="flex gap-8 mt-4 px-4 mb-16">
                    <div class="flex w-full justify-between items-center">
                        <div>
                            <div class="flex items-center gap-4">
                                <ToggleSwitch
                                v-model="isLive"
                                left-label="Draft"
                                right-label="Live"
                                text-size="sm"
                                @update:modelValue="handleStatusChange" />
                                <a 
                                    v-if="isLive"
                                    :href="`/communities/${community.slug}/posts/${post.slug}`"
                                    class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                                    title="View live community"
                                >
                                    <component :is="RiExternalLinkLine" class="w-6 h-6 text-gray-600" />
                                </a>
                            </div>
                        </div>
                        
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
                <div class="mt-28">
                    <template v-if="postEdit">
                        <div class="mb-4">
                            <textarea 
                                type="text" 
                                v-model="post.name"
                                @input="clearErrors"
                                class="text-4xl font-normal border border-[#222222] rounded-2xl p-4 w-full"
                                :class="{ 
                                    'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': v$.post.name.$error,
                                    'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !v$.post.name.$error 
                                }"
                                placeholder="Collection Name"
                                rows="2" />
                            <div class="flex justify-end mt-1" 
                                :class="{'text-red-500': isNameNearLimit, 'text-gray-500': !isNameNearLimit}">
                                {{ post.name?.length || 0 }}/100
                            </div>
                            <div v-if="v$.post.$error" class="px-4">
                                <p class="text-red-500 text-1xl" v-if="!v$.post.name.required.$response">Please add a name.</p>
                                <p class="text-red-500 text-1xl" v-if="!v$.post.name.maxLength.$response">The name is too long.</p>
                            </div>
                        </div>
                        <div class="mb-4">
                            <textarea 
                                type="text"
                                v-model="post.blurb"
                                class="text-2xl border border-[#222222] rounded-2xl p-4 w-full"
                                :class="{ 
                                    'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': v$.post.blurb.$error,
                                    'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !v$.post.blurb.$error 
                                }"
                                placeholder="Collection tag line"
                                rows="2" />
                            <div class="flex justify-end mt-1" 
                                :class="{'text-red-500': isBlurbNearLimit, 'text-gray-500': !isBlurbNearLimit}">
                                {{ post.blurb?.length || 0 }}/255
                            </div>
                            <div v-if="v$.post.$error" class="px-4">
                                <p class="text-red-500 text-1xl" v-if="!v$.post.blurb.maxLength.$response">The tag line is too long.</p>
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
                            class="px-4">
                            <h1 class="font-bold">{{ post.name }}</h1>
                        </div>
                        <div 
                            @click="postEdit=true"
                            class="mt-6 relative px-4">
                            <p>{{ post.blurb }}</p>
                        </div>
                    </template>
                </div>

                <!-- Add Featured Image Section -->
                <div class="my-8 relative">
                    <div>
                        <div 
                            v-if="!hasImage"
                            @click="showImageModal = true"
                            class="relative aspect-[16/9] flex items-center justify-center border border-dashed border-gray-300 rounded-2xl cursor-pointer hover:border-black hover:border-2"
                        >
                            <component :is="RiImageCircleLine" style="width:4rem; height: 4rem;" />
                        </div>
                        <div v-else :class="['relative', isVisible ? 'aspect-[16/9]' : 'h-16']">
                            <img 
                                v-if="isVisible"
                                :src="hasImage ? `${imageUrl}${hasImage}` : null" 
                                class="w-full h-full object-cover rounded-2xl" 
                            />
                            <div 
                                v-if="isVisible"
                                @click="deleteImage" 
                                class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white rounded-full"
                                @mouseenter="hoveredImage = true"
                                @mouseleave="hoveredImage = false"
                            >
                                <component :is="hoveredImage ? RiCloseCircleFill : RiCloseCircleLine" />
                            </div>
                            <div class="absolute bottom-4 left-4 drop-shadow-lg">
                                <ToggleSwitch
                                    v-if="hasImage"
                                    v-model="isVisible"
                                    left-label="Hidden"
                                    right-label="Visible"
                                    text-size="lg"
                                    @update:modelValue="handleVisibilityChange" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <draggable
                        v-model="post.cards" 
                        :item-key="card => card.id"
                        @start="handleDragStart" 
                        @end="debouncePostOrder">
                        <template #item="{ element: card }">
                            <div :key="card.id">
                                <div v-if="activeCardId === card.id && newCardPosition === post.cards.indexOf(card)">
                                    <div v-if="blockType" class="mb-4">
                                        <EventBlock v-if="blockType==='e'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" :community="community" />
                                        <ImageBlock v-if="blockType==='i'" @update="handleNewCardUpdate" @cancel="clear" :post="post" :position="newCardPosition" :community="community" />
                                        <TextBlock v-if="blockType==='t'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" :community="community" />
                                    </div>
                                </div>
                                <div class="card-wrapper group mb-4">
                                    <div class="relative">
                                        <button 
                                            @click="showAddButtonOptionsForCard(card, 'top')"
                                            class="absolute left-1/2 transform -translate-x-1/2 -top-6 bg-white rounded-full border shadow-sm p-2 hover:shadow-md z-10 hidden group-hover:block">
                                            <component :is="RiAddLine" class="w-6 h-6" />
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
                                    <div>   
                                        <EditCard 
                                            @update="updatePost"
                                            :parent-card="{ ...card, post }"
                                            :community="community" />
                                    </div>
                                    <div class="relative">
                                        <button 
                                            @click="showAddButtonOptionsForCard(card, 'bottom')"
                                            class="absolute left-1/2 transform -translate-x-1/2 -bottom-6 bg-white rounded-full border shadow-sm p-2 hover:shadow-md z-10 hidden group-hover:block">
                                            <component :is="RiAddLine" class="w-6 h-6" />
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
                                        <EventBlock v-if="blockType==='e'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" :community="community" />
                                        <ImageBlock v-if="blockType==='i'" @update="handleNewCardUpdate" @cancel="clear" :post="post" :position="newCardPosition" :community="community" />
                                        <TextBlock v-if="blockType==='t'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" :community="community" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <div class="flex w-full">
                        <div class="top-0 bg-white flex-col w-full flex mt-24 rounded-2xl p-4 border">
                            <template v-if="!blockType">
                                <button 
                                    @click="showAddButtonOptions"
                                    class="border-none h-16 items-center flex px-4">
                                    Add card
                                    <component :is="RiAddLine" class="w-8 ml-2" />
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
                            </template>
                            <template v-else>
                                <EventBlock v-if="blockType==='e'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" :community="community" />
                                <ImageBlock v-if="blockType==='i'" @update="handleNewCardUpdate" @cancel="clear" :post="post" :position="newCardPosition" :community="community" />
                                <TextBlock v-if="blockType==='t'" @cancel="clear" @update="handleNewCardUpdate" :post="post" :position="newCardPosition" :community="community" />
                            </template>
                        </div>
                    </div>
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
            <div class="bg-white rounded-3xl p-8 max-w-3xl w-full mx-4" @click.stop>
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-2xl font-bold">Add Featured Image</h2>
                    <button 
                        @click="showImageModal = false"
                        class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200"
                    >
                        <component :is="RiCloseCircleLine" class="w-10 h-10" />
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Upload Image Option -->
                    <div 
                        @click="triggerFileInput"
                        class="p-8 border border-neutral-300 hover:border-[#222222] rounded-3xl cursor-pointer transition-all duration-200 flex items-center gap-4"
                    >
                        <component :is="RiImageCircleLine" class="w-10 h-10" />
                        <span class="text-2xl">Upload an image</span>
                        <input 
                            type="file" 
                            class="hidden fileInput" 
                            accept="image/*"
                            @change="handleFileChange" 
                        />
                    </div>

                    <!-- Select Event Option -->
                    <div class="p-8 border border-neutral-300 hover:border-[#222222] rounded-3xl cursor-pointer transition-all duration-200">
                        <EventSearch @select="handleEventSelect" />
                    </div>
                </div>
            </div>
        </div>
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="showSuccessModal"
                class="fixed top-36 right-4 z-50 bg-white rounded-xl shadow-lg p-4 max-w-sm border"
            >
                <div class="flex items-center gap-3">
                    <component :is="RiCheckLine" class="w-6 h-6 text-green-500 flex-shrink-0" />
                    <p class="text-gray-600">Post updated successfully</p>
                </div>
            </div>
        </Transition>
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
import { 
    RiImageCircleLine, 
    RiCloseCircleLine, 
    RiCloseCircleFill, 
    RiAddLine,
    RiExternalLinkLine,
    RiCheckLine 
} from "@remixicon/vue";
import axios from 'axios'
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue'
import EventSearch from './event-search.vue'

const props = defineProps({
    value: Object,
    user: Object,
    community: Object,
    shelves: Array
})

const emit = defineEmits(['update'])

const post = ref({
    ...props.value,
    cards: props.value?.cards || [],
    name: props.value?.name || '',
    blurb: props.value?.blurb || ''
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
const showImageModal = ref(false)
const showSuccessModal = ref(false)

// Validation rules
const rules = {
    post: {
        name: { 
            required,
            maxLength: maxLength(100)
        },
        blurb: { 
            maxLength: maxLength(255)
        }
    }
}

// Create validation state that only includes the fields we want to validate
const validationState = computed(() => ({
    post: {
        name: post.value.name,
        blurb: post.value.blurb
    }
}))

// Create validator with our validation state
const v$ = useVuelidate(rules, validationState)

// Update checkVuelidate to only check post fields
const checkVuelidate = async () => {
    await v$.value.$validate()
    const hasPostErrors = v$.value.post.name.$error || v$.value.post.blurb.$error
    
    console.log('Post validation:', {
        hasErrors: hasPostErrors,
        nameError: v$.value.post.name.$error,
        blurbError: v$.value.post.blurb.$error
    })
    
    return hasPostErrors
}

const patchPost = async () => {
    console.log('Starting validation check')
    const hasErrors = await checkVuelidate()
    console.log('Validation errors?', hasErrors)
    
    if (hasErrors) {
        console.log('Validation failed:', v$.value.$errors)
        return
    }
    
    console.log('Proceeding with patch')
    addPostData()
    try {
        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${post.value.slug}`, 
            formData.value
        )
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()
        clear()

        if (res.data.slug_changed) {
            setTimeout(() => {
                window.location.href = `/communities/${props.community.slug}/posts/${res.data.slug}/edit`
            }, 1000)
        }
    } catch (err) {
        console.error('Patch error:', err)
        onErrors(err)
    }
}

const updatePostOrder = async () => {
    try {
        const list = post.value.cards.map((item, index) => ({
            ...item,
            order: index
        }))
        await axios.put(
            `/communities/${props.community.slug}/posts/${post.value.slug}/cards/order`, 
            list
        )
        onUpdated()
    } catch (error) {
        console.error('Error updating card order:', error)
        post.value = { ...postBeforeEdit.value }
    }
}

const resetPost = () => {
    post.value = { ...postBeforeEdit.value }
    clear()
}

const debounce = () => {
    console.log('Debounce called with query:', searchQuery.value);
    if (timeout.value) clearTimeout(timeout.value)
    timeout.value = setTimeout(() => {
        console.log('Executing debounced fetch with query:', searchQuery.value);
        fetchEvents()
    }, 300)
}

const debouncePostOrder = () => {
    if (timeout.value) clearTimeout(timeout.value)
    timeout.value = setTimeout(() => {
        updatePostOrder()
    }, 500)
}


const addImage = (image) => {
    formData.value = new FormData()
    formData.value.append('image', image)
    loading.value = true
    
    axios.post(
        `/communities/${props.community.slug}/posts/${post.value.slug}`, 
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
    
    axios.post(
        `/communities/${props.community.slug}/posts/${post.value.slug}`, 
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
    showSuccessModal.value = true
    setTimeout(() => {
        showSuccessModal.value = false
    }, 3000)
}

const showAddButtonOptions = () => {
    clear()
    buttonOptions.value = !buttonOptions.value
}

const selectButton = (val) => {
    buttonOptions.value = false
    blockType.value = val
    
    // Set position to the end of the list
    newCardPosition.value = post.value.cards?.length || 0
    activeCardId.value = null
    topPosition.value = null
}

const clear = () => {
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
    console.log('Adding block:', {
        type,
        cardId: card.id,
        requestedPosition: position,
        calculatedIndex: index,
        finalPosition: position === 'top' ? index : index + 1
    })
    
    activeCardId.value = card.id
    blockType.value = type
    topPosition.value = index
    newCardPosition.value = position === 'top' ? index : index + 1
    activeAddButton.value = null
}

const handleNewCardUpdate = (updatedPost) => {
    console.log('handleNewCardUpdate received:', {
        currentCards: post.value.cards.map(c => ({ id: c.id, order: c.order })),
        updatedCards: updatedPost.cards.map(c => ({ id: c.id, order: c.order }))
    });
    
    if (updatedPost) {
        // Simply update the post with the server response
        post.value = { ...updatedPost }
        postBeforeEdit.value = { ...updatedPost }
    }
    
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
    return count > 230
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

// Add these near your other refs
const isLive = computed({
    get: () => post.value.status === 'p',
    set: (value) => {
        post.value.status = value ? 'p' : 'd'
    }
})

// Replace the toggleStatus function with this
const handleStatusChange = (value) => {
    post.value.status = value ? 'p' : 'd'
    patchPost()
}

// Add the shelf methods if not already present
const handleShelfSelect = async (shelf) => {
    try {
        post.value.shelf_id = Number(shelf.id)
        const formDataCopy = new FormData()
        formDataCopy.append('shelf_id', shelf.id)
        
        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${post.value.slug}`, 
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
        const formDataCopy = new FormData()
        formDataCopy.append('shelf_id', '')
        
        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${post.value.slug}`, 
            formDataCopy
        )
        
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()
    } catch (error) {
        console.error('Error removing shelf:', error)
    }
}

// Add computed
const hasImage = computed(() => {
    if (post.value?.event_id) {
        return post.value.featured_event_image?.largeImagePath
    } else if (post.value?.images?.length > 0) {
        return post.value.images[0].large_image_path
    } else {
        return post.value?.largeImagePath
    }
})

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

const handleEventSelect = (event) => {
    if (event) {
        post.value.event_id = event.id
        patchPost()
        showImageModal.value = false
    }
}

// Add this watcher for showImageModal
watch(showImageModal, async (isOpen) => {
    if (isOpen) {
        // Fetch initial events when modal opens
        await fetchEvents()
    }
})

// Add these near your other refs
const isVisible = computed({
    get: () => post.value.type !== 'h',
    set: (value) => {
        post.value.type = value ? 's' : 'h'
    }
})

// Add this function to handle visibility changes
const handleVisibilityChange = (value) => {
    post.value.type = value ? 's' : 'h'
    patchPost()
}


</script>

