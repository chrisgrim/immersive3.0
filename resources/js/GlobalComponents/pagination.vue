<template>
    <div class="pagination">
        <ul 
            v-if="pagination.total > pagination.per_page"
            class="text-center flex pb-8 justify-center items-center list-none m-0 p-0"
        >
            <li class="inline p-2 mt-2">
                <button
                    class="border-none rounded-full p-2 inline-flex items-center justify-center font-medium"
                    :class="[ pagination.current_page === 1 ? 'shadow-custom-3' : 'shadow-custom-1 hover:bg-black' ]"
                    :tabindex="pagination.current_page === 1 && -1" 
                    :disabled="pagination.current_page === 1"
                    @click="$emit('paginate', pagination.current_page - 1)"
                >
                    <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        viewBox="0 0 24 24"
                        :class="[ pagination.current_page === 1 ? 'fill-gray-300' : 'hover:fill-white' ]"
                        class="w-10 h-10"
                    >
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                </button>
            </li>

            <li v-for="page in pageRange" :key="page">
                <button 
                    @click="$emit('paginate', page)"
                    :class="{ 'bg-black text-white': page === pagination.current_page }"
                    class="w-12 h-12 rounded-full items-center justify-center inline-flex bg-white border-none font-medium"
                >
                    {{ page }}
                </button>
            </li>

            <li>
                <button
                    :disabled="pagination.current_page === pagination.last_page"
                    class="border-none rounded-full p-2 inline-flex items-center justify-center font-medium"
                    :class="[ pagination.current_page === pagination.last_page ? 'shadow-custom-3' : 'shadow-custom-1 hover:bg-black' ]"
                    :tabindex="pagination.current_page === pagination.last_page && -1" 
                    @click="$emit('paginate', pagination.current_page + 1)"
                >
                    <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        viewBox="0 0 24 24"
                        :class="[ pagination.current_page === pagination.last_page ? 'fill-gray-300' : 'hover:fill-white' ]"
                        class="w-10 h-10"
                    >
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                    </svg>
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    pagination: {
        type: Object,
        required: true
    },
    limit: {
        type: Number,
        default: 2
    }
})

const pageRange = computed(() => {
    if (props.limit === -1) return 0
    if (props.limit === 0) return props.pagination.last_page

    const current = props.pagination.current_page
    const last = props.pagination.last_page
    const delta = props.limit
    const left = current - delta
    const right = current + delta + 1
    const range = []
    const pages = []
    let l

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= left && i < right)) {
            range.push(i)
        }
    }

    range.forEach(function (i) {
        if (l) {
            if (i - l === 2) {
                pages.push(l + 1)
            } else if (i - l !== 1) {
                pages.push('...')
            }
        }
        pages.push(i)
        l = i
    })

    return pages
})
</script>