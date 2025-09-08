<template>
    <div class="rounded-2xl mb-8">
        <div class="">
            <!-- Shelf Header -->
            <div class="flex flex-col mb-4">
                <div class="flex items-center justify-between py-6">
                    <!-- Name Section -->
                    <div class="flex items-center gap-4">
                        <template v-if="editName && shelf.status !== 'a'">
                            <div class="field h3">
                                <input 
                                    type="text" 
                                    v-model="shelf.name"
                                    :class="{ 'error': v$.shelf.name.$error }"
                                    class="w-full border border-black rounded-lg p-2"
                                    placeholder="Shelf Name">
                                <div v-if="v$.shelf.name.$error" class="validation-error">
                                    <p class="error" v-if="!v$.shelf.name.required">Please add a name.</p>
                                    <p class="error" v-if="!v$.shelf.name.maxLength">The name is too long.</p>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div 
                                @mouseover="hover = true"
                                @mouseleave="hover = false"
                                @click="editName=true"
                                class="relative inline-flex items-center">
                                <template v-if="shelf.name">
                                    <h3>{{ shelf.name }}</h3>
                                </template>
                                <template v-else>
                                    <h3>Edit Name</h3>
                                </template>
                                <template v-if="!editName && hover && shelf.status !== 'a'">
                                    <button class="absolute -left-12  border-2 border-black underline p-0 bg-white flex w-12 h-12 rounded-full justify-center items-center">
                                        <svg class="h-8 w-8">
                                            <use :xlink:href="`/storage/website-files/icons.svg#ri-pencil-line`" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </template>
                        
                        <!-- Visibility Toggle -->
                        <button 
                            @click="toggleHidden"
                            class="p-2 rounded-full group transition-colors bg-gray-100 hover:bg-gray-200"
                            :title="shelf.is_hidden ? 'Show shelf' : 'Hide shelf'"
                        >
                            <svg class="w-8 h-8 fill-gray-700">
                                <use :xlink:href="shelf.is_hidden ? '/storage/website-files/icons.svg#ri-eye-off-line' : '/storage/website-files/icons.svg#ri-eye-line'" />
                            </svg>
                        </button>
                    </div>

                    <!-- Delete Button -->
                    <div v-if="!shelf.posts?.data?.length && shelf.name !== 'Archived'">
                        <button 
                            @click="$emit('delete', shelf)"
                            class="p-2 bg-black hover:bg-gray-100 rounded-full group">
                            <svg class="w-8 h-8 fill-white group-hover:fill-black">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                            </svg>
                        </button>
                    </div>

                    <!-- Create Post Button when there are posts -->
                    <div v-if="shelf.posts?.data?.length">
                        <a 
                            :href="`/communities/${community.slug}/posts/create?shelf=${shelf.id}`"
                            class="inline-flex items-center border border-neutral-300 rounded-full p-4 pr-6 gap-2 text-gray-600 hover:bg-black hover:text-white"
                        >
                            <div class="rounded-full w-8 h-8 flex items-center justify-center">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <span class="text-xl font-bold">Create Post</span>
                        </a>
                    </div>
                </div>

                <!-- Create Post Button when no posts -->
                <div v-if="!shelf.posts?.data?.length">
                    <a 
                        :href="`/communities/${community.slug}/posts/create?shelf=${shelf.id}`"
                        class="inline-flex items-center border border-neutral-300 rounded-full p-4 pr-6 gap-2 text-gray-600 hover:bg-black hover:text-white"
                    >
                        <div class="rounded-full w-8 h-8 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold">Create Post</span>
                    </a>
                </div>
            </div>

            <!-- Save/Cancel Buttons for Name Edit -->
            <div v-if="editName && shelf.status !== 'a'" class="flex gap-4 mb-4">
                <button 
                    class="rounded-full border border-black py-2 px-4 bg-white hover:bg-black hover:text-white hover:border-black"
                    @click="resetShelf">Cancel</button>
                <button 
                    class="rounded-full text-white border border-black py-2 px-4 bg-black hover:bg-white hover:text-black hover:border-black"
                    @click="patchShelf">Save</button>
            </div>

            <!-- Collection Album (Posts) -->
            <div class="posts">
                <CollectionAlbum
                    v-model="shelf"
                    :community="community"
                    :edit="true"
                    :title="true"
                    :draggable="true" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import CollectionAlbum from './Components/album.vue'

// Props
const props = defineProps({
    community: {
        type: Object,
        required: true
    },
    loadshelf: {
        type: Object,
        required: true
    },
    startEditing: {
        type: Boolean,
        default: false
    }
})

// Data
const shelf = ref(props.loadshelf)
const shelfBeforeEdit = ref({ ...props.loadshelf })
const posts = ref(props.loadshelf.posts || [])
const editName = ref(false)
const hover = ref(false)
const serverErrors = ref(null)

// Validation rules
const rules = {
    shelf: {
        name: { 
            required, 
            maxLength: maxLength(60) 
        }
    }
}

// Setup vuelidate
const v$ = useVuelidate(rules, { shelf })

// Methods
const patchShelf = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    try {
        const res = await axios.put(`/communities/${props.community.slug}/shelves/${shelf.value.id}`, shelf.value)
        shelf.value = res.data
        clear()
    } catch (err) {
        serverErrors.value = err.response?.data?.errors || { error: err.message }
    }
}

const resetShelf = () => {
    shelf.value = { ...shelfBeforeEdit.value }
    clear()
}

const updateShelf = (value) => {
    shelf.value = value
    posts.value = value.posts
}

const clear = () => {
    editName.value = false
    hover.value = false
}

const startEditing = () => {
    editName.value = true
}

const toggleHidden = async () => {
    try {
        const response = await axios.patch(`/communities/${props.community.slug}/shelves/${shelf.value.id}/toggle-hidden`)
        shelf.value.is_hidden = response.data.is_hidden
        
        // Optional: Show a toast notification
        console.log(response.data.message)
    } catch (error) {
        console.error('Error toggling shelf visibility:', error)
        // Handle error - maybe show a toast notification
    }
}

// Add emit
defineEmits(['delete'])

// Lifecycle hooks
onMounted(() => {
    if (props.startEditing) {
        startEditing()
    }
})
</script>

<style scoped>
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}
</style>