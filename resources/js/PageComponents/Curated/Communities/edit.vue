<template>
    <div v-if="loading">
        <p>Loading...</p>
    </div>
    <div v-else>
        <div class="m-auto w-full md:px-12 md:py-8 lg:py-0 lg:px-32 max-w-screen-xl">
            <div class="py-12">
                <p class="text-1xl">
                    <a class="underline" :href="`/`">Everything Immersive</a> > 
                    {{ community?.name || 'Loading...' }} Community
                </p>
            </div>
            <template v-if="community">
                <div class="relative overflow-hidden mb-8 rounded-2xl block h-full w-full md:flex md:h-[45rem]">
                    <div class="absolute right-20 bottom-20 z-10">
                        <a :href="`/communities/${community.slug || ''}`">
                            <button class="border-none bg-white rounded-2xl mt-16 py-6 px-8">
                                View Community
                            </button>
                        </a>
                    </div>
                    <div class="p-8 items-center justify-center flex bg-black text-center md:justify-start md:text-left md:w-5/12 md:h-[45rem] md:px-24">
                        <div class="w-full">
                            <div>
                                <h2 class="text-white text-6xl mb-4">{{ community.name || 'New Community' }}</h2>
                            </div>
                            <div class="field">
                                <textarea 
                                    type="text"
                                    v-model="community.blurb" 
                                    placeholder="Community description."
                                    :class="{ 'error': $v.community.blurb.$error }"
                                    @input="$v.community.blurb.$touch"
                                    rows="6" />
                                <div v-if="$v.community.blurb.$error" class="validation-error">
                                    <p class="error" v-if="!$v.community.blurb.required">Must provide a short description</p>
                                    <p class="error" v-if="!$v.community.blurb.maxLength">Description is too long</p>
                                </div>
                            </div>
                            <div 
                                v-if="$v.$anyDirty"
                                class="buttons">
                                <button 
                                    class="border-black bg-white rounded-2xl mt-16 py-4 px-6 hover:bg-black hover:text-white hover:border-white" 
                                    @click="patchCommunity">Save</button>
                                <button 
                                    class="border-black bg-white rounded-2xl mt-16 py-4 px-6 hover:bg-black hover:text-white hover:border-white" 
                                    @click="resetCommunity">Cancel</button>
                            </div>
                        </div>
                    </div>
                    <div class="relative inline-block bg-slate-400 md:h-[45rem] md:w-7/12">
                        <div 
                            @mouseover="overImage = true"
                            @mouseleave="overImage = false"
                            class="relative w-full h-full">
                            <label 
                                :class="{ '': overImage }"
                                class="cursor-pointer justify-center items-center flex w-full h-full bg-cover bg-center bg-no-repeat flex-col" 
                                :style="backgroundImage">  
                                <div v-if="!hasImage || overImage">
                                    <svg class="w-16 h-16 m-auto">
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-image-line`" />
                                    </svg>
                                    <p class="text-lg">Image should be at least 800 x 450</p>
                                </div>
                                <input
                                    type="file"
                                    class="hidden"
                                    accept="image/*"
                                    @change="onFileChange">
                            </label>
                            <div v-if="v$.imageFile.$error" class="absolute w-96 h-36 rounded-2xl inset-0 m-auto p-4 bg-white">
                                <p class="text-red-600" v-if="!v$.imageFile.fileSize">The image file size is over 10mb</p>
                                <p class="text-red-600" v-if="!v$.imageFile.fileType">The image needs to be a JPG, PNG or GIF</p>
                                <p class="text-red-600" v-if="!v$.imageFile.imageRatio">The image needs to be at least 800 x 450</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div class="m-auto w-full md:px-12 md:py-8 lg:py-0 lg:px-32 max-w-screen-xl">
            <div class="flex flex-col md:flex-row">
                <div class="w-full inline-block md:w-4/12 md:py-8 md:pr-8">
                    <div class="sticky top-16">
                        <div class="mb-8">
                            <Curators
                                @update="updateCurators"
                                :loadowner="owner"
                                :user="user"
                                :community="community"
                                :loadcurators="curators" />
                        </div>
                        <div class="p-8 rounded-2xl my-8 border">
                            <div class="field">
                                <textarea 
                                    type="text"
                                    v-model="community.description" 
                                    placeholder="Community description."
                                    :class="{ 'error': $v.community.description.$error }"
                                    @input="$v.community.description.$touch"
                                    rows="6" />
                                <div v-if="$v.community.description.$error" class="validation-error">
                                    <p class="error" v-if="!$v.community.description.maxLength">Description is too long</p>
                                </div>
                            </div>
                            <div 
                                v-if="$v.$anyDirty"
                                class="buttons">
                                <button 
                                    class="px-4 py-2 hover:bg-black hover:text-white" 
                                    @click="patchCommunity">Save</button>
                                <button 
                                    class="px-4 py-2 hover:bg-black hover:text-white"
                                    @click="resetCommunity">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="inline-block w-full md:w-8/12">
                    <div class="relative top-[-1rem]">
                        <div class="flex justify-end relative z-50">
                            <button 
                                @click="toggleButton"
                                ref="addButton"
                                class="border-none w-20 h-20 flex p-0 rounded-full justify-center items-center hover:bg-slate-300" 
                                :class="{'bg-black hover:bg-slate-300': onAdd}">
                                <svg 
                                    :class="{'fill-white rotate-45': onAdd}"
                                    class="w-16 h-16">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-add-fill`" />
                                </svg>
                                <template v-if="onAdd">
                                    <div class="p-4 rounded-2xl shadow-custom-1 absolute right-0 flex flex-col  bg-white min-w-[20rem] top-[115%]">
                                        <div class="mt-2 px-4 py-2">
                                            <p class="text-lg w-full text-left">Add New</p>
                                        </div>
                                        <button 
                                            class="w-full text-left border-none px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                            @click="addShelf">
                                            Shelf
                                        </button>
                                        <a 
                                            class="w-full text-left px-4 py-2 font-semibold text-3xl block rounded-xl hover:bg-gray-400 hover:text-white"
                                            :href="`/posts/${community.slug}/create`">
                                            Post
                                        </a>
                                    </div>
                                </template>
                            </button>
                        </div>
                    </div>
                    <draggable
                        v-model="shelves"
                        @start="isDragging=true" 
                        @end="debounce">
                        <div 
                            class="relative" 
                            v-for="(shelf, index) in shelves"
                            @mouseover="showDelete = index"
                            @mouseleave="showDelete = null"
                            :key="shelf.id">
                            <button 
                                v-if="showDelete === index && shelf.posts.data.length < 1 && shelf.name !== 'Archived'"
                                @click="deleteShelf(shelf)"
                                class="items-center justify-center rounded-full p-0 w-12 h-12 flex border-2 bg-white border-black absolute top-[-1rem] right-[-1rem] hover:bg-black hover:fill-white">
                                <svg class="w-12 h-12">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                                </svg>
                            </button>
                            <Shelf 
                                :archived="archived"
                                @updated="onUpdated"
                                :community="community"
                                :loadshelf="shelf" />
                        </div>
                    </draggable>
                </div>
            </div>
        </div>
        <transition name="slide-fade">
            <div 
                v-if="updated" 
                class="updated-notifcation">
                <p>Your changes have been saved.</p>
            </div>
        </transition>
        <div 
            v-if="serverErrors" 
            class="updated-notifcation">
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import Curators from './curators.vue'
import Shelf from '../Shelves/edit.vue'
import draggable from 'vuedraggable'

// Props
const props = defineProps({
    loadcommunity: {
        type: Object,
        required: true,
        default: () => ({
            name: '',
            slug: '',
            blurb: '',
            description: '',
            curators: []
        })
    },
    user: {
        type: Object,
        default: () => null
    },
    loadshelves: {
        type: Array,
        default: () => []
    },
    loadowner: {
        type: Object,
        default: () => null
    },
    mobile: {
        type: Boolean,
        default: false
    }
})

// Initialize with default values
const defaultCommunity = {
    name: '',
    slug: '',
    blurb: '',
    description: '',
    curators: [],
    thumbImagePath: null,
    largeImagePath: null
}

// Data refs with default values
const shelves = ref(props.loadshelves || [])
const community = ref({ ...defaultCommunity, ...props.loadcommunity })
const communityBeforeEdit = ref({ ...defaultCommunity, ...props.loadcommunity })
const headerImage = ref(null)
const loading = ref(true)

// Set headerImage based on props after initialization
if (props.loadcommunity) {
    headerImage.value = props.mobile 
        ? props.loadcommunity.thumbImagePath 
        : props.loadcommunity.largeImagePath
}

// Initialize all refs first
const active = ref(null)
const formData = ref(new FormData())
const serverErrors = ref(null)
const updated = ref(false)
const owner = ref(props.loadowner || null)
const curators = ref(props.loadcommunity?.curators?.filter(u => u.id !== props?.loadowner?.id) || [])
const onAdd = ref(false)
const showDelete = ref(null)
const timeout = ref(null)
const isDragging = ref(false)

// Image handling refs
const imageFile = ref('')
const overImage = ref(false)
const size = ref(10485760) // 10MB
const width = ref(800)
const height = ref(450)

// Modal state
const showModal = ref(false)
const modalTitle = ref('')
const modalMessage = ref('')
const modalCallback = ref(null)

// Validation rules after refs are initialized
const rules = {
    community: {
        blurb: { 
            required, 
            maxLength: maxLength(254) 
        },
        description: { 
            maxLength: maxLength(5000) 
        }
    },
    imageFile: {
        fileSize: (value) => !value || value.file?.size < size.value,
        fileType: (value) => !value || ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(value.file?.type),
        imageRatio: (value) => !value || (value.width >= width.value && value.height >= height.value)
    }
}

// Setup vuelidate after rules
const v$ = useVuelidate(rules, { community, imageFile })

// Computed properties
const archived = computed(() => {
    return props.loadshelves.find(shelf => shelf.status === 'a')
})

const hasImage = computed(() => 
    Boolean(headerImage.value || imageFile.value?.src)
)

const backgroundImage = computed(() => {
    if (!hasImage.value) return null
    if (imageFile.value?.src && !v$.value.imageFile.$error) {
        return `backgroundImage: url('${imageFile.value.src}')`
    }
    if (!headerImage.value) return null
    
    const imageUrl = import.meta.env.VITE_IMAGE_URL || ''
    return `backgroundImage: url('${imageUrl}${headerImage.value?.slice(0, -4)}jpg?timestamp=${new Date().getTime()}')`
})

// Methods
const patchCommunity = async () => {
    addCommunityData()
    try {
        await axios.post(`/communities/${community.value.slug}`, formData.value)
        onUpdated()
    } catch (err) {
        onErrors(err)
    }
}

const resetCommunity = () => {
    community.value = { ...communityBeforeEdit.value }
    v$.value.$reset()
}

const addShelf = async () => {
    try {
        const res = await axios.post(`/shelves/${community.value.slug}`)
        shelves.value = res.data
        clear()
    } catch (err) {
        console.error(err)
    }
}

const updateShelfOrder = async () => {
    const list = shelves.value.map((item, index) => {
        item.order = index
        return item
    })
    try {
        await axios.put(`/shelves/${community.value.slug}/order`, list)
        onUpdated()
    } catch (err) {
        console.error(err)
    }
}

const deleteShelf = async (shelf) => {
    if (shelf.name === 'Archived') {
        openModal('Cannot Delete', 'Cannot delete archived shelf')
        return
    }
    if (shelves.value.length <= 1) {
        openModal('Cannot Delete', 'Communities must have at least one shelf')
        return
    }
    if (shelf.posts.length) {
        openModal('Cannot Delete', 'Cannot delete shelf with posts')
        return
    }
    
    openModal(
        'Delete Shelf',
        'Are you sure you want to delete this shelf?',
        async () => {
            try {
                const res = await axios.delete(`/shelves/${shelf.id}`)
                shelves.value = res.data
                v$.value.$reset()
            } catch (err) {
                console.error(err)
            }
        }
    )
}

const toggleButton = () => {
    onAdd.value = !onAdd.value
}

const status = (post) => {
    return post.status === 'p' ? 'live' : 'draft'
}

const statusCircle = (post) => {
    if (post.status === 'p') return 'background: rgb(27, 187, 27)'
    if (post.status === 'd') return 'background: rgb(255 194 21)'
}

const addImage = (file) => {
    formData.value = new FormData() // Reset formData
    formData.value.append('image', file)
    patchCommunity()
}

const addCommunityData = () => {
    formData.value.append('_method', 'PUT')
    formData.value.append('name', community.value.name)
    formData.value.append('blurb', community.value.blurb)
    formData.value.append('description', community.value.description)
}

const onUpdated = () => {
    v$.value.$reset()
    updated.value = true
    setTimeout(() => updated.value = false, 3000)
}

const clearName = () => {
    v$.value.community.name.$touch()
    serverErrors.value = null
}

const clear = () => {
    onAdd.value = false
}

const updateCurators = (newOwner, newCurators) => {
    owner.value = newOwner || null
    curators.value = newCurators || []
}

const debounce = () => {
    if (timeout.value) clearTimeout(timeout.value)
    timeout.value = setTimeout(() => {
        updateShelfOrder()
    }, 500)
}

const onClickOutside = (event) => {
    const addButton = document.querySelector('[ref="addButton"]')
    if (!addButton || addButton.contains(event.target)) return
    onAdd.value = false
}

// Modal methods
const openModal = (title, message, callback = null) => {
    modalTitle.value = title
    modalMessage.value = message
    modalCallback.value = callback
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    modalCallback.value = null
}

const confirmModal = () => {
    if (modalCallback.value) {
        modalCallback.value()
    }
    closeModal()
}

// Add image handling methods
const onFileChange = async (event) => {
    const file = event.target.files[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = async (e) => {
        const img = new Image()
        img.src = e.target.result
        img.onload = async () => {
            imageFile.value = {
                file,
                src: e.target.result,
                width: img.width,
                height: img.height
            }

            await v$.value.$touch()
            if (v$.value.$invalid) return

            addImage(file)
        }
    }
    reader.readAsDataURL(file)
}

// Lifecycle hooks
onMounted(() => {
    loading.value = false
    setTimeout(() => document.addEventListener('click', onClickOutside), 200)
})

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside)
})
</script>

<style scoped>
.hidden {
    display: none;
}
</style>