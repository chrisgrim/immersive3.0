<template>
    <div class="fixed inset-0 z-50 overflow-y-auto" @click.self="$emit('close')">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-40"></div>
            
            <div class="relative bg-white rounded-3xl w-full max-w-2xl">
                <!-- Header -->
                <div class="flex justify-between items-center p-8 border-b border-gray-100">
                    <h2 class="text-2xl font-semibold">Price Range</h2>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Price Slider -->
                <div class="p-8">
                    <vue-slider
                        v-model="localPrice"
                        tooltip="always"
                        :min="0"
                        :max="maxPrice"
                        :tooltip-formatter="sliderFormat"
                        :enable-cross="false"
                        class="py-8"
                    />
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            v-if="hasPrice"
                            @click="clear" 
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                        >
                            Clear
                        </button>
                        <button 
                            v-else
                            @click="$emit('close')" 
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="submit" 
                            class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                        >
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'

const props = defineProps({
    modelValue: {
        type: Array,
        required: true
    },
    maxPrice: {
        type: Number,
        required: true
    }
})

const emit = defineEmits(['update:selected', 'close'])

const localPrice = ref(props.modelValue)

watch(() => props.modelValue, (newValue) => {
    localPrice.value = newValue
}, { immediate: true })

const hasPrice = computed(() => {
    return localPrice.value[0] !== 0 || localPrice.value[1] !== props.maxPrice
})

const sliderFormat = (v) => `$${('' + v).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`

const clear = () => {
    localPrice.value = [0, props.maxPrice]
    emit('update:selected', 'price', [0, props.maxPrice])
    emit('close')
}

const submit = () => {
    emit('update:selected', 'price', localPrice.value)
    emit('close')
}
</script>
