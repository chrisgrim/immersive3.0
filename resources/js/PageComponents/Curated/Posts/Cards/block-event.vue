<template>
    <div class="relative p-4">
        <div class="flex gap-8">
            <template v-if="hasImage && isVisible">
                <div class="relative rounded-2xl overflow-hidden mb-8 w-1/2">
                    <picture>
                        <source 
                            type="image/webp" 
                            :srcset="`${imageUrl}${hasImage}`"> 
                        <img 
                            loading="lazy"
                            class="w-full rounded-2xl align-bottom object-cover h-full"
                            :src="`${imageUrl}${hasImage.slice(0, -4)}jpg`" 
                            :alt="`${card.name}`">
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
            </template>
            <div :class="[hasImage && isVisible ? 'w-1/2' : 'w-full']">
                <div v-if="hasImage && !isVisible" class="mb-4">
                    <ToggleSwitch
                        v-model="isVisible"
                        left-label="Hidden"
                        right-label="Visible"
                        text-size="sm"
                        @update:modelValue="handleVisibilityChange" />
                </div>
                <div class="field mb-4">
                    <EventSearch 
                        @select="selectEvent"
                        class="w-full" />
                </div>
                <div class="field border border-gray-300 rounded-md p-4 mb-4">
                    <input 
                        type="text" 
                        v-model="card.name"
                        class="w-full"
                        :class="{ 'border-red-500': v$.card.name.$error }"
                        :placeholder="name">
                    <div v-if="v$.card.name.$error" class="text-red-500 text-sm mt-1">
                        <p v-if="!v$.card.name.maxLength">The name is too long.</p>
                    </div>
                </div>
                <div class="field border border-gray-300 rounded-md p-4 mb-4">
                    <input 
                        class="w-full"
                        type="text" 
                        v-model="card.url"
                        :placeholder="url">
                </div>
                <div 
                    class="mb-4" 
                    v-if="selectedEvent">
                    <p>Booking Through: {{ cleanDate(selectedEvent.closingDate) }}</p>
                </div>
            </div>
        </div>
        <tiptap 
            v-model="card.blurb"
            @cancel="cancelCard"
            @save="saveCard"
            :disabled="disabled"
            :class="{ 'border-red-500': v$.card.blurb.$error }" />
        <div v-if="v$.card.blurb.$error" class="text-red-500 text-sm mt-1">
            <p v-if="!v$.card.blurb.required">Please add a description.</p>
            <p v-if="!v$.card.blurb.maxLength">The description is too long.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import moment from 'moment'
import Tiptap from './Components/Tiptap.vue'
import CardImage from './block-image.vue'
import EventSearch from '../event-search.vue'
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

const imageUrl = import.meta.env.VITE_IMAGE_URL

// State
const card = ref({
    blurb: null,
    thumbImagePath: null,
    post_id: props.post.id,
    event_id: null,
    url: null,
    name: null,
    type: 'e',
    order: props.position || 0  // Ensure order is set
})

const formData = ref(new FormData())
const disabled = ref(false)
const selectedEvent = ref(null)

const isVisible = computed({
    get: () => card.value.type !== 'h',
    set: (value) => {
        card.value.type = value ? 'e' : 'h'
    }
})

onMounted(() => {
    // No need to call generateSearchList here as it's handled by EventSearch
})

// Validation rules
const rules = {
    card: {
        name: { maxLength: maxLength(255) },
        blurb: {
            required,
            maxLength: maxLength(40000)
        }
    }
}

const v$ = useVuelidate(rules, { card })

// Computed
const url = computed(() => 
    selectedEvent.value ? `/events/${selectedEvent.value.slug}` : 'Url (Optional)'
)

const name = computed(() => 
    selectedEvent.value ? selectedEvent.value.name : 'Event name'
)

const thumbImagePath = computed(() => 
    selectedEvent.value ? `${imageUrl}${selectedEvent.value.thumbImagePath}` : null
)

const hasImage = computed(() => {
    if (!selectedEvent.value) return null
    
    if (selectedEvent.value.images?.length > 0) {
        return selectedEvent.value.images[0].largeImagePath || selectedEvent.value.images[0].thumbImagePath
    }
    
    return selectedEvent.value.largeImagePath || selectedEvent.value.thumbImagePath || null
})

// Methods
const saveCard = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    addCardData()
    try {
        console.log('Saving card with order:', card.value.order)
        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${props.post.slug}/cards`,
            formData.value  // Use the FormData we prepared in addCardData()
        )
        emit('update', res.data)
        disabled.value = false
    } catch (error) {
        console.error('Failed to save card:', error)
    }
}

const cancelCard = () => {
    emit('cancel')
}

const addCardData = () => {
    formData.value = new FormData() // Reset FormData
    formData.value.append('blurb', card.value.blurb)
    formData.value.append('order', card.value.order)
    formData.value.append('type', card.value.type)
    formData.value.append('post_id', card.value.post_id)
    formData.value.append('community_id', props.community.id)
    formData.value.append('position', props.position)  // Add position
    if (selectedEvent.value) {
        formData.value.append('event_id', selectedEvent.value.id)
    }
    if (card.value.url) formData.value.append('url', card.value.url)
    if (card.value.name) formData.value.append('name', card.value.name)
    if (card.value.button_text) formData.value.append('button_text', card.value.button_text)
}

const selectEvent = (event) => {
    if (!event) return
    selectedEvent.value = event
    card.value.event_id = event.id
    card.value.name = event.name
    card.value.blurb = event.tag_line || ''
    card.value.url = `/events/${event.slug}`
    card.value.thumbImagePath = event.thumbImagePath || null
}

const cleanDate = (date) => {
    return moment(date).format("dddd, MMMM D YYYY")
}

const handleVisibilityChange = (value) => {
    card.value.type = value ? 'e' : 'h'
}
</script>