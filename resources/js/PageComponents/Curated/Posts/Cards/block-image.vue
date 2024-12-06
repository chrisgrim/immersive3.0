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
                    :src="props.image" 
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
                <p class="text-lg">{{ text }}</p>
            </div>
            <input
                type="file"
                class="hidden"
                accept="image/*"
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

        <!-- Validation Error Messages -->
        <div v-if="v$.imageFile.$error" class="absolute inset-0 rounded-2xl p-4 bg-white">
            <p class="text-red-600" v-if="!v$.imageFile.fileSize">The image file size is over 10mb</p>
            <p class="text-red-600" v-if="!v$.imageFile.fileType">The image needs to be a JPG, PNG or GIF</p>
            <p class="text-red-600" v-if="!v$.imageFile.imageRatio">The image needs to be at least {{ width }} x {{ height }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { RiCloseCircleLine, RiCloseCircleFill } from "@remixicon/vue"

const props = defineProps({
    loading: Boolean,
    image: String
})

const emit = defineEmits(['addImage', 'cancel'])

const imageFile = ref('')
const overImage = ref(false)
const hoveredClose = ref(false)
const size = ref(10485760) // 10MB
const width = ref(800)
const height = ref(450)
const text = ref('Image should be at least 800 x 450')

// Validation rules
const rules = {
    imageFile: {
        fileSize: (value) => !value || value.file.size < size.value,
        fileType: (value) => !value || ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(value.file.type),
        imageRatio: (value) => !value || (value.width >= width.value && value.height >= height.value)
    }
}

const v$ = useVuelidate(rules, { imageFile })

// Computed properties
const hasImage = computed(() => 
    props.image || imageFile.value.src ? true : false
)

// Methods
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

            emit('addImage', file, e.target.result)
        }
    }
    reader.readAsDataURL(file)
}
</script>

<style scoped>
.hidden {
    display: none;
}
</style>