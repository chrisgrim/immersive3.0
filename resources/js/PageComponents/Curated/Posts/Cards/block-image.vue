<template>
    <div 
        @mouseover="overImage = true"
        @mouseleave="overImage = false"
        :class="{ '': overImage }"
        class="relative w-96 h-full">
        <template v-if="canDelete && overImage">
            <div class="absolute top-[-1rem] z-10 right-[-1rem]">
                <button 
                    @click="onDelete"
                    class="items-center justify-center rounded-full p-0 w-12 h-12 flex border-2 bg-white border-black">
                    <svg class="w-12 h-12">
                        <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                    </svg>
                </button>
            </div>
        </template>
        <label 
            :class="{ '': overImage }"
            class="cursor-pointer justify-center items-center flex mb-8 w-96 h-64 bg-cover bg-center bg-no-repeat flex-col rounded-2xl" 
            :style="backgroundImage">  
            <div v-if="!hasImage || overImage">
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
        <div v-if="v$.imageFile.$error" class="absolute w-96 h-36 rounded-2xl inset-0 m-auto p-4 bg-white">
            <p class="text-red-600" v-if="!v$.imageFile.fileSize">The image file size is over 10mb</p>
            <p class="text-red-600" v-if="!v$.imageFile.fileType">The image needs to be a JPG, PNG or GIF</p>
            <p class="text-red-600" v-if="!v$.imageFile.imageRatio">The image needs to be at least {{ width }} x {{ height }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'

const props = defineProps({
    loading: Boolean,
    image: String,
    canDelete: Boolean
})

const emit = defineEmits(['addImage', 'onDelete'])

const imageFile = ref('')
const overImage = ref(false)
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

const backgroundImage = computed(() => {
    if (!hasImage.value) return null
    if (imageFile.value.src && !v$.value.imageFile.$error) {
        return `backgroundImage: url('${imageFile.value.src}')`
    }
    return `backgroundImage: url('${props.image?.slice(0, -4)}jpg?timestamp=${new Date().getTime()}')`
})

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

const onDelete = () => {
    emit('onDelete')
    imageFile.value = ''
}
</script>

<style scoped>
.hidden {
    display: none;
}
</style>