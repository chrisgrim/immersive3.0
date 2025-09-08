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
            <template #item="{ element }">
                <div 
                    v-if="element"
                    @mouseover="showDelete = element.id"
                    @mouseleave="showDelete = null"
                    :class="{ drag: draggable }"
                    class="block cursor-pointer">
                    <div class="group relative grid grid-cols-3 gap-8 py-2 items-center hover:bg-gray-100 rounded-2xl"
                         style="grid-template-columns: 8rem auto 1fr;">
                        <div class="px-6 flex items-center">
                            <a v-if="community?.slug && element?.slug" 
                               :href="`/communities/${community.slug}/posts/${element.slug}/edit`"
                               class="block w-full h-16">
                                <CardImage 
                                    :element="element"
                                    :community="community"
                                />
                            </a>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <a v-if="community?.slug && element?.slug" 
                                   :href="`/communities/${community.slug}/posts/${element.slug}/edit`" 
                                   class="hover:underline">
                                    <p class="text-2xl font-medium">{{ element.name }}</p>
                                </a>
                            </div>
                            <p class="text-md leading-4 text-gray-500">last edited: {{ formatDate(element.updated_at) }}</p>
                        </div>
                        <div class="flex items-center justify-end gap-8 pr-8">
                            <button 
                                @click="togglePostStatus(element)"
                                class="text-lg px-3 py-1 rounded-full transition-colors hover:bg-gray-100"
                                :title="element.status === 'd' ? 'Make Live' : 'Make Draft'"
                            >
                                <span :class="element.status === 'd' ? 'text-orange-500' : 'text-green-500'">
                                    {{ element.status === 'd' ? 'Draft' : 'Live' }}
                                </span>
                            </button>
                            <button 
                                @click="togglePostHidden(element)"
                                class="p-2 rounded-full group transition-colors bg-gray-100 hover:bg-gray-200"
                                :title="element.is_hidden ? 'Show post' : 'Hide post'"
                            >
                                <svg class="w-6 h-6 fill-gray-700">
                                    <use :xlink:href="element.is_hidden ? '/storage/website-files/icons.svg#ri-eye-off-line' : '/storage/website-files/icons.svg#ri-eye-line'" />
                                </svg>
                            </button>
                            <button 
                                @click.stop="openDeleteModal(element)"
                                class="p-2 rounded-full transition-colors bg-gray-100 hover:bg-red-100 border-2 border-transparent hover:border-red-200">
                                <svg class="w-6 h-6 fill-gray-700 hover:fill-red-600">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                                </svg>
                            </button>
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
                @click="fetchMorePosts">
                Load More
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import Draggable from "vuedraggable"
import CardImage from './image.vue'
import moment from 'moment'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true
    },
    community: {
        type: Object,
        required: true
    },
    title: Boolean,
    text: Boolean,
    link: Boolean,
    edit: Boolean,
    draggable: Boolean
})


// Initialize posts from paginated data
const posts = ref([])
const currentPage = ref(1)
const lastPage = ref(1)

// Watch for changes in modelValue.posts
watch(() => props.modelValue.posts, (newPosts) => {
    if (newPosts) {
        posts.value = newPosts.data || []
        currentPage.value = newPosts.current_page
        lastPage.value = newPosts.last_page
    }
}, { immediate: true })

const hasNextPage = computed(() => {
    return currentPage.value < lastPage.value
})

const fetchMorePosts = async () => {
    if (!props.modelValue?.id) return
    
    try {
        const nextPage = currentPage.value + 1
        const response = await axios.get(`/communities/${props.community.slug}/shelves/${props.modelValue.id}/paginate`, {
            params: {
                page: nextPage
            }
        })
        
        if (response.data.data) {
            posts.value = [...posts.value, ...response.data.data]
            currentPage.value = response.data.current_page
            lastPage.value = response.data.last_page
        }
    } catch (error) {
        console.error('Failed to fetch more posts:', error)
    }
}

// Update the v-model to work with the new structure
const inputVal = computed({
    get: () => ({
        ...props.modelValue,
        posts: {
            ...props.modelValue.posts,
            data: posts.value
        }
    }),
    set: (val) => {
        emit('update:modelValue', {
            ...val,
            posts: {
                ...props.modelValue.posts,
                data: val.posts
            }
        })
    }
})

const isDisabled = ref(false)
const showDelete = ref(null)
const showDeleteModal = ref(false)
const selectedPost = ref(null)
const timeout = ref(null)
const isDragging = ref(false)
const formatDate = (date) => {
    return moment(date).format('MMM D, YYYY')
}

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
        await axios.delete(`/communities/${props.community.slug}/posts/${selectedPost.value.slug}`)
        posts.value = posts.value.filter(post => post.id !== selectedPost.value.id)
        closeDeleteModal()
    } catch (error) {
        console.error('Delete failed:', error)
    }
}

const togglePostHidden = async (post) => {
    try {
        const response = await axios.patch(`/communities/${props.community.slug}/posts/${post.slug}/toggle-hidden`)
        
        // Update the post in the local posts array
        const postIndex = posts.value.findIndex(p => p.id === post.id)
        if (postIndex !== -1) {
            posts.value[postIndex].is_hidden = response.data.is_hidden
        }
        
        console.log(response.data.message)
    } catch (error) {
        console.error('Error toggling post visibility:', error)
    }
}

const togglePostStatus = async (post) => {
    try {
        const newStatus = post.status === 'd' ? 'p' : 'd'
        const response = await axios.post(`/communities/${props.community.slug}/posts/${post.slug}`, {
            status: newStatus
        })
        
        // Update the post in the local posts array
        const postIndex = posts.value.findIndex(p => p.id === post.id)
        if (postIndex !== -1) {
            posts.value[postIndex].status = response.data.status
        }
        
        console.log(`Post ${newStatus === 'p' ? 'published' : 'saved as draft'}`)
    } catch (error) {
        console.error('Error updating post status:', error)
    }
}

const updateShelfOrder = async () => {
    const list = posts.value.map((item, index) => ({
        ...item,
        order: index
    }))
    
    try {
        await axios.put(`/communities/${props.community.slug}/posts/order`, list)
    } catch (error) {
        console.error('Order update failed:', error)
    }
}

const debounce = () => {
    if (timeout.value) clearTimeout(timeout.value)
    timeout.value = setTimeout(() => {
        updateShelfOrder()
    }, 500)
}
</script>