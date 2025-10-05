<template>
    <div class="mt-8 relative border border-neutral-400 rounded-2xl p-6">
        <!-- Card Layout: Image Left, Content Right -->
        <div class="flex flex-col md:flex-row md:gap-16">
            <!-- Image Section - Left side on desktop -->
            <div class="relative flex gap-10 w-full md:w-[35%] mb-6 md:mb-0">
                <div class="w-1/5 md:w-full">
                    <!-- Image Upload Area -->
                    <label for="text-card-image-upload"
                        class="relative block aspect-[3/4] w-full rounded-2xl overflow-hidden cursor-pointer group">
                        
                        <!-- Current Image or Placeholder -->
                        <div v-if="imageFile?.src" class="w-full h-full">
                            <img 
                                :src="imageFile.src" 
                                class="w-full h-full object-cover" 
                                alt="Card image"
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
                                {{ imageFile?.src ? 'Change Image' : 'Upload Image' }}
                            </span>
                        </div>
                        
                        <input 
                            id="text-card-image-upload"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="onFileChange">
                    </label>
                    
                    <!-- Visibility Toggle Switch -->
                    <div class="absolute top-4 right-4 z-10">
                        <ToggleSwitch
                            v-model="isVisible"
                            left-label="Hidden"
                            right-label="Visible"
                            text-size="sm"
                            @update:modelValue="handleVisibilityChange" />
                    </div>
                </div>
                
                <!-- Mobile-only title -->
                <div class="w-4/5 flex items-center md:hidden">
                    <input 
                        type="text" 
                        v-model="card.name"
                        :class="{ 'border-red-500': v$.card.name.$error }"
                        class="border-gray-200 border p-4 rounded-2xl w-full text-4xl font-bold"
                        placeholder="Card Title (optional)">
                </div>
            </div>

            <!-- Content Section - Right side on desktop -->
            <div :class="[imageFile?.src && isVisible ? 'md:w-[65%]' : 'w-full', 'md:my-auto']">
                <!-- Toggle switch when image is hidden -->
                <div v-if="imageFile?.src && !isVisible" class="mb-4">
                    <ToggleSwitch
                        v-model="isVisible"
                        left-label="Hidden"
                        right-label="Visible"
                        text-size="sm"
                        @update:modelValue="handleVisibilityChange" />
                </div>
                
                <!-- Desktop-only title -->
                <div class="hidden md:block mb-4">
                    <input 
                        type="text" 
                        v-model="card.name"
                        :class="{ 'border-red-500': v$.card.name.$error }"
                        class="border-gray-200 border p-4 rounded-2xl w-full text-4xl font-bold"
                        placeholder="Card Title (optional)">
                    <div v-if="v$.card.name.$error" class="text-red-500 text-sm mt-1">
                        <p v-if="!v$.card.name.maxLength">The title is too long.</p>
                    </div>
                </div>

                <div class="md:mt-6 space-y-6">
                    <!-- URL Field -->
                    <div>
                        <input 
                            type="text" 
                            v-model="card.url"
                            :class="{ 'border-red-500': v$.card.url.$error }"
                            class="border-gray-200 border p-4 rounded-2xl w-full"
                            placeholder="URL (optional)">
                        <div v-if="v$.card.url.$error" class="text-red-500 text-sm mt-1">
                            <p v-if="!v$.card.url.maxLength">The URL is too long.</p>
                        </div>
                    </div>

                    <!-- Button Text Field -->
                    <div>
                        <input 
                            type="text" 
                            v-model="card.button_text"
                            :class="{ 'border-red-500': v$.card.button_text.$error }"
                            class="border-gray-200 border p-4 rounded-2xl w-full"
                            placeholder="Button text (If left blank, 'Read More' will be used)">
                        <div v-if="v$.card.button_text.$error" class="text-red-500 text-sm mt-1">
                            <p v-if="!v$.card.button_text.maxLength">The button text is too long.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Full-width TipTap Editor -->
        <div class="mt-8">
            <tiptap 
                @cancel="cancelCard"
                @save="saveCard"
                :class="{ 'border-red-500': v$.card.blurb.$error }"
                v-model="card.blurb" />
            <div v-if="v$.card.blurb.$error" class="text-red-500 text-sm mt-1">
                <p v-if="!v$.card.blurb.required">Please add a description.</p>
                <p v-if="!v$.card.blurb.maxLength">The description is too long.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import Tiptap from './Components/Tiptap.vue'
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue'

const props = defineProps({
    post: {
        type: Object,
        required: true
    },
    position: {
        type: Number,
        required: true
    },
    community: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['update', 'cancel'])

const card = ref({
    name: '',
    url: '',
    button_text: '',
    blurb: null,
    post_id: props.post.id,
    community_id: props.community.id,
    type: 't',
    order: props.position
})

const imageFile = ref(null)
const isVisible = ref(false) // Default to hidden when no image

// Validation rules
const rules = {
    card: {
        name: {
            maxLength: maxLength(255)
        },
        url: {
            maxLength: maxLength(500)
        },
        button_text: {
            maxLength: maxLength(50)
        },
        blurb: {
            required,
            maxLength: maxLength(40000)
        }
    }
}

const v$ = useVuelidate(rules, { card })

const onFileChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        const reader = new FileReader()
        reader.onload = (e) => {
            imageFile.value = {
                file: file,
                src: e.target.result
            }
            // Set visibility to true when image is added
            isVisible.value = true
        }
        reader.readAsDataURL(file)
    }
}

const handleVisibilityChange = (newValue) => {
    isVisible.value = newValue
    card.value.type = newValue ? 't' : 'h'
}

const saveCard = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return
    
    try {
        const formData = new FormData()
        formData.append('name', card.value.name || '')
        formData.append('url', card.value.url || '')
        formData.append('button_text', card.value.button_text || '')
        formData.append('blurb', card.value.blurb)
        formData.append('type', card.value.type)
        formData.append('order', card.value.order)
        formData.append('post_id', card.value.post_id)
        formData.append('community_id', props.community.id)
        formData.append('position', props.position)

        // Add image if one was uploaded
        if (imageFile.value?.file) {
            formData.append('image', imageFile.value.file)
        }

        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${props.post.slug}/cards`, 
            formData
        )
        emit('update', res.data)
    } catch (error) {
        console.error('Failed to save card:', error)
    }
}

const cancelCard = () => {
    emit('cancel')
}
</script>