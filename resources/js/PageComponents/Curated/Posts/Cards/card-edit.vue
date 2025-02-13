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

                            <h3 class="text-4xl font-bold mt-0">{{ hasName }}</h3>
                            <!-- Event Dates -->

                            <div class="card-blurb text-2xl leading-tight">
                                <vue-show-more 
                                    :text="stripHtml(card.blurb)"
                                    :limit="50"
                                    white-space="pre-wrap"
                                />
                            </div>
                            
                            <p class="text-gray-600 text-xl">
                                Booking Through: {{ cleanDate(card.event?.closingDate) }}
                            </p>

                            <!-- Event Blurb -->
                            <p class="text-gray-800">{{ card.event?.blurb }}</p>
                            
                        </div>

                        
                    </div>
                </div>

            </template>
            
            <template v-else>
                <!-- Regular Image -->
                <template v-if="!card.event_id && card.type === 'i'">
                    <div :class="['relative rounded-2xl overflow-hidden mb-8 cursor-pointer', isVisible ? 'aspect-[16/9]' : 'h-16']" >
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
    community: {
        type: Object,
        required: true
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
    card.value?.name || card.value?.event?.name || ''
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
        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${props.parentCard.post.slug}/cards/${card.value.id}`, 
            formData.value
        )
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
    if (card.value.name) formData.value.append('name', card.value.name)
    if (card.value.url) formData.value.append('url', card.value.url)
    if (card.value.blurb) formData.value.append('blurb', card.value.blurb)
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