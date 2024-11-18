<template>
    <div class="modal delete">
        <div class="wrapper">
            <div class="header">
                <button @click="onClose">
                    <svg>
                        <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                    </svg>
                </button>
            </div>
            <div class="body">
                <h5>Are you sure?</h5>
                <h3>{{ item.name }}</h3>
                <p>{{ body }}</p>
                <p><b>To archive {{ item.name }}, type ARCHIVE.</b></p>
                <div class="input">
                    <input
                        v-model="toDelete"
                        type="text">
                    <button
                        class="btn-borderless btn-login"
                        @click="onArchive"
                        :disabled="isDisabled">
                        Archive
                    </button>
                </div>
            </div>
            <div class="footer">
                <button 
                    class="btn-borderless" 
                    @click="onClose">
                    Close
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    item: {
        type: Object,
        required: true
    },
    strict: {
        type: Boolean,
        default: false
    },
    body: {
        type: String,
        required: true
    }
})

const emit = defineEmits(['archive', 'close'])

const toDelete = ref(null)
const isDisabled = ref(true)

const onArchive = () => {
    emit('archive', true)
}

const onClose = () => {
    emit('close', true)
}

watch(toDelete, (newValue) => {
    isDisabled.value = newValue?.trim().toLowerCase() !== 'archive'
})
</script>