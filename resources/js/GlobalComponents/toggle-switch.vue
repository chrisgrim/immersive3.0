<template>
    <div class="relative">
        <label class="inline-flex items-center cursor-pointer">
            <input 
                type="checkbox" 
                class="sr-only" 
                :checked="modelValue" 
                @change="$emit('update:modelValue', !modelValue)">
            <div :class="[
                    'relative rounded-full p-1 transition duration-300 ease-in-out',
                    modelValue ? 'bg-[#ff385c]' : 'bg-black',
                    sizeClass
                ]">
                <div :class="[
                        'absolute inset-1 bg-white rounded-full transform transition-transform duration-300 ease-in-out shadow-md',
                        {'translate-x-[calc(100%-0.50rem)]': modelValue},
                        toggleSizeClass
                    ]">
                </div>
                <div class="relative flex justify-between items-center h-full px-2">
                    <span class="flex-1 text-center z-10 transition-opacity duration-300 font-bold truncate"
                          :class="[textSizeClass, !modelValue ? 'text-black opacity-100' : 'opacity-0']">
                        {{ leftLabel }}
                    </span>
                    <span class="flex-1 text-center z-10 transition-opacity duration-300 font-bold truncate"
                          :class="[textSizeClass, modelValue ? 'text-black opacity-100' : 'opacity-0']">
                        {{ rightLabel }}
                    </span>
                </div>
            </div>
        </label>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true
    },
    leftLabel: {
        type: String,
        default: 'Off'
    },
    rightLabel: {
        type: String,
        default: 'On'
    },
    textSize: {
        type: String,
        default: 'sm',
        validator: (value) => ['xs', 'sm', 'base', 'lg', 'xl'].includes(value)
    }
})

defineEmits(['update:modelValue'])

const textSizeClass = computed(() => ({
    'text-xs': props.textSize === 'xs',
    'text-sm': props.textSize === 'sm',
    'text-base': props.textSize === 'base',
    'text-lg': props.textSize === 'lg',
    'text-xl': props.textSize === 'xl'
}))

const sizeClass = computed(() => ({
    'w-24 h-8': props.textSize === 'xs',
    'w-32 h-10': props.textSize === 'sm' || props.textSize === 'base',
    'w-40 h-12': props.textSize === 'lg',
    'w-48 h-14': props.textSize === 'xl'
}))

const toggleSizeClass = computed(() => ({
    'w-[45%]': props.textSize === 'xs',
    'w-[50%]': props.textSize === 'sm' || props.textSize === 'base' || props.textSize === 'lg' || props.textSize === 'xl'
}))
</script>