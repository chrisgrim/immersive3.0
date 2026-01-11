<template>
    <div 
        @mouseover="overImage = true"
        @mouseleave="overImage = false"
        class="relative aspect-[16/9] w-full">
        <label 
            class="block w-full h-full cursor-pointer rounded-2xl overflow-hidden"
        >  
            <template v-if="hasImage">
                <img 
                    :src="imageFile.src" 
                    class="w-full h-full object-cover rounded-2xl"
                />
            </template>
            <div 
                v-else
                class="w-full h-full flex flex-col items-center justify-center border border-dashed border-gray-300 rounded-2xl hover:border-black hover:border-2"
            >
                <svg class="w-16 h-16 m-auto">
                    <use :xlink:href="`/storage/website-files/icons.svg#ri-image-line`" />
                </svg>
                <p class="text-lg">Click to add image</p>
            </div>
            <input
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/webp"
                @change="onFileChange">
        </label>

        <!-- Close Button -->
        <div 
            @click="$emit('cancel')" 
            class="absolute top-[-1rem] right-[-1rem] cursor-pointer bg-white rounded-full"
            @mouseenter="hoveredClose = true"
            @mouseleave="hoveredClose = false"
        >
            <component :is="hoveredClose ? RiCloseCircleFill : RiCloseCircleLine" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue"
import axios from 'axios'

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
    thumbImagePath: null,
    post_id: props.post.id,
    community_id: props.community.id,
    type: 'i',
    order: props.position
})

const formData = ref(new FormData())
const disabled = ref(false)
const imageFile = ref(null)
const overImage = ref(false)
const hoveredClose = ref(false)

// Computed
const hasImage = computed(() => 
    imageFile.value?.src ? true : false
)

const isVisible = computed({
    get: () => card.value.type !== 'h',
    set: (value) => {
        card.value.type = value ? 'i' : 'h'
    }
})

// Methods
const saveCard = async () => {
    addCardData()
    try {
        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${props.post.slug}/cards`, 
            formData.value
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
    formData.value = new FormData()
    formData.value.append('type', card.value.type)
    formData.value.append('order', card.value.order)
    formData.value.append('post_id', card.value.post_id)
    formData.value.append('community_id', props.community.id)
    formData.value.append('position', props.position)
    if (imageFile.value?.file) {
        formData.value.append('image', imageFile.value.file)
    }
}

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
            await saveCard()
        }
    }
    reader.readAsDataURL(file)
}

const handleVisibilityChange = (value) => {
    card.value.type = value ? 'i' : 'h'
}
</script>

<style scoped>
.hidden {
    display: none;
}
</style>