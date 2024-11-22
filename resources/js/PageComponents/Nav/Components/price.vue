<template>
    <div class="relative">
        <button 
            ref="priceButton"
            @click="togglePrice" 
            :class="{ 'bg-gray-100': hasPrice }" 
            class="px-8 py-4 rounded-full bg-white border border-gray-200 hover:bg-gray-50"
        >
            <span class="text-lg font-medium">
                <template v-if="hasPrice">
                    ${{ modelValue[0] }} - ${{ modelValue[1] }}
                </template>
                <template v-else>
                    Price
                </template>
            </span>
        </button>

        <div 
            v-if="isShowing"
            ref="pricePopUp"
            class="absolute mt-4 right-0 z-50"
            @click.stop
        > 
            <div 
                class="bg-white shadow-lg rounded-3xl w-[500px]"
                @click.stop
            >
                <div class="p-12">
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
                <div class="px-8 py-6 border-t border-gray-100 flex justify-between items-center">
                    <button 
                        v-if="hasPrice" 
                        @click.stop="clear" 
                        class="text-gray-500 hover:text-gray-700"
                    >
                        Clear
                    </button>
                    <button 
                        v-else
                        @click.stop="hidePrice" 
                        class="text-gray-500 hover:text-gray-700"
                    >
                        Cancel
                    </button>
                    <button 
                        @click.stop="submit" 
                        class="px-8 py-4 rounded-full bg-default-red text-white hover:bg-red-600"
                    >
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'

const props = defineProps({
    modelValue: {
        type: Array,
        required: true
    },
    isShowing: {
        type: Boolean,
        default: false
    },
    maxPrice: {
        type: Number,
        default: 670
    }
})

const emit = defineEmits(['update:modelValue', 'update:showing', 'filter-update'])

const priceButton = ref(null)
const pricePopUp = ref(null)
const localPrice = ref(props.modelValue)

watch(() => props.modelValue, (newValue) => {
    localPrice.value = newValue
}, { immediate: true })

const hasPrice = computed(() => {
    return props.modelValue[0] !== 0 || props.modelValue[1] !== props.maxPrice
})

const sliderFormat = (v) => `$${('' + v).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`

const togglePrice = () => {
    emit('update:showing', !props.isShowing)
}

const hidePrice = () => {
    emit('update:showing', false)
}

const clear = () => {
    localPrice.value = [0, props.maxPrice]
    emit('update:modelValue', [0, props.maxPrice])
    emit('filter-update', 'price', [0, props.maxPrice])
    hidePrice()
}

const submit = () => {
    emit('update:modelValue', localPrice.value)
    emit('filter-update', 'price', localPrice.value)
    hidePrice()
}

const onClickOutside = (event) => {
    if (
        (!priceButton.value || !priceButton.value.contains(event.target)) &&
        (!pricePopUp.value || !pricePopUp.value.contains(event.target))
    ) {
        hidePrice()
    }
}

onMounted(() => {
    setTimeout(() => {
        document.addEventListener('click', onClickOutside)
    }, 0)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside)
})
</script>
