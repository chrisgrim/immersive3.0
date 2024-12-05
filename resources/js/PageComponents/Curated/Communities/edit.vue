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
                            <div class="w-full mt-8">
                                <textarea 
                                    type="text"
                                    v-model="community.blurb" 
                                    placeholder="Community description."
                                    class="w-full rounded-2xl p-4"
                                    :class="{ 'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showBlurbError }"
                                    @input="handleBlurbInput"
                                    rows="6" />
                                <div class="flex justify-end mt-1 relative" 
                                     :class="{'text-red-500': isBlurbNearLimit, 'text-gray-500': !isBlurbNearLimit}">
                                    {{ community.blurb?.length || 0 }}/254
                                    <p v-if="showBlurbMaxLengthError" 
                                       class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                        Community description is too long.
                                    </p>
                                    <p v-if="showBlurbRequiredError" 
                                       class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                        Community description is required
                                    </p>
                                </div>
                            </div>
                            <div 
                                v-if="v$.community.blurb.$dirty"
                                class="buttons flex gap-4">
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
                                :loadowner="community.owner"
                                :user="user"
                                :community="community"
                                :loadcurators="curators" />
                        </div>
                        <div class="p-8 rounded-2xl my-8 border">
                            <div class="w-full">
                                <textarea 
                                    type="text"
                                    v-model="community.description" 
                                    placeholder="Community description."
                                    class="w-full rounded-2xl p-4"
                                    :class="{ 'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showDescriptionError }"
                                    @input="handleDescriptionInput"
                                    rows="6" />
                                <div class="flex justify-end mt-1 relative" 
                                     :class="{'text-red-500': isDescriptionNearLimit, 'text-gray-500': !isDescriptionNearLimit}">
                                    {{ community.description?.length || 0 }}/5000
                                    <p v-if="showDescriptionMaxLengthError" 
                                       class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                        Community description is too long.
                                    </p>
                                </div>
                            </div>
                            <div 
                                v-if="v$.community.description.$dirty"
                                class="buttons flex gap-4">
                                <button 
                                    class="px-4 py-2 border border-black bg-white rounded-2xl hover:bg-black hover:text-white" 
                                    @click="patchCommunity">Save</button>
                                <button 
                                    class="px-4 py-2 border border-black bg-white rounded-2xl hover:bg-black hover:text-white"
                                    @click="resetCommunity">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="inline-block w-full md:w-8/12">
                    <div class="relative top-[-1rem]">
                        <div class="flex justify-end relative z-50">
                            <button 
                                @click.stop="toggleButton"
                                ref="addButton"
                                class="border-none w-20 h-20 flex p-0 rounded-full justify-center items-center hover:bg-slate-300" 
                                :class="{'bg-black hover:bg-slate-300': onAdd}">
                                <svg 
                                    :class="{'fill-white rotate-45': onAdd}"
                                    class="w-16 h-16">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-add-fill`" />
                                </svg>
                                <div 
                                    v-if="onAdd"
                                    v-click-outside="closeMenu"
                                    class="p-4 rounded-2xl shadow-custom-1 absolute right-0 flex flex-col bg-white min-w-[20rem] top-[115%]">
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
                            </button>
                        </div>
                    </div>
                    <draggable
                        v-model="shelves"
                        :draggable="'.drag'"
                        @end="updateShelvesOrder"
                        item-key="id">
                        <template #item="{ element }">
                            <div class="drag">
                                <Shelf 
                                    :community="community"
                                    :loadshelf="element"
                                    @delete="deleteShelf" />
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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { required, maxLength } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import Curators from './curators.vue'
import Shelf from '../Shelves/edit.vue'
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective'
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
            curators: [],
            owner: null
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
const loading = ref(true)

// Initialize all refs first
const active = ref(null)
const formData = ref(new FormData())
const serverErrors = ref(null)
const updated = ref(false)
const curators = ref(props.loadcommunity?.curators?.filter(u => u.id !== props?.loadowner?.id) || [])
const onAdd = ref(false)
const showDelete = ref(null)

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

// Validation Rules
const rules = {
    community: {
        name: { required },
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

// Setup Vuelidate
const v$ = useVuelidate(rules, { community, imageFile })

// Computed properties
const archived = computed(() => {
    return props.loadshelves.find(shelf => shelf.status === 'a')
})

const hasImage = computed(() => 
    Boolean(
        imageFile.value?.src || 
        community.value.images?.length > 0 || 
        community.value.largeImagePath
    )
)

const backgroundImage = computed(() => {
    if (!hasImage.value) return null;
    if (imageFile.value?.src && !v$.value.imageFile.$error) {
        return `background-image: url('${imageFile.value.src}')`;
    }
    
    const imageUrl = import.meta.env.VITE_IMAGE_URL || '';
    // Check for new format first (images array)
    if (community.value.images?.length > 0) {
        return `background-image: url('${imageUrl}${community.value.images[0].large_image_path}')`;
    }
    // Fallback to old format
    if (community.value.largeImagePath) {
        return `background-image: url('${imageUrl}${community.value.largeImagePath}')`;
    }
    
    return null;
})

// Methods
const patchCommunity = async () => {
    console.log('Starting patchCommunity')
    
    // Only validate the community part
    const isValid = await v$.value.community.$validate()
    if (!isValid) {
        console.log('Validation errors detail:', {
            description: v$.value.community.description.$errors,
            allErrors: v$.value.community.$errors,
            dirty: v$.value.community.$dirty,
            invalid: v$.value.community.$invalid
        })
        return
    }

    try {
        // Use the class-level formData instead of creating a new one
        if (!formData.value) {
            formData.value = new FormData()
        }
        
        // Always include these basic fields
        formData.value.append('_method', 'PUT')
        formData.value.append('name', community.value.name)
        formData.value.append('blurb', community.value.blurb)
        formData.value.append('description', community.value.description)
        
        // If there's a new image file, include it
        if (imageFile.value?.file) {
            formData.value.append('image', imageFile.value.file)
        }
        
        console.log('FormData created:', Object.fromEntries(formData.value))
        
        const response = await axios.post(
            `/communities/${community.value.slug}`, 
            formData.value,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        )
        
        // Update the community with the response data
        community.value = response.data
        
        // Reset the image file after successful upload
        imageFile.value = null
        formData.value = new FormData()
        
        onUpdated()
    } catch (err) {
        console.error('Error in patchCommunity:', err)
        console.error('Error response:', err.response?.data)
        serverErrors.value = err.response?.data?.errors || ['An error occurred while saving']
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

const deleteShelf = async (shelf) => {
    if (shelves.value.length <= 1) {
        openModal('Cannot Delete', 'Communities must have at least one shelf')
        return
    }
    if (shelf.posts.data.length) {
        openModal('Cannot Delete', 'Cannot delete shelf with posts')
        return
    }
    try {
        await axios.delete(`/shelves/${shelf.id}`)
        // Remove the shelf from the local array
        shelves.value = shelves.value.filter(s => s.id !== shelf.id)
    } catch (err) {
        console.error('Failed to delete shelf:', err)
        alert('Failed to delete shelf')
    }
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
    community.value.owner = newOwner || null
    curators.value = newCurators || []
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

            // Call patchCommunity directly instead of addImage
            await patchCommunity()
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

// Computed Properties for Blurb
const showBlurbError = computed(() => {
    return v$.value.community.blurb.$dirty && v$.value.community.blurb.$error
})

const showBlurbMaxLengthError = computed(() => {
    return v$.value.community.blurb.$dirty && v$.value.community.blurb.maxLength.$invalid
})

const showBlurbRequiredError = computed(() => {
    return v$.value.community.blurb.$dirty && v$.value.community.blurb.required.$invalid
})

const isBlurbNearLimit = computed(() => {
    const count = community.value.blurb?.length || 0
    return count > 244
})

// Computed Properties for Description
const showDescriptionError = computed(() => {
    return v$.value.community.description.$dirty && v$.value.community.description.$error
})

const showDescriptionMaxLengthError = computed(() => {
    return v$.value.community.description.$dirty && v$.value.community.description.maxLength.$invalid
})

const isDescriptionNearLimit = ref(false)

const handleDescriptionInput = () => {
    v$.value.community.description.$touch()
    
    if (community.value.description?.length > 5000) {
        community.value.description = community.value.description.slice(0, 5000)
    }
    isDescriptionNearLimit.value = community.value.description?.length > 4750
}

// Methods
const handleBlurbInput = () => {
    v$.value.community.blurb.$touch()
    
    if (community.value.blurb?.length > 254) {
        community.value.blurb = community.value.blurb.slice(0, 254)
    }
}

// Add the directive to your component
const vClickOutside = ClickOutsideDirective

// Add this method
const closeMenu = () => {
    onAdd.value = false
}

// Add this method
const updateShelvesOrder = async () => {
    try {
        const orderedShelves = shelves.value.map((shelf, index) => ({
            id: shelf.id,
            order: index
        }))
        
        await axios.put(`/shelves/${community.value.slug}/order`, orderedShelves)
    } catch (error) {
        console.error('Failed to update shelf order:', error)
    }
}
</script>
<style scoped>
.hidden {
    display: none;
}

.drag {
    cursor: move;
}
</style>
