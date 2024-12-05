<template>
    <div class="relative">
        <div 
            :class="{ 'cursor-pointer border !border-black': hover && !onEdit }"
            @mouseover="hover = true"
            @mouseleave="hover = false"
            class="block rounded-2xl border-transparent border-2">
            <template v-if="hasImage || onEdit && card.url">
                <CardImage
                    :loading="disabled"
                    :image="image"
                    :can-delete="card.type === 'e'"
                    @onDelete="removeImage"
                    @addImage="addImageSubmit" />
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

            <template v-if="card.event_id">
                <div 
                    class="flex mt-4" 
                    @click="onEdit=true">
                    <p class="text-1xl">Closing Date: {{ cleanDate(card.event.closingDate) }}</p>
                    <template v-if="onEdit">
                        <a target="_blank" :href="`/hosting/event/${card.event.slug}/edit?dates`">
                            <button class="ml-4 px-4 py-2 bg-black text-white rounded-xl hover:bg-white hover:text-black">
                                Edit dates
                            </button>
                        </a>
                        <a target="_blank" :href="card.event.ticketUrl">
                            <button class="ml-4 px-4 py-2 bg-black text-white rounded-xl hover:bg-white hover:text-black">
                                Check Event
                            </button>
                        </a>
                    </template>
                </div>
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
                        class="card-blurb">
                        <div v-html="cleanBlurb(card.blurb)" />
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
        </div>
        
        <transition name="slide-fade">
            <div 
                v-if="updated" 
                class="bg-green-50 text-green-800 p-4 rounded-lg mt-4">
                <p>Your Post has been updated.</p>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import moment from 'moment'
import Tiptap from './Components/Tiptap.vue'
import CardImage from './block-image.vue'

const props = defineProps({
    parentCard: {
        type: Object,
        required: true
    },
    owner: {
        type: Boolean,
        default: false
    }
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
const updated = ref(false)

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
    const type = card.value?.type
    return type === 'i' || type === 'e' || type === 'h'
})

const image = computed(() => {
    const type = card.value?.type
    const thumbPath = card.value?.thumbImagePath
    const eventThumbPath = card.value?.event?.thumbImagePath

    if (!type) return null
    if (type === 'i' && thumbPath) return `${imageUrl}${thumbPath}`
    if (type === 'h' || type === 't') return null
    if (type === 'e') {
        if (thumbPath) return `${imageUrl}${thumbPath}`
        if (eventThumbPath) return `${imageUrl}${eventThumbPath}`
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
        onUpdated()
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
    formData.value.append('_method', 'PUT')
    if (card.value.name) formData.value.append('name', card.value.name)
    if (card.value.url) formData.value.append('url', card.value.url)
    if (card.value.blurb) formData.value.append('blurb', card.value.blurb)
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
    onEdit.value = false
    hover.value = false
    disabled.value = false
    formData.value = new FormData()
}

const onUpdated = () => {
    v$.value.$reset()
    updated.value = true
    setTimeout(() => updated.value = false, 3000)
}

const cleanDate = (date) => {
    return moment(date).format("dddd, MMMM D YYYY")
}

const cleanBlurb = (blurb) => {
    // Create a temporary div to parse the HTML
    const temp = document.createElement('div')
    temp.innerHTML = blurb

    // Handle empty paragraphs and br tags
    const paragraphs = Array.from(temp.querySelectorAll('p'))
    const cleanedParagraphs = paragraphs.map(p => {
        // If paragraph is empty or only contains a br tag
        if (!p.textContent.trim() || p.innerHTML === '<br class="ProseMirror-trailingBreak">') {
            return '<p><br></p>'
        }
        return p.outerHTML
    })

    // Handle standalone <br> tags
    const brTags = Array.from(temp.querySelectorAll('br'))
    brTags.forEach(br => {
        if (!br.parentElement.textContent.trim()) {
            br.parentElement.innerHTML = '<br>'
        }
    })

    return cleanedParagraphs.join('')
}
</script>