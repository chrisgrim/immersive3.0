<template>
    <main class="w-full min-h-fit">
        <div class="max-w-screen-xl mx-auto">
            <div class="w-full lg:w-1/2 mx-auto pt-52 pb-40 px-4 md:px-0">
                <div class="flex flex-col w-full">

                    <!-- Form Content -->
                    <div>
                        <h2 class="text-4xl font-medium">Create a Post</h2>
                        <p class="text-gray-500 font-normal mt-4">Enter details for your new post</p>
                        <div class="mt-6">
                            <!-- Post Name Input -->
                            <textarea 
                                type="text" 
                                v-model="post.name"
                                @input="handleNameInput"
                                class="text-4xl font-normal border border-[#222222] rounded-2xl p-4 w-full mt-8"
                                :class="{ 
                                    'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': v$.post.name.$error && v$.post.name.$dirty,
                                    'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !v$.post.name.$error || !v$.post.name.$dirty 
                                }"
                                placeholder="Enter post name"
                                rows="2" />
                            
                            <!-- Name Character Count -->
                            <div class="flex justify-end mt-1" 
                                 :class="{'text-red-500': isNameNearLimit, 'text-gray-500': !isNameNearLimit}">
                                {{ post.name?.length || 0 }}/100
                            </div>

                            <!-- Name Error Messages -->
                            <div v-if="v$.post.name.$error && v$.post.name.$dirty" class="mt-[-2.5rem] mb-8 px-4">
                                <p class="text-red-500 text-1xl" v-if="!v$.post.name.required">Please add a name.</p>
                                <p class="text-red-500 text-1xl" v-if="!v$.post.name.maxLength">The name is too long.</p>
                                <p class="text-red-500 text-1xl" v-if="!v$.post.name.serverError">Your community already has a post with a similar name</p>
                            </div>

                            <!-- Tag Line Section -->
                            <div v-if="post.name">
                                <p class="text-gray-500 font-normal">Tag Line</p>
                                <textarea 
                                    type="text" 
                                    v-model="post.blurb"
                                    @input="handleBlurbInput"
                                    class="text-2xl border border-[#222222] rounded-2xl p-4 w-full mt-4"
                                    :class="{ 
                                        'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': v$.post.blurb.$error,
                                        'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !v$.post.blurb.$error 
                                    }"
                                    placeholder="Enter a catchy tagline"
                                    rows="2" />

                                <!-- Blurb Character Count -->
                                <div class="flex justify-end mt-1 relative" 
                                     :class="{'text-red-500': isBlurbNearLimit, 'text-gray-500': !isBlurbNearLimit}">
                                    {{ post.blurb?.length || 0 }}/100
                                    <div v-if="v$.post.blurb.$error" class="absolute left-0 top-0">
                                        <p class="text-red-500 text-1xl" v-if="!v$.post.blurb.required">Please add a tag line.</p>
                                        <p class="text-red-500 text-1xl" v-if="!v$.post.blurb.maxLength">The tag line is too long.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Shelf Selection -->
                            <div class="mt-8">
                                <p class="text-gray-500 font-normal">Select Shelf</p>
                                <div class="w-full relative mt-4" ref="shelfDrop" v-click-outside="handleClickOutside">
                                    <!-- Dropdown Arrow -->
                                    <svg 
                                        :class="{'transform rotate-90': state.dropdown}"
                                        class="w-10 h-10 fill-black absolute z-10 right-4 top-4 cursor-pointer" 
                                        @click="onDropdown"
                                    >
                                        <use :xlink:href="`/storage/website-files/icons.svg#ri-arrow-right-s-line`" />
                                    </svg>

                                    <!-- Shelf Input -->
                                    <input 
                                        :class="{ 'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': v$.post.shelf_id.$error }"
                                        class="text-2xl relative p-4 w-full border rounded-2xl focus:border-black focus:shadow-[0_0_0_1.5px_black]"
                                        v-model="state.shelfName"
                                        placeholder="Select a shelf"
                                        @focus="onDropdown"
                                        autocomplete="off"
                                        readonly
                                        type="text"
                                    >
                                    
                                    <!-- Error Message -->
                                    <div v-if="v$.post.shelf_id.$error" class="px-4 mt-2">
                                        <p class="text-red-500 text-1xl" v-if="!v$.post.shelf_id.required">Please select a shelf.</p>
                                    </div>

                                    <!-- Shelf Dropdown -->
                                    <ul v-if="state.dropdown" 
                                        class="overflow-auto bg-white w-full list-none rounded-2xl absolute top-16 m-0 z-10 border-[#e5e7eb] border max-h-[45rem]"
                                    >
                                        <li v-for="shelf in shelves"
                                            :key="shelf.id"
                                            class="py-4 px-6 hover:bg-gray-100 cursor-pointer" 
                                            @click="selectShelf(shelf)"
                                        >
                                            {{ shelf.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex w-full justify-between">
                                <a 
                                    :href="`/communities/${community.slug}/edit`"
                                    class="mt-8 rounded-2xl border border-black py-4 px-8 bg-white text-black hover:bg-black hover:text-white">
                                    Back
                                </a>
                                <button 
                                    class="mt-8 rounded-2xl py-4 px-8 bg-black text-white hover:bg-white hover:text-black border border-black"
                                    @click="submitPost">
                                    Create Post
                                </button>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Error Notifications -->
    <div v-if="serverErrors" class="updated-notifcation">
        <transition-group name="slide-fade">
            <ul 
                v-for="(error, index) in serverErrors"
                :key="`name${index}`">
                <li>
                    <p>{{ error.toString() }}</p>
                </li>
            </ul>
        </transition-group>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import { ClickOutsideDirective } from '@/Directives/ClickOutsideDirective.js'

// Props
const props = defineProps({
    community: {
        type: Object,
        required: true
    },
    shelves: {
        type: Array,
        required: true
    }
})

// Data
const post = ref(initializePostObject())
const serverErrors = ref(null)

// Validation rules
const rules = {
    post: {
        name: {
            required,
            maxLength: maxLength(100),
            serverError: () => !serverErrors.value?.name
        },
        blurb: {
            required,
            maxLength: maxLength(100)
        },
        shelf_id: {
            required
        }
    }
}

// Setup vuelidate
const v$ = useVuelidate(rules, { post }, { 
    $lazy: true,
    $autoDirty: false,
    $rewardEarly: false  // Add this to prevent early validation
})

// Methods
function initializePostObject() {
    return {
        name: '',
        blurb: '',
        community_id: props.community.id,
        shelf_id: null,
    }
}

async function submitPost() {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    try {
        const res = await axios.post(`/posts/${props.community.slug}/store`, post.value)
        window.location.href = `/communities/${props.community.slug}/${res.data.slug}/edit`
    } catch (err) {
        onErrors(err)
    }
}

function clearError() {
    v$.value.post.$touch()
    serverErrors.value = null
}

function onErrors(err) {
    if (err.response?.data?.errors) {
        serverErrors.value = err.response.data.errors
    } else {
        serverErrors.value = { error: err.message }
    }
}

// Add these computed properties
const isNameNearLimit = computed(() => {
    const count = post.value.name?.length || 0
    return count > 90
})

const isBlurbNearLimit = computed(() => {
    const count = post.value.blurb?.length || 0
    return count > 90
})

// Add these methods
function handleNameInput() {
    v$.value.post.name.$touch()
    if (post.value.name?.length > 100) {
        post.value.name = post.value.name.slice(0, 100)
    }
    clearError()
}

function handleBlurbInput() {
    v$.value.post.blurb.$touch()
    if (post.value.blurb?.length > 100) {
        post.value.blurb = post.value.blurb.slice(0, 100)
    }
    clearError()
}

// Add new refs for dropdown
const shelfDrop = ref(null)
const state = ref({
    shelfName: '',
    dropdown: false
})

// Add dropdown methods
const handleClickOutside = (event) => {
    const dropdownElement = shelfDrop.value
    if (dropdownElement && !dropdownElement.contains(event.target) && 
        !dropdownElement.contains(document.activeElement)) {
        state.value.dropdown = false
    }
}

const onDropdown = () => state.value.dropdown = true

const selectShelf = (shelf) => {
    post.value.shelf_id = shelf.id
    state.value.shelfName = shelf.name
    state.value.dropdown = false
}

// Add vClickOutside directive
const vClickOutside = ClickOutsideDirective

// Add function to handle URL params
const handleUrlParams = () => {
    const urlParams = new URLSearchParams(window.location.search)
    const shelfId = urlParams.get('shelf')
    
    if (shelfId) {
        const shelf = props.shelves.find(s => s.id === parseInt(shelfId))
        if (shelf) {
            selectShelf(shelf)
        }
    }
}

// Add to existing onMounted or create one
onMounted(() => {
    handleUrlParams()
})
</script>