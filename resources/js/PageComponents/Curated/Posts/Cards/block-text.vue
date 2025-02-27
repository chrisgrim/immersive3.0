<template>
    <div class="mt-8 relative p-4">
        <tiptap 
            @cancel="cancelCard"
            @save="saveCard"
            :class="{ 'border-red-500': v$.card.blurb.$error }"
            v-model="card.blurb" />
        <div v-if="v$.card.blurb.$error" class="text-red-500 text-sm mt-1">
            <p v-if="!v$.card.blurb.required" class="text-red-500">
                Please add a description.
            </p>
            <p v-if="!v$.card.blurb.maxLength" class="text-red-500">
                The description is too long.
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength } from '@vuelidate/validators'
import Tiptap from './Components/Tiptap.vue'

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
    blurb: null,
    post_id: props.post.id,
    community_id: props.community.id,
    type: 't',
    order: props.position
})

// Validation rules
const rules = {
    card: {
        blurb: {
            required,
            maxLength: maxLength(40000)
        }
    }
}

const v$ = useVuelidate(rules, { card })

const saveCard = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return
    
    console.log('Sending card data:', card.value)
    
    try {
        const formData = new FormData()
        formData.append('blurb', card.value.blurb)
        formData.append('type', card.value.type)
        formData.append('order', card.value.order)
        formData.append('post_id', card.value.post_id)
        formData.append('community_id', props.community.id)
        formData.append('position', props.position)

        const res = await axios.post(
            `/communities/${props.community.slug}/posts/${props.post.slug}/cards`, 
            formData
        )
        console.log('Response received:', res.data)
        emit('update', res.data)
    } catch (error) {
        console.error('Failed to save card:', error)
    }
}

const cancelCard = () => {
    emit('cancel')
}

console.log('TextBlock initialized with position:', props.position)
</script>