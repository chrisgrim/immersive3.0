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
                    <Dropdown
                        :list="searchOptions"
                        :placeholder="'Search for event'"
                        @onSelect="selectEvent"
                        @input="debounce"
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
import Dropdown from '@/GlobalComponents/dropdown.vue'
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue'

const props = defineProps({
    post: {
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
    type: 'e'
})

const formData = ref(new FormData())
const searchInput = ref('')
const searchOptions = ref([])
const disabled = ref(false)
const timeout = ref(null)
const selectedEvent = ref(null)

// Add these near your other refs/computed properties
const isVisible = computed({
    get: () => card.value.type !== 'h',
    set: (value) => {
        card.value.type = value ? 'e' : 'h'
    }
})

// Fetch initial events list when component mounts
onMounted(() => {
    generateSearchList('')
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

// Add hasImage computed property
const hasImage = computed(() => {
    if (!selectedEvent.value) return null
    
    // First check event images
    if (selectedEvent.value.images?.length > 0) {
        return selectedEvent.value.images[0].largeImagePath || selectedEvent.value.images[0].thumbImagePath
    }
    
    // Fallback to event images
    return selectedEvent.value.largeImagePath || selectedEvent.value.thumbImagePath || null
})

// Methods
const saveCard = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    addCardData()
    try {
        const res = await axios.post(`/cards/${props.post.slug}/create`, formData.value)
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
    formData.value.append('blurb', card.value.blurb)
    if (card.value.url) formData.value.append('url', card.value.url)
    if (card.value.name) formData.value.append('name', card.value.name)
    if (card.value.type) formData.value.append('type', card.value.type)
    if (card.value.event_id) formData.value.append('event_id', card.value.event_id)
}

const debounce = (event) => {
    const query = typeof event === 'object' ? event.target.value : event
    
    console.log('Debouncing search:', query)
    if (timeout.value) clearTimeout(timeout.value)
    
    searchInput.value = query
    timeout.value = setTimeout(() => {
        if (query && query.length > 0) {
            generateSearchList(query)
        }
    }, 300)
}

const generateSearchList = async (query) => {
    console.log('Generating search list for:', query)
    try {
        const res = await axios.get('/api/search/nav/events', { 
            params: { 
                keywords: query,
                limit: 10 // Optional: limit initial results
            } 
        })
        
        searchOptions.value = res.data.map(hit => ({
            id: hit.model.id,
            name: hit.model.name,
            slug: hit.model.slug,
            tag_line: hit.model.tag_line || '',
            thumbImagePath: hit.model.thumbImagePath || '',
            closingDate: hit.model.closingDate || null
        }))
        
        console.log('Processed options:', searchOptions.value)
    } catch (error) {
        console.error('Failed to fetch search results:', error)
        searchOptions.value = []
    }
}

const selectEvent = (event) => {
    if (!event) return
    console.log('Selected event:', event)
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

// Add a watcher to debug searchOptions changes
watch(searchOptions, (newVal) => {
    console.log('searchOptions updated:', newVal)
}, { deep: true })

// Add this method to handle visibility changes
const handleVisibilityChange = (value) => {
    card.value.type = value ? 'e' : 'h'
}
</script>