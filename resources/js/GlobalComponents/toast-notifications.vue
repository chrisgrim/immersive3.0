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
            class="fixed top-4 right-4 z-50 bg-white rounded-xl shadow-custom-6 p-4 max-w-sm border"
        >
            <div class="flex items-center gap-3">
                <svg 
                    class="w-6 h-6 text-green-500 flex-shrink-0" 
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path 
                        stroke-linecap="round" 
                        stroke-linejoin="round" 
                        stroke-width="2" 
                        d="M5 13l4 4L19 7"
                    />
                </svg>
                <p class="text-gray-600">{{ message }}</p>
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
    }
});

const emit = defineEmits(['update:show']);

// Auto-hide timer
watch(() => props.show, (newValue) => {
    if (newValue) {
        setTimeout(() => {
            emit('update:show', false);
        }, props.duration);
    }
});
</script>