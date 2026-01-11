<template>
    <div class="relative">
        <div 
            :class="{ 'cursor-pointer border-2 !border-black': hover && !onEdit }"
            @mouseover="hover = true"
            @mouseleave="hover = false"
            class="block rounded-2xl border-transparent border-2 p-4">
            
            <!-- Event Image -->
            <template v-if="card.event_id">
                <div class="event-card border-t md:border border-neutral-400 md:rounded-2xl py-12 md:mb-16 md:p-12 overflow-hidden">
                    <div class="flex flex-col md:flex-row md:gap-16">
                        <!-- Event Image and Mobile Title -->
                        <template v-if="hasImage && isVisible">
                            <div class="flex gap-10 w-full md:w-[35%] mb-6 md:mb-0">
                                <div class="w-1/5 md:w-full">
                                    <div class="aspect-[3/4] w-full rounded-2xl overflow-hidden relative group">
                                        <picture>
                                            <source 
                                                v-if="!imageFile?.src"
                                                type="image/webp" 
                                                :srcset="`${imageUrl}${hasImage}`" /> 
                                            <img 
                                                loading="lazy"
                                                class="w-full h-full object-cover"
                                                :src="imageFile?.src || `${imageUrl}${hasImage}`"
                                                :alt="card.event?.name">
                                        </picture>
                                        
                                        <!-- Clickable overlay to enter edit mode when NOT editing -->
                                        <div v-if="!onEdit" @click="onEdit = true" class="absolute inset-0 cursor-pointer"></div>
                                        
                                        <!-- Clickable overlay for image upload when in edit mode -->
                                        <label v-if="onEdit" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                            <div class="text-white text-center pointer-events-none">
                                                <svg class="w-8 h-8 mx-auto mb-2">
                                                    <use xlink:href="/storage/website-files/icons.svg#ri-camera-line" />
                                                </svg>
                                                <p class="text-sm">Click to change</p>
                                            </div>
                                            <!-- Hidden file input -->
                                            <input
                                                type="file"
                                                class="hidden"
                                                accept="image/jpeg,image/png,image/webp"
                                                @change="onFileChange">
                                        </label>
                                        
                                        <!-- Toggle switch - separate from image upload -->
                                        <div v-if="onEdit && isVisible" class="absolute top-4 right-4 z-10">
                                            <ToggleSwitch
                                                v-model="isVisible"
                                                left-label="Hidden"
                                                right-label="Visible"
                                                text-size="sm" />
                                        </div>
                                    </div>
                                </div>
                                <!-- Mobile-only title -->
                                <div class="w-4/5 flex items-center md:hidden">
                                    <div @click="onEdit = true">
                                        <h3 class="text-4xl font-bold mt-0">{{ hasName }}</h3>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Event Content - Right side on desktop -->
                        <div :class="[hasImage && isVisible ? 'md:w-[65%]' : 'w-full', 'md:my-auto']">
                            <div v-if="hasImage && !isVisible && onEdit" class="mb-4">
                                <ToggleSwitch
                                    v-model="isVisible"
                                    left-label="Hidden"
                                    right-label="Visible"
                                    text-size="sm"
 />
                            </div>
                            
                            <template v-if="onEdit">
                                <div class="field border border-gray-300 rounded-md p-4 mb-4">
                                    <input 
                                        type="text" 
                                        v-model="card.name"
                                        class="w-full"
                                        :class="{ 'border-red-500': v$.card.name.$error }"
                                        :placeholder="hasName">
                                    <div v-if="v$.card.name.$error" class="text-red-500 text-sm mt-1">
                                        <p v-if="!v$.card.name.maxLength">The name is too long.</p>
                                    </div>
                                </div>
                                <div class="field border border-gray-300 rounded-md p-4 mb-4">
                                    <input 
                                        type="text" 
                                        v-model="card.url"
                                        class="w-full"
                                        :class="{ 'border-red-500': v$.card.url.$error }"
                                        :placeholder="hasUrl">
                                    <div v-if="v$.card.url.$error" class="text-red-500 text-sm mt-1">
                                        <p v-if="!v$.card.url.maxLength">The url is too long.</p>
                                    </div>
                                </div>
                                <div class="field border border-gray-300 rounded-md p-4 mb-4">
                                    <input 
                                        type="text" 
                                        v-model="card.button_text"
                                        class="w-full"
                                        :class="{ 'border-red-500': v$.card.button_text.$error }"
                                        placeholder="Button text (If left blank, 'Read More' will be used)">
                                    <div v-if="v$.card.button_text.$error" class="text-red-500 text-sm mt-1">
                                        <p v-if="!v$.card.button_text.maxLength">The button text is too long.</p>
                                    </div>
                                </div>
                                <div class="flex gap-4 mb-4">
                                    <a target="_blank" :href="`/hosting/event/${card.event.slug}/edit?dates`">
                                        <button class="px-4 py-2 font-medium border border-black rounded-full text-xl hover:bg-white hover:text-black">
                                            Edit event
                                        </button>
                                    </a>
                                    <a target="_blank" :href="card.event.ticketUrl">
                                        <button class="px-4 py-2 font-medium border border-black rounded-full text-xl hover:bg-white hover:text-black">
                                            Check Event
                                        </button>
                                    </a>
                                </div>
                                
                                <div v-if="card.event">
                                    <p class="mb-4">Booking Through: {{ cleanDate(card.event?.closingDate) }}</p>
                                </div>
                            </template>
                            
                            <template v-else>
                                <!-- Desktop-only title -->
                                <div @click="onEdit = true" class="hidden md:block">
                                    <h3 class="text-4xl font-bold mt-0">{{ hasName }}</h3>
                                </div>

                                <div @click="onEdit = true" class="mt-6 space-y-6">
                                    <!-- Blurb -->
                                    <div v-if="card.blurb" class="card-blurb">
                                        <div v-html="card.blurb.split(' ').slice(0, 40).join(' ') + (card.blurb.split(' ').length > 40 ? '...' : '')" />
                                    </div>

                                    <!-- Event Dates -->
                                    <p v-if="card.event" class="text-gray-600 text-xl">
                                        Booking Through: {{ cleanDate(card.event?.closingDate) }}
                                    </p>

                                    <!-- Read More Button -->
                                    <div>
                                        <a 
                                            :href="hasUrl"
                                            :target="urlSecurity.target"
                                            :rel="urlSecurity.rel"
                                            class="inline-block bg-black text-white px-8 py-4 rounded-2xl hover:bg-gray-800 transition-colors">
                                            {{ !card.button_text || card.button_text.trim() === '' ? 'Read More' : card.button_text }}
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    
                    <!-- Full-width TipTap Editor -->
                    <template v-if="onEdit">
                        <tiptap 
                            v-model="card.blurb"
                            @cancel="resetCard"
                            @save="updateCard"
                            :disabled="disabled"
                            :class="{ 'border-red-500': v$.card.blurb.$error }" />
                        <div v-if="v$.card.blurb.$error" class="text-red-500 text-sm mt-1">
                            <p v-if="!v$.card.blurb.atLeastOneRequired">Please add a title, description, or image.</p>
                            <p v-if="!v$.card.blurb.maxLength">The description is too long.</p>
                        </div>
                    </template>
                </div>

            </template>
            
            <template v-else-if="card.type === 'i'">
                <!-- Image Card - Full Width -->
                <div v-if="(hasImage && isVisible) || onEdit" class="relative aspect-[16/9] mb-8">
                    <label v-if="onEdit" 
                        for="image-card-upload"
                        class="relative block w-full h-full rounded-2xl overflow-hidden cursor-pointer group">
                        
                        <!-- Current Image or Placeholder -->
                        <div v-if="hasImage" class="w-full h-full">
                            <img 
                                :src="hasImage" 
                                class="w-full h-full object-cover" 
                                :alt="card.name || 'Card image'"
                            />
                        </div>
                        <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        
                        <!-- Upload Overlay -->
                        <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                            <span class="text-white text-lg font-medium">
                                {{ hasImage ? 'Change Image' : 'Upload Image' }}
                            </span>
                        </div>
                        
                        <input 
                            id="image-card-upload"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/webp"
                            @change="onFileChange">
                    </label>
                    
                    <!-- Display Only Mode -->
                    <div v-else-if="hasImage && isVisible" @click="onEdit = true" class="w-full h-full rounded-2xl overflow-hidden cursor-pointer">
                        <img 
                            :src="hasImage" 
                            class="w-full h-full object-cover" 
                            :alt="card.name || 'Card image'"
                        />
                    </div>
                    
                    <!-- Toggle switch for image cards -->
                    <div v-if="onEdit && (hasImage || isVisible)" class="absolute top-4 right-4 z-10">
                        <ToggleSwitch
                            v-model="isVisible"
                            left-label="Hidden"
                            right-label="Visible"
                            text-size="sm" />
                    </div>
                </div>
                
                <!-- Toggle switch when image is hidden -->
                <div v-if="!isVisible && onEdit" class="mb-4">
                    <ToggleSwitch
                        v-model="isVisible"
                        left-label="Hidden"
                        right-label="Visible"
                        text-size="sm" />
                </div>
                
            </template>
            
            <template v-else>
                <!-- Text Card with Event-like Layout -->
                <div class="flex flex-col md:flex-row md:gap-16 mb-8">
                    <!-- Image Section - Left side on desktop -->
                    <div v-if="(hasImage && isVisible) || onEdit" class="relative flex gap-10 w-full md:w-[35%] mb-6 md:mb-0">
                        <div class="w-1/5 md:w-full">
                            <!-- Image Upload Area -->
                            <label v-if="onEdit" 
                                for="regular-image-upload"
                                class="relative block aspect-[3/4] w-full rounded-2xl overflow-hidden cursor-pointer group">
                                
                                <!-- Current Image or Placeholder -->
                                <div v-if="hasImage" class="w-full h-full">
                                    <img 
                                        :src="hasImage" 
                                        class="w-full h-full object-cover" 
                                        :alt="card.name || 'Card image'"
                                    />
                                </div>
                                <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </div>
                                
                                <!-- Upload Overlay -->
                                <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                    <span class="text-white text-sm font-medium">
                                        {{ hasImage ? 'Change Image' : 'Upload Image' }}
                                    </span>
                                </div>
                                
                                <input 
                                    id="regular-image-upload"
                                    type="file"
                                    class="hidden"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="onFileChange">
                            </label>
                            
                            <!-- Display Only Mode -->
                            <div v-else-if="hasImage && isVisible" @click="onEdit = true" class="aspect-[3/4] w-full rounded-2xl overflow-hidden cursor-pointer">
                                <img 
                                    :src="hasImage" 
                                    class="w-full h-full object-cover" 
                                    :alt="card.name || 'Card image'"
                                />
                            </div>
                            
                            <!-- Toggle switch - separate from image upload -->
                            <div v-if="onEdit && (hasImage || isVisible)" class="absolute top-4 right-4 z-10">
                                <ToggleSwitch
                                    v-model="isVisible"
                                    left-label="Hidden"
                                    right-label="Visible"
                                    text-size="sm"
 />
                            </div>
                        </div>
                        
                        <!-- Mobile-only title -->
                        <div class="w-4/5 flex items-center md:hidden">
                            <div v-if="onEdit">
                                <input 
                                    type="text" 
                                    v-model="card.name"
                                    :class="{ 'border-red-500': v$.card.name.$error }"
                                    class="border-gray-200 border p-4 rounded-2xl w-full text-4xl font-bold"
                                    placeholder="Card Title">
                            </div>
                            <div v-else-if="card.name && card.name.trim()" @click="onEdit = true" class="cursor-pointer">
                                <h3 class="text-4xl font-bold mt-0">{{ card.name }}</h3>
                            </div>
                        </div>
                    </div>

                    <!-- Content Section - Right side on desktop -->
                    <div :class="[((hasImage && isVisible) || onEdit) ? 'md:w-[65%]' : 'w-full', (hasImage && isVisible) ? 'md:my-auto' : '']">
                        <!-- Toggle switch when image is hidden -->
                        <div v-if="!isVisible && onEdit" class="mb-4">
                            <ToggleSwitch
                                v-model="isVisible"
                                left-label="Hidden"
                                right-label="Visible"
                                text-size="sm" />
                        </div>
                        
                        <!-- Desktop-only title -->
                        <div class="hidden md:block">
                            <div v-if="onEdit">
                                <input 
                                    type="text" 
                                    v-model="card.name"
                                    :class="{ 'border-red-500': v$.card.name.$error }"
                                    class="border-gray-200 border p-4 rounded-2xl w-full mb-4 text-4xl font-bold"
                                    placeholder="Card Title (Optional)">
                                <div v-if="v$.card.name.$error" class="text-red-500 text-sm mt-1">
                                    <p v-if="!v$.card.name.maxLength">The name is too long.</p>
                                </div>
                            </div>
                            <div v-else-if="card.name && card.name.trim()" @click="onEdit = true" class="cursor-pointer">
                                <h3 class="text-4xl font-bold mt-0 mb-6">{{ card.name }}</h3>
                            </div>
                        </div>

                        <div class="md:mt-6 space-y-6">
                            <!-- URL and Button Text Fields -->
                            <div v-if="onEdit">
                                <input 
                                    type="text" 
                                    v-model="card.url"
                                    :class="{ 'border-red-500': v$.card.url.$error }"
                                    class="border-gray-200 border p-4 rounded-2xl w-full mb-4"
                                    placeholder="URL (optional)">
                                <div v-if="v$.card.url.$error" class="text-red-500 text-sm mt-1">
                                    <p v-if="!v$.card.url.maxLength">The url is too long.</p>
                                </div>
                                
                                <input 
                                    type="text" 
                                    v-model="card.button_text"
                                    :class="{ 'border-red-500': v$.card.button_text.$error }"
                                    class="border-gray-200 border p-4 rounded-2xl w-full mb-4"
                                    placeholder="Button text (If left blank, 'Read More' will be used)">
                                <div v-if="v$.card.button_text.$error" class="text-red-500 text-sm mt-1">
                                    <p v-if="!v$.card.button_text.maxLength">The button text is too long.</p>
                                </div>
                            </div>

                            <!-- Blurb Display Only -->
                            <div v-if="!onEdit">
                                <div 
                                    @click="onEdit = true"
                                    class="card-blurb text-lg leading-relaxed cursor-pointer">
                                    <div v-html="card.blurb" />
                                </div>
                            </div>

                            <!-- Button (when URL exists) -->
                            <div v-if="card.url && !onEdit">
                                <a 
                                    :href="card.url"
                                    :target="textCardUrlSecurity.target"
                                    :rel="textCardUrlSecurity.rel"
                                    class="inline-block bg-black text-white px-8 py-4 rounded-2xl hover:bg-gray-800 transition-colors">
                                    {{ !card.button_text || card.button_text.trim() === '' ? 'Read More' : card.button_text }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Full-width TipTap Editor -->
                <template v-if="onEdit">
                    <tiptap 
                        v-model="card.blurb"
                        @cancel="resetCard"
                        @save="updateCard"
                        :disabled="disabled"
                        :class="{ 'border-red-500': v$.card.blurb.$error }" />
                    <div v-if="v$.card.blurb.$error" class="text-red-500 text-sm mt-1">
                        <p v-if="!v$.card.blurb.atLeastOneRequired">Please add a title, description, or image.</p>
                        <p v-if="!v$.card.blurb.maxLength">The description is too long.</p>
                    </div>
                </template>
            </template>
            <template v-if="hover && !onEdit">
                <div class="absolute top-[-1rem] right-[-1rem]">
                    <button 
                        @click="deleteCard"
                        class="w-12 h-12 flex rounded-full justify-center items-center border-2 bg-white hover:bg-black group">
                        <svg class="w-12 h-12 fill-black hover:fill-white">
                            <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                        </svg>
                    </button>
                </div>
            </template>
            
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { maxLength } from '@vuelidate/validators'
import moment from 'moment'
import Tiptap from './Components/Tiptap.vue'
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue'
import { useSecureUrl } from '@/composables/useSecureUrl'

const props = defineProps({
    parentCard: {
        type: Object,
        required: true
    },
    owner: {
        type: Boolean,
        default: false
    },
    community: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['update', 'edit-mode-change'])

const imageUrl = import.meta.env.VITE_IMAGE_URL

// State
const card = ref({ ...props.parentCard })
const cardBeforeEdit = ref({ ...props.parentCard })
const onEdit = ref(false)
const disabled = ref(false)
const formData = ref(new FormData())
const hover = ref(false)
const imageFile = ref(null)

// Watch for edit mode changes and emit to parent
watch(onEdit, (newValue) => {
    emit('edit-mode-change', newValue)
})

// Computed
const hasImage = computed(() => {
    if (!card.value) return null

    // If there's a custom uploaded image, use that first
    if (imageFile.value?.src) {
        return imageFile.value.src
    }

    // For event cards
    if (card.value.event_id) {
        // Check card's own images first (uploaded overrides)
        if (card.value.images?.length > 0) {
            const imagePath = card.value.images[0].large_image_path || card.value.images[0].thumbImagePath
            return imagePath ? imageUrl + imagePath : null
        }
        // Then check event images
        if (card.value.event?.images?.length > 0) {
            const imagePath = card.value.event.images[0].largeImagePath || card.value.event.images[0].thumbImagePath
            return imagePath ? imageUrl + imagePath : null
        }
        // Fallback to event direct images
        const imagePath = card.value.event?.largeImagePath || card.value.event?.thumbImagePath
        return imagePath ? imageUrl + imagePath : null
    }
    
    // For regular cards (both type 'i' and type 't')
    if (!card.value.event_id) {
        // Check card images first
        if (card.value.images?.length > 0) {
            const imagePath = card.value.images[0].large_image_path || card.value.images[0].thumbImagePath
            return imagePath ? imageUrl + imagePath : null
        }
        // Fallback to direct image path properties
        const imagePath = card.value.largeImagePath || card.value.thumbImagePath
        return imagePath ? imageUrl + imagePath : null
    }

    return null
})

const hasName = computed(() => 
    card.value?.name || card.value?.event?.name || ''
)

const hasUrl = computed(() => {
    if (card.value?.event && !card.value?.url) {
        return `/events/${card.value.event.slug}`
    }
    return card.value?.url || ''
})

const urlSecurity = computed(() => useSecureUrl(hasUrl.value))

const textCardUrlSecurity = computed(() => useSecureUrl(card.value?.url))

// Custom validator: At least one of name, blurb, or image must be present
const atLeastOneRequired = (value) => {
    const hasNameValue = card.value?.name && card.value.name.trim().length > 0
    const hasBlurbValue = value && value.trim().length > 0
    const hasImageValue = hasImage.value || imageFile.value?.file
    return hasNameValue || hasBlurbValue || hasImageValue
}

// Validation rules
const rules = {
    card: {
        name: { maxLength: maxLength(255) },
        blurb: {
            atLeastOneRequired,
            maxLength: maxLength(40000)
        },
        url: { maxLength: maxLength(255) },
        button_text: { maxLength: maxLength(50) }
    }
}

const v$ = useVuelidate(rules, { card })

// Methods
const updateCard = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    appendCardData()
    try {
        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${props.parentCard.post.slug}/cards/${card.value.id}`, 
            formData.value
        )
        card.value = res.data
        cardBeforeEdit.value = { ...res.data }
        clear()
    } catch (error) {
        console.error('Failed to update card:', error)
        
        // Handle validation errors
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const errors = error.response.data.errors;
            
            // Build user-friendly error message
            let errorMessages = [];
            if (errors.image) {
                errorMessages.push('Invalid image file. Please upload a JPEG, PNG, or WebP image.');
                // Clear the invalid image preview
                imageFile.value = null;
            }
            for (const [field, messages] of Object.entries(errors)) {
                if (field !== 'image') {
                    errorMessages.push(...messages);
                }
            }
            
            if (errorMessages.length > 0) {
                alert(errorMessages.join('\n'));
            }
        } else {
            alert('Failed to update card. Please try again.');
        }
    }
}

const resetCard = () => {
    card.value = { ...cardBeforeEdit.value }
    clear()
}

const deleteCard = async () => {
    if (!props.parentCard?.post?.slug) {
        console.error('Post slug is missing from parentCard:', props.parentCard)
        return
    }

    const url = `/communities/${props.community.slug}/posts/${props.parentCard.post.slug}/cards/${card.value.id}`
    console.log('Attempting to delete card:', {
        url,
        community: props.community.slug,
        post: props.parentCard.post.slug,
        cardId: card.value.id
    })

    try {
        const res = await axios.delete(url)
        console.log('Delete response:', res.data)
        emit('update', res.data)
    } catch (error) {
        // If we get a 404, the card might have already been deleted
        if (error.response?.status === 404) {
            console.warn('Card not found - might have been already deleted')
            emit('update')
            return
        }

        console.error('Failed to delete card:', {
            error,
            response: error.response?.data,
            status: error.response?.status,
            url,
            message: error.response?.data?.message
        })
    }
}

const appendCardData = () => {
    formData.value = new FormData()
    formData.value.append('name', card.value.name || '')
    formData.value.append('url', card.value.url || '')
    formData.value.append('button_text', card.value.button_text || '')
    if (card.value.blurb) formData.value.append('blurb', card.value.blurb)
    if (imageFile.value?.file) formData.value.append('image', imageFile.value.file)
    formData.value.append('type', card.value.type)
    formData.value.append('community_id', props.community.id)
    formData.value.append('post_id', props.parentCard.post.id)
}

const clear = () => {
    console.log('onEdit', onEdit.value)
    onEdit.value = false
    console.log('onEdit', onEdit.value)
    hover.value = false
    disabled.value = false
    formData.value = new FormData()
}

const cleanDate = (date) => {
    return moment(date).format("dddd, MMMM D YYYY")
}

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

const onFileChange = async (event) => {
    const file = event.target.files[0]
    if (!file) return

    // Validate file type
    if (!ALLOWED_TYPES.includes(file.type)) {
        alert('Please upload a valid image file (JPEG, PNG, or WebP).');
        event.target.value = '';
        return;
    }

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
            // Auto-save after image upload
            await updateCard()
        }
    }
    reader.readAsDataURL(file)
}

// Add isVisible computed
const isVisible = computed({
    get: () => card.value.type !== 'h',
    set: (value) => {
        // Use the same logic as handleVisibilityChange
        if (card.value.event_id) {
            // Event cards: 'e' when visible, 'h' when hidden
            card.value.type = value ? 'e' : 'h'
        } else {
            // Text cards: 't' when visible, 'h' when hidden  
            card.value.type = value ? 't' : 'h'
        }
    }
})



// Add this function to strip HTML tags
const stripHtml = (html) => {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
};
</script>

<style scoped>
.event-card .card-blurb :deep(p) {
    @apply text-base md:text-lg lg:text-3xl leading-tight;
}

.card-blurb :deep(p) {
    @apply text-base md:text-lg lg:text-3xl leading-relaxed;
}
</style>