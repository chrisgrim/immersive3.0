<template>
    <div class="relative">
        <div 
            :class="{ 'cursor-pointer border !border-black': hover && !onEdit }"
            @mouseover="hover = true"
            @mouseleave="hover = false"
            class="block rounded-2xl border-transparent border-2 p-4">
            
            <!-- Event Image -->
            <template v-if="card.event_id">
                <div class="event-card border rounded-2xl p-12 overflow-hidden">
                    <div class="flex flex-col md:flex-row gap-8 items-start">
                        <!-- Event Image -->
                        <div 
                            @click="onEdit = true"
                            v-if="hasImage" 
                            class="md:w-[45%] overflow-hidden rounded-2xl">
                            <div class="aspect-[3/4] w-full relative">
                                <picture v-if="isVisible">
                                    <source 
                                        type="image/webp" 
                                        :srcset="`${imageUrl}${hasImage}`" /> 
                                    <img 
                                        loading="lazy"
                                        class="w-full h-full object-cover"
                                        :src="`${imageUrl}${hasImage}`"
                                        :alt="card.event?.name">
                                </picture>
                                <div class="absolute top-4 right-4">
                                    <ToggleSwitch
                                        v-model="isVisible"
                                        left-label="Hidden"
                                        right-label="Visible"
                                        text-size="sm"
                                        @update:modelValue="handleVisibilityChange" />
                                </div>
                            </div>
                        </div>

                        <!-- Event Content -->

                        <div v-if="onEdit">
                            <div class="field h3">
                                <input 
                                    type="text" 
                                    v-model="card.name"
                                    :class="{ 'border-red-500': v$.card.name.$error }"
                                    class="border-gray-200 border p-4 rounded-2xl w-full mb-4"
                                    :placeholder="hasName">
                                <div v-if="v$.card.name.$error" class="text-red-500 text-sm mt-1">
                                    <p v-if="!v$.card.name.maxLength">The name is too long.</p>
                                </div>
                            </div>
                            <div>
                                <input 
                                    type="text" 
                                    v-model="card.url"
                                    :class="{ 'border-red-500': v$.card.url.$error }"
                                    class="border-gray-200 border p-4 rounded-2xl w-full mb-4"
                                    :placeholder="hasUrl">
                                <div v-if="v$.card.url.$error" class="text-red-500 text-sm mt-1">
                                    <p v-if="!v$.card.url.maxLength">The url is too long.</p>
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
                            
                            <tiptap 
                                v-model="card.blurb"
                                @cancel="resetCard"
                                @save="updateCard"
                                :disabled="disabled"
                                :class="{ 'border-red-500': v$.card.blurb.$error }" />
                            <div v-if="v$.card.blurb.$error" class="text-red-500 text-sm mt-1">
                                <p v-if="!v$.card.blurb.required">Please add a description.</p>
                                <p v-if="!v$.card.blurb.maxLength">The description is too long.</p>
                            </div>
                            
                        </div>
                        <div 
                            @click="onEdit = true"
                            v-else 
                            class="space-y-6 md:w-[65%]">

                            <h3 class="text-4xl font-bold mt-0">{{ card.event?.name }}</h3>
                            <!-- Event Dates -->

                            <div class="card-blurb text-2xl leading-tight">
                                <div v-html="card.blurb" />
                            </div>
                            
                            <p class="text-gray-600 text-xl">
                                Booking Through: {{ cleanDate(card.event?.closingDate) }}
                            </p>

                            <!-- Event Blurb -->
                            <p class="text-gray-800">{{ card.event?.blurb }}</p>
                            
                        </div>

                        
                    </div>
                </div>

                <template v-if="hover && !onEdit">
                    <div class="absolute top-[-1rem] right-[-1rem]">
                        <button 
                            @click="deleteCard"
                            class="w-12 h-12 flex rounded-full justify-center items-center border-2 bg-white hover:bg-black hover:text-white">
                            <svg class="w-12 h-12">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                            </svg>
                        </button>
                    </div>
                </template>
            </template>
            
            <template v-else>
                <!-- Regular Image -->
                <template v-if="!card.event_id && card.type === 'i'">
                    <div 
                        :class="['relative rounded-2xl overflow-hidden mb-8 cursor-pointer', isVisible ? 'aspect-[16/9]' : 'h-16']" 
                        @click="onEdit = true">
                        <picture v-if="isVisible && hasImage">
                            <source 
                                type="image/webp" 
                                :srcset="imageUrl + hasImage" /> 
                            <img 
                                loading="lazy"
                                class="w-full rounded-2xl align-bottom object-cover h-full"
                                :src="imageUrl + hasImage"
                                :alt="card.name || 'Card image'">
                        </picture>
                        <div v-if="onEdit" class="absolute top-4 right-4">
                            <ToggleSwitch
                                v-model="isVisible"
                                left-label="Hidden"
                                right-label="Visible"
                                text-size="sm"
                                @update:modelValue="handleVisibilityChange" />
                        </div>
                    </div>
                </template>

                <template v-if="hasName || cardBeforeEdit.name">
                    <template v-if="onEdit">
                        <div class="field h3">
                            <input 
                                type="text" 
                                v-model="card.name"
                                :class="{ 'border-red-500': v$.card.name.$error }"
                                class="border-gray-200 border p-4 rounded-2xl w-full mb-4"
                                :placeholder="hasName">
                            <div v-if="v$.card.name.$error" class="text-red-500 text-sm mt-1">
                                <p v-if="!v$.card.name.maxLength">The name is too long.</p>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div 
                            @click="onEdit = true"
                            class="card-name">
                            <h2>{{ hasName }}</h2>
                        </div>
                    </template>
                </template>

                <template v-if="hasUrl || cardBeforeEdit.url">
                    <template v-if="onEdit">
                        <div class="field h3">
                            <input 
                                type="text" 
                                v-model="card.url"
                                :class="{ 'border-red-500': v$.card.url.$error }"
                                class="border-gray-200 border p-4 rounded-2xl w-full mb-4"
                                :placeholder="hasUrl">
                            <div v-if="v$.card.url.$error" class="text-red-500 text-sm mt-1">
                                <p v-if="!v$.card.url.maxLength">The url is too long.</p>
                            </div>
                        </div>
                    </template>
                </template>

                <div class="mt-4" v-if="card.blurb">
                    <template v-if="onEdit">
                        <tiptap 
                            v-model="card.blurb"
                            @cancel="resetCard"
                            @save="updateCard"
                            :disabled="disabled"
                            :class="{ 'border-red-500': v$.card.blurb.$error }" />
                        <div v-if="v$.card.blurb.$error" class="text-red-500 text-sm mt-1">
                            <p v-if="!v$.card.blurb.required">Please add a description.</p>
                            <p v-if="!v$.card.blurb.maxLength">The description is too long.</p>
                        </div>
                    </template>
                    <template v-else>
                        <div 
                            @click="onEdit=true"
                            class="card-blurb text-lg leading-relaxed">
                            <div v-html="card.blurb" />
                        </div>
                    </template>
                </div>

                <template v-if="hover && !onEdit">
                    <div class="absolute top-[-1rem] right-[-1rem]">
                        <button 
                            @click="deleteCard"
                            class="w-12 h-12 flex rounded-full justify-center items-center border-2 bg-white hover:bg-black hover:text-white">
                            <svg class="w-12 h-12">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                            </svg>
                        </button>
                    </div>
                </template>
            </template>
            
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import moment from 'moment'
import Tiptap from './Components/Tiptap.vue'
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue'

const props = defineProps({
    parentCard: {
        type: Object,
        required: true
    },
    owner: {
        type: Boolean,
        default: false
    },
})

const emit = defineEmits(['update'])

const imageUrl = import.meta.env.VITE_IMAGE_URL

// State
const card = ref({ ...props.parentCard })
const cardBeforeEdit = ref({ ...props.parentCard })
const onEdit = ref(false)
const disabled = ref(false)
const formData = ref(new FormData())
const hover = ref(false)

// Validation rules
const rules = {
    card: {
        name: { maxLength: maxLength(255) },
        blurb: {
            required,
            maxLength: maxLength(40000)
        },
        url: { maxLength: maxLength(255) }
    }
}

const v$ = useVuelidate(rules, { card })

// Computed
const hasImage = computed(() => {
    if (!card.value) return null

    // For event cards
    if (card.value.event_id) {
        // Check event images first
        if (card.value.event?.images?.length > 0) {
            return card.value.event.images[0].largeImagePath || card.value.event.images[0].thumbImagePath
        }
        // Fallback to event direct images
        return card.value.event?.largeImagePath || card.value.event?.thumbImagePath
    }
    
    // For regular cards
    if (card.value.type === 'i') {
        // Check card images first
        if (card.value.images?.length > 0) {
            return card.value.images[0].large_image_path
        }
        // Fallback to direct image path
        return card.value.thumbImagePath
    }

    return null
})

const hasName = computed(() => 
    card.value?.event?.name && !card.value?.name 
        ? card.value.event.name 
        : card.value?.name || ''
)

const hasUrl = computed(() => {
    if (card.value?.event && !card.value?.url) {
        return `/events/${card.value.event.slug}`
    }
    return card.value?.url || ''
})

// Methods
const updateCard = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    appendCardData()
    try {
        const res = await axios.post(`/cards/${card.value.id}`, formData.value)
        card.value = res.data
        cardBeforeEdit.value = { ...res.data }
        clear()
    } catch (error) {
        console.error('Failed to update card:', error)
    }
}

const resetCard = () => {
    card.value = { ...cardBeforeEdit.value }
    clear()
}

const deleteCard = async () => {
    try {
        const res = await axios.delete(`/cards/${card.value.id}`)
        emit('update', res.data)
    } catch (error) {
        console.error('Failed to delete card:', error)
    }
}

const appendCardData = () => {
    formData.value = new FormData()
    if (card.value.name) formData.value.append('name', card.value.name)
    if (card.value.url) formData.value.append('url', card.value.url)
    if (card.value.blurb) formData.value.append('blurb', card.value.blurb)
    formData.value.append('type', card.value.type)
}

const addImageSubmit = (image) => {
    disabled.value = true
    formData.value.append('image', image)
    if (card.value.type === 'h') formData.value.append('type', 'e')
    updateCard()
}

const removeImage = () => {
    disabled.value = true
    formData.value.append('type', 'h')
    updateCard()
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

// Add isVisible computed
const isVisible = computed({
    get: () => card.value.type !== 'h',
    set: (value) => {
        card.value.type = value ? 'e' : 'h'
    }
})

// Add handleVisibilityChange method
const handleVisibilityChange = (value) => {
    card.value.type = value ? 'e' : 'h'
}
</script>

<style scoped>
.event-card .card-blurb :deep(p) {
    @apply text-base md:text-lg lg:text-3xl leading-tight;
}

.card-blurb :deep(p) {
    @apply text-base md:text-lg lg:text-3xl leading-relaxed;
}
</style>