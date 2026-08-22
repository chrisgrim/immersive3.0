<template>
    <Transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            class="fixed bottom-[10%] left-1/2 -translate-x-1/2 z-50 bg-white rounded-xl shadow-custom-6 py-4 px-8 border"
        >
            <div class="flex items-center gap-8">
                <p class="text-lg text-gray-600">{{ message }}</p>
                <button
                    v-if="actionLabel"
                    type="button"
                    class="text-lg font-semibold border border-black rounded-[2rem] py-2 px-4 hover:bg-black hover:text-white transition-colors flex-shrink-0"
                    @click="$emit('action')"
                >
                    {{ actionLabel }}
                </button>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    message: {
        type: String,
        default: 'Updated successfully'
    },
    duration: {
        type: Number,
        default: 3000
    },
    actionLabel: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['update:show', 'action']);

// Auto-hide timer
watch(() => props.show, (newValue) => {
    if (newValue) {
        setTimeout(() => {
            emit('update:show', false);
        }, props.duration);
    }
});
</script>