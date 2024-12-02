<template>
    <div class="w-full">
        <draggable
            v-if="posts"
            class="w-full"
            v-model="posts"
            :draggable="draggable ? '.drag' : false"
            @start="isDragging = true" 
            @end="debounce"
            item-key="id">
            <template #item="{ element: post, index }">
                <div 
                    @mouseover="showDelete = index"
                    @mouseleave="showDelete = null"
                    :class="{ drag: draggable }"
                    class="block cursor-pointer">
                    <div class="group relative grid grid-cols-4 gap-8 py-4 h-36 items-center hover:bg-gray-100 rounded-2xl"
                         style="grid-template-columns: 16rem 30% auto auto;">
                        <div class="px-8">
                            <template v-if="post.images?.length > 0">
                                <picture>
                                    <source :srcset="`${imageUrl}${post.images[0].large_image_path}`" type="image/webp">
                                    <img :src="`${imageUrl}${post.images[0].large_image_path}`"
                                         :alt="`${post.name}`"
                                         class="h-24 w-full object-cover rounded-2xl">
                                </picture>
                            </template>
                            <template v-else-if="post.thumbImagePath">
                                <picture>
                                    <source :srcset="`${imageUrl}${post.thumbImagePath}`" type="image/webp">
                                    <img :src="`${imageUrl}${post.thumbImagePath}`"
                                         :alt="`${post.name}`"
                                         class="h-24 w-full object-cover rounded-2xl">
                                </picture>
                            </template>
                            <template v-else>
                                <div class="h-24 w-full rounded-2xl bg-gray-300"></div>
                            </template>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-2xl font-medium">{{ post.name }}</p>
                                <span v-if="post.status === 'd'" class="text-gray-500">(Not Live)</span>
                            </div>
                            <p class="text-md leading-4 text-gray-500">last edited: {{ formatDate(post.updated_at) }}</p>
                        </div>
                        <div class="flex items-center">
                            <p class="text-lg text-gray-500">{{ post.cards?.length || 0 }} Cards</p>
                        </div>
                        <div class="flex items-center justify-end pr-8">
                            <button 
                                v-if="showDelete === index"
                                @click="openDeleteModal(post)"
                                class="absolute top-[-1rem] z-20 right-[-.4rem] items-center justify-center rounded-full p-0 w-12 h-12 flex border-2 bg-white border-black hover:bg-black hover:fill-white">
                                @click.stop="openDeleteModal(post)"
                                class="rounded-full p-2 hover:bg-gray-200">
                                <svg class="w-6 h-6 text-red-500">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-delete-bin-line`" />
                                </svg>
                            </button>
                            <a 
                                :href="`/communities/${community.slug}/${post.slug}/edit`"
                                class="ml-4">
                                <svg class="w-6 h-6 text-gray-500">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-edit-line`" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" 
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white p-6 rounded-2xl max-w-md w-full mx-4">
                <h3 class="text-xl mb-4">Delete Post</h3>
                <p class="mb-6">Are you sure you want to delete this post?</p>
                <div class="flex justify-end gap-4">
                    <button 
                        @click="closeDeleteModal"
                        class="px-4 py-2 rounded-full border border-black hover:bg-black hover:text-white">
                        Cancel
                    </button>
                    <button 
                        @click="deletePost"
                        class="px-4 py-2 rounded-full bg-red-500 text-white border border-red-500 hover:bg-white hover:text-red-500">
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Load More Button -->
        <div v-if="hasNextPage" class="mt-4 text-center">
            <button 
                class="rounded-full py-2 px-4 border border-black hover:bg-black hover:text-white"
                @click="fetchPosts">
                Load More
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Draggable from "vuedraggable"
import CardImage from './image.vue'
import moment from 'moment' // Changed from dayjs to moment


const props = defineProps({
    modelValue: {
        type: Object,
        required: true
    },
    community: {
        type: Object,
        required: true
    },
    loadposts: {
        type: Array,
        default: () => []
    },
    title: Boolean,
    text: Boolean,
    link: Boolean,
    edit: Boolean,
    draggable: Boolean
})

const emit = defineEmits(['update:modelValue'])

const isDisabled = ref(false)
const showDelete = ref(null)
const posts = ref(props.loadposts)
const showDeleteModal = ref(false)
const selectedPost = ref(null)
const timeout = ref(null)
const isDragging = ref(false)
const formatDate = (date) => {
    return moment(date).format('MMM D, YYYY') // Changed to moment
}


const inputVal = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const hasNextPage = computed(() => {
    return posts.value?.length === 8
})

const canShowDeleteButton = (post) => {
    return true
}

const openDeleteModal = (post) => {
    selectedPost.value = post
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    selectedPost.value = null
    showDeleteModal.value = false
}

const deletePost = async () => {
    if (!selectedPost.value) return
    
    try {
        await axios.delete(`/communities/${props.community.slug}/${selectedPost.value.slug}`)
        posts.value = posts.value.filter(post => post.id !== selectedPost.value.id)
        emit('update:modelValue', { ...props.modelValue, posts: posts.value })
        closeDeleteModal()
    } catch (error) {
        console.error('Delete failed:', error)
    }
}

const updateShelfOrder = async () => {
    const list = posts.value.map((item, index) => ({
        ...item,
        order: index
    }))
    
    try {
        await axios.put(`/posts/${props.community.slug}/order`, list)
        emit('update:modelValue', { ...props.modelValue, posts: posts.value })
    } catch (error) {
        console.error('Order update failed:', error)
    }
}

const fetchPosts = async () => {
    if (!props.modelValue?.id) return
    
    try {
        const offset = posts.value?.length || 0
        const res = await axios.get(`/shelves/${props.modelValue.id}/paginate?offset=${offset}`)
        if (res.data?.length) {
            posts.value = [...posts.value, ...res.data]
        }
        if (res.data?.length < 8) {
            hasNextPage.value = false
        }
    } catch (error) {
        console.error('Fetch posts failed:', error)
    }
}

const debounce = () => {
    if (timeout.value) clearTimeout(timeout.value)
    timeout.value = setTimeout(() => {
        updateShelfOrder()
    }, 500)
}
</script>