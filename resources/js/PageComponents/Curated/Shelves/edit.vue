<template>
    <div class="rounded-2xl mb-8 p-4 border">
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
            <div class="flex gap-4 mt-4">
                <button 
                    class="rounded-full border border-black py-2 px-4 bg-white hover:bg-black hover:text-white hover:border-black"
                    @click="resetShelf">Cancel</button>
                <button 
                    class="rounded-full text-white border border-black py-2 px-4 bg-black hover:bg-white hover:text-black hover:border-black"
                    @click="patchShelf">Save</button>
            </div>
        </template>
        <template v-else>
            <div 
                @mouseover="hover = true"
                @mouseleave="hover = false"
                @click="editName=true"
                class="inline-flex">
                <template v-if="shelf.name">
                    <h3>{{ shelf.name }}</h3>
                </template>
                <template v-else>
                    <h3>Edit Name</h3>
                </template>
                <template v-if="!editName && hover && shelf.status !== 'a'">
                    <button class="border-none underline p-0 block w-8 h-8 rounded-full justify-center items-center">
                        <svg class="h-8 w-8">
                            <use :xlink:href="`/storage/website-files/icons.svg#ri-pencil-line`" />
                        </svg>
                    </button>
                </template>
            </div>
        </template>
        <div 
            class="posts">
            <CollectionAlbum
                v-model="shelf"
                :community="community"
                :edit="true"
                :title="true"
                :draggable="true"
                :loadposts="posts" />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import CollectionAlbum from './Components/album.vue'

// Props
const props = defineProps({
    loadshelf: {
        type: Object,
        required: true
    },
    community: {
        type: Object,
        required: true
    },
})

// Emits
const emit = defineEmits(['updated'])

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
        const res = await axios.put(`/shelves/${shelf.value.id}`, shelf.value)
        shelf.value = res.data
        clear()
        emit('updated')
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
</script>

<style scoped>
/* Add any scoped styles here */
</style>