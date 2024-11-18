<template>
    <div class="m-auto gap-6 whitespace-nowrap overflow-y-hidden overflow-x-auto w-full md:whitespace-normal md:overflow-visible">
        <div>
            <draggable
                v-if="posts"
                style="scroll-snap-type: x mandatory;" 
                class="flex w-full scroll-p-7 overflow-visible mt-8 scroll-smooth md:flex-wrap"
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
                        class="relative w-full flex flex-[1_0_calc(100%-6rem)] snap-start snap-always px-4 first:ml-[-1rem] last:mr-[-1rem] md:flex-[0_1_33.3333333333%] md:w-4/12 lg:flex-[0_1_25%] lg:w-3/12">
                        <button 
                            v-if="showDelete === index && value.id !== archived?.id"
                            @click="selectedModal = post"
                            class="absolute top-[-1rem] z-20 right-[-.4rem] items-center justify-center rounded-full p-0 w-12 h-12 flex border-2 bg-white border-black hover:bg-black hover:fill-white">
                            <svg class="w-12 h-12">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                            </svg>
                        </button>
                        <div class="flex w-full flex-col overflow-hidden relative">
                            <a 
                                :href="`/communities/${community.slug}/${post.slug}/edit`" 
                                class="block h-full absolute w-full rounded-2xl top-0 left-0 z-10" />
                            <CardImage 
                                :community="community"
                                :element="post" />
                            <div class="mb-8 mt-2">
                                <div 
                                    v-if="title"
                                    class="mt-4 overflow-hidden text-ellipsis max-h-16">
                                    <p> {{ post.name }} <span v-if="post.status === 'd'">(Not Live)</span></p>
                                </div>
                                <div 
                                    v-if="text"
                                    class="blurb">
                                    <p> {{ post.description }} </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </draggable>
        </div>
        <VueArchiveModal 
            v-if="selectedModal"
            :item="selectedModal"
            :strict="true"
            body="You are archiving the Post"
            @close="selectedModal = null"
            @archive="onArchive" />
        <div 
            v-if="value?.posts?.next_page_url"
            class="loadmore">
            <button 
                class="rounded-full py-2 px-4 bg-white hover:bg-black hover:text-white hover:border-black"
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
import VueArchiveModal from '@/GlobalComponents/Modals/archive.vue'

const props = defineProps({
    loadposts: Array,
    title: Boolean,
    text: Boolean,
    link: Boolean,
    community: Object,
    edit: Boolean,
    draggable: Boolean,
    value: Object,
    archived: Object
})

const emit = defineEmits(['input', 'updated'])

const isDisabled = ref(false)
const showDelete = ref(null)
const posts = ref(props.loadposts)
const selectedModal = ref(null)
const timeout = ref(null)
const isDragging = ref(false)

const inputVal = computed({
    get: () => props.value,
    set: (val) => emit('input', val)
})

const onArchive = async () => {
    selectedModal.value.shelf_id = props.archived.id
    await update()
}

const update = async () => {
    try {
        const res = await axios.put(
            `/communities/${props.community.slug}/${selectedModal.value.slug}/update`, 
            selectedModal.value
        )
        console.log(res.data)
        location.reload()
        // posts.value = posts.value.filter(post => post.id !== selectedModal.value.id)
        // selectedModal.value = null
    } catch (error) {
        console.error('Update failed:', error)
    }
}

const updateShelfOrder = async () => {
    const list = posts.value.map((item, index) => ({
        ...item,
        order: index
    }))
    
    try {
        await axios.put(`/posts/${props.community.slug}/order`, list)
        emit('updated')
    } catch (error) {
        console.error('Order update failed:', error)
    }
}

const fetchPosts = async () => {
    try {
        const res = await axios.get(`/shelves/${props.value.id}/paginate?page=${props.value.posts.next_page_url.slice(-1)}`)
        inputVal.value.posts = res.data
        posts.value = [...posts.value, ...res.data.data]
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