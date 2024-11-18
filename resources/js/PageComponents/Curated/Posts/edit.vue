<template>
    <div class="m-auto w-full md:px-12 md:py-8 lg:py-0 lg:px-32 max-w-screen-xl">
        <div class="py-12">
            <p class="text-1xl">
                <a 
                    class="underline" 
                    :href="`/communities/${community.slug}/edit`">{{community.name}}</a> > {{post.name}}
            </p>
        </div>
        <div class="flex flex-col md:flex-row">
            <div class="w-full inline-block md:w-8/12 md:py-8 md:pr-8">
                <div class="mb-8">
                    <template v-if="postEdit">
                        <div class="border-gray-200 border p-4 rounded-2xl w-full mb-4">
                            <input 
                                type="text" 
                                v-model="post.name"
                                @input="clearErrors"
                                :class="{ 'error': v$.name.$error }"
                                placeholder="Collection Name">
                            <div v-if="v$.name.$error" class="validation-error">
                                <p class="error" v-if="!v$.name.required">Please add a name.</p>
                                <p class="error" v-if="!v$.name.serverError">Your community already has a post with a similar name</p>
                                <p class="error" v-if="!v$.name.maxLength">The name is too long.</p>
                            </div>
                        </div>
                        <div class="border-gray-200 border p-4 rounded-2xl w-full mb-4">
                            <input 
                                type="text"
                                v-model="post.blurb"
                                :class="{ 'error': v$.blurb.$error }"
                                placeholder="Collection tag line">
                            <div v-if="v$.blurb.$error" class="validation-error">
                                <p class="error" v-if="!v$.blurb.maxLength">The name is too long.</p>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <button 
                                class="px-4 py-2" 
                                @click="resetPost">Cancel</button>
                            <button 
                                @click="patchPost"
                                class="bg-black text-white px-4 py-2">Save</button>
                        </div>
                    </template>
                    <template v-else>
                        <div 
                            @click="postEdit=true"
                            class="">
                            <h2>{{ post.name }}</h2>
                        </div>
                        <div 
                            @click="postEdit=true"
                            class="mt-4 relative">
                            <p>{{ post.blurb }}</p>
                        </div>
                    </template>
                    <div class="relative">
                        <draggable
                            v-model="post.cards" 
                            :item-key="card => card.id"
                            @start="isDragging=true" 
                            @end="debounce">
                            <template #item="{ element: card }">
                                <div :key="card.id">
                                    <div v-if="activeCardId === card.id && topPosition === newCardPosition">
                                        <EventBlock 
                                            v-if="blockType==='e'"
                                            @cancel="clear"
                                            @update="handleNewCardUpdate"
                                            :post="post"
                                            :position="newCardPosition" />
                                        <ImageBlock 
                                            v-if="blockType==='i'"
                                            @update="handleNewCardUpdate"
                                            :post="post"
                                            :position="newCardPosition" />
                                        <TextBlock 
                                            v-if="blockType==='t'"
                                            @cancel="clear"
                                            @update="handleNewCardUpdate"
                                            :post="post"
                                            :position="newCardPosition" />
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
                                                    @click="addBlockAfterCard('i', card, 'top')">
                                                    Image Block
                                                </button>
                                                <button 
                                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                    @click="addBlockAfterCard('t', card, 'top')">
                                                    Text Block
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
                                                class="absolute left-1/2 transform -translate-x-1/2 mt-2 bg-white w-96 rounded-2xl p-4 border shadow-lg z-20">
                                                <button 
                                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                    @click="addBlockAfterCard('i', card, 'bottom')">
                                                    Image Block
                                                </button>
                                                <button 
                                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                    @click="addBlockAfterCard('t', card, 'bottom')">
                                                    Text Block
                                                </button>
                                                <button 
                                                    class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                                    @click="addBlockAfterCard('e', card, 'bottom')">
                                                    Event Block
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="activeCardId === card.id && topPosition !== newCardPosition">
                                        <EventBlock 
                                            v-if="blockType==='e'"
                                            @cancel="clear"
                                            @update="handleNewCardUpdate"
                                            :post="post"
                                            :position="newCardPosition" />
                                        <ImageBlock 
                                            v-if="blockType==='i'"
                                            @update="handleNewCardUpdate"
                                            :post="post"
                                            :position="newCardPosition" />
                                        <TextBlock 
                                            v-if="blockType==='t'"
                                            @cancel="clear"
                                            @update="handleNewCardUpdate"
                                            :post="post"
                                            :position="newCardPosition" />
                                    </div>
                                </div>
                            </template>
                        </draggable>
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
                </div>
            </div>
            <SideBar 
                @update="handleSidebarUpdate"
                @deleteImage="deleteImage"
                @addImage="addImage"
                @addEventFeaturedImage="addEventFeaturedImage"
                @reOrder="debounce"
                :community="community"
                :shelves="shelves"
                :loading="loading"
                :value="post"
                :user="user"
                v-model="post" />
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
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import EditCard from './Cards/card-edit.vue'
import EventBlock from './Cards/block-event.vue'
import ImageBlock from './Cards/block-image.vue'
import TextBlock from './Cards/block-text.vue'
import SideBar from './nav.vue'
import Draggable from "vuedraggable"

const props = defineProps({
    value: Object,
    user: Object,
    curator: Boolean,
    community: Object,
    shelves: Array
})

const post = ref(props.value || {})
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
    timeout.value = setTimeout(() => {
        updatePostOrder()
    }, 500)
}

const addImage = (image) => {
    formData.value.append('image', image)
    loading.value = true
    patchPost()
}

const addEventFeaturedImage = (event) => {
    formData.value.append('event_id', event.id)
    patchPost()
}

const deleteImage = () => {
    formData.value.append('deleteImage', true)
    patchPost()
}

const addPostData = () => {
    formData.value = new FormData()
    formData.value.append('_method', 'PUT')
    formData.value.append('name', post.value.name)
    formData.value.append('blurb', post.value.blurb)
    formData.value.append('community_id', props.community.id)
    
    // Only append shelf_id if it's not null
    if (post.value.shelf_id !== null && post.value.shelf_id !== undefined) {
        formData.value.append('shelf_id', post.value.shelf_id)
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
    clear()
    buttonOptions.value = !buttonOptions.value
}

const selectButton = (val) => {
    buttonOptions.value = false
    blockType.value = val
}

const clear = () => {
    onEdit.value = false
    blockType.value = null
    postEdit.value = false
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

const handleSidebarUpdate = async (updatedPost) => {
    console.log('Handling sidebar update:', updatedPost)
    
    // Update local state immediately
    if (updatedPost) {
        post.value = { ...updatedPost }
        
        // If cards were reordered, update their order on the server
        if (updatedPost.cards) {
            await updatePostOrder()
            return // Return early as we've already handled the update
        }
    }
    
    // Handle other updates...
    formData.value = new FormData()
    formData.value.append('_method', 'PUT')
    
    // Add all post data
    Object.entries(post.value).forEach(([key, value]) => {
        if (value !== undefined) {
            if (key === 'cards') {
                formData.value.append('cards', JSON.stringify(value))
            } else if (value === null) {
                formData.value.append(key, '')
            } else {
                formData.value.append(key, value)
            }
        }
    })
    
    try {
        const res = await axios.post(
            `/communities/${props.community.slug}/${post.value.slug}/update`, 
            formData.value
        )
        
        // Update with server response
        post.value = res.data
        postBeforeEdit.value = { ...res.data }
        onUpdated()

        // Check if slug changed and redirect
        if (res.data.slug_changed) {
            setTimeout(() => {
                window.location.href = `/communities/${props.community.slug}/${res.data.slug}/edit`
            }, 1000) // Wait for "Updated" message to show
        }
    } catch (err) {
        console.error('Error updating post:', err)
        post.value = { ...postBeforeEdit.value }
        onErrors(err)
    }
}

const showAddButtonOptionsForCard = (card, position) => {
    const buttonId = `${card.id}-${position}`
    activeAddButton.value = activeAddButton.value === buttonId ? null : buttonId
}

const addBlockAfterCard = (type, card, position) => {
    const index = post.value.cards.findIndex(c => c.id === card.id)
    activeCardId.value = card.id
    blockType.value = type
    topPosition.value = index
    newCardPosition.value = position === 'top' ? index : index + 1
    activeAddButton.value = null
}

const handleNewCardUpdate = (updatedPost) => {
    if (updatedPost && updatedPost.cards && newCardPosition.value !== null) {
        const newCard = updatedPost.cards[updatedPost.cards.length - 1]
        updatedPost.cards.pop()
        
        // Insert at the correct position
        updatedPost.cards.splice(newCardPosition.value, 0, newCard)
        
        // Update order
        updatedPost.cards = updatedPost.cards.map((card, index) => ({
            ...card,
            order: index
        }))
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
</script>
