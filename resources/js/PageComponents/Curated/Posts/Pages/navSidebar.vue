<template>
    <nav class="relative flex flex-col items-center flex-shrink-0 w-full mx-auto pt-8 md:pt-12">
        <!-- Header with Community Name and Controls -->
        <div class="w-full flex flex-col gap-8 md:gap-4 pb-8 z-50 bg-white p-10 lg-air:max-w-[40rem]">
            <a 
                :href="`/communities/${community?.slug}/listings?shelf=${post?.shelf?.id}`" 
                class="flex items-center gap-4"
            >
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors flex-shrink-0">
                    <svg 
                        class="w-8 h-8" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2" 
                        stroke-linecap="round" 
                        stroke-linejoin="round"
                    >
                        <path d="M19 12H5"/>
                        <path d="M12 19l-7-7 7-7"/>
                    </svg>
                </div>
                <span class="ml-4 text-4xl md:text-5xl font-semibold truncate">
                    {{ post?.shelf?.name || community?.name }}
                </span>
            </a>

            <!-- Post Controls -->
            <div class="flex flex-col md:flex-row gap-8 items-start md:items-center justify-between mt-8">
                <!-- Left side controls - now in a row -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <ToggleSwitch
                        v-model="isLive"
                        left-label="Draft"
                        right-label="Live"
                        text-size="xl"
                        @update:modelValue="handleStatusChange" 
                    />
                    <div class="flex items-center gap-2">
                        <!-- Visibility Toggle -->
                        <button 
                            @click="toggleHidden"
                            class="inline-flex items-center justify-center w-16 h-16 min-w-16 min-h-16 rounded-full transition-colors bg-gray-100 hover:bg-gray-200"
                            :title="post?.is_hidden ? 'Show post' : 'Hide post'"
                        >
                            <svg 
                                class="w-8 h-8 fill-gray-700"
                                viewBox="0 0 24 24"
                            >
                                <use :xlink:href="post?.is_hidden ? '/storage/website-files/icons.svg#ri-eye-off-line' : '/storage/website-files/icons.svg#ri-eye-line'" />
                            </svg>
                        </button>
                        
                        <!-- View Live Post -->
                        <a 
                            v-if="isLive"
                            :href="`/communities/${community?.slug}/posts/${post?.slug}`"
                            class="inline-flex items-center justify-center w-16 h-16 min-w-16 min-h-16 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors"
                            title="View live post"
                        >
                            <component :is="RiExternalLinkLine" class="w-8 h-8 text-neutral-800" />
                        </a>
                    </div>
                </div>

                <!-- Right side shelf selector - now with proper width constraints -->
                <div @click.stop class="w-full md:max-w-[50%]">
                    <div v-if="!selectedShelf" class="w-full">
                        <Dropdown
                            :list="shelves"
                            :placeholder="'Select Shelf'"
                            @onSelect="handleShelfSelect" 
                        />
                    </div>
                    <DropdownList 
                        v-else
                        :selections="[selectedShelf]"
                        :show-remove="true"
                        :maxLines="1"
                        :maxWidth="'100%'"
                        :itemWidth="'100%'"
                        @onSelect="removeShelf" 
                    />
                </div>
            </div>
        </div>

        <!-- Navigation Items -->
        <div class="w-full flex flex-col md:items-center overflow-y-auto max-h-[calc(100vh-28rem)]">
            <div class="space-y-10 lg-air:max-w-[40rem] p-10 mb-20">
                <!-- Name Section -->
                <button
                    @click="$emit('navigate', 'Name')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Name',
                            'border border-neutral-200': currentStep !== 'Name'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Name</h3>
                    <p class="text-4xl text-neutral-600 mb-4 break-words hyphens-auto overflow-hidden">
                        {{ post?.name || 'No name set' }}
                    </p>
                    <p class="text-neutral-500 text-2xl line-clamp-3 leading-tight break-words hyphens-auto overflow-hidden">
                        {{ post?.blurb || 'No description set' }}
                    </p>
                </button>

                <!-- Image Section -->
                <button
                    @click="$emit('navigate', 'Image')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Image',
                            'border border-neutral-200': currentStep !== 'Image'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Image</h3>
                    <div class="flex justify-center mb-8">
                        <div v-if="postImage" class="w-full max-w-[400px] relative">
                            <div class="aspect-[16/9] rounded-xl overflow-hidden">
                                <img 
                                    :src="postImage"
                                    class="w-full h-full object-cover"
                                    alt="Post image"
                                />
                            </div>
                        </div>
                        <div 
                            v-else 
                            class="w-full max-w-[400px] aspect-[16/9] rounded-xl bg-neutral-200 flex items-center justify-center"
                        >
                            <span class="text-2xl font-bold text-neutral-400">
                                {{ post?.name?.charAt(0).toUpperCase() || '?' }}
                            </span>
                        </div>
                    </div>
                </button>

                <!-- Content Section -->
                <button
                    @click="$emit('navigate', 'Content')"
                    :class="[
                        'w-full p-8 text-left rounded-3xl cursor-pointer hover:bg-neutral-50 transition-all duration-200',
                        {
                            'border-[#222222] shadow-focus-black bg-neutral-50': currentStep === 'Content',
                            'border border-neutral-200': currentStep !== 'Content'
                        }
                    ]"
                >
                    <h3 class="text-xl font-semibold mb-4">Content</h3>
                    <p class="text-neutral-500">
                        {{ post?.cards?.length || 0 }} blocks
                    </p>
                </button>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { RiExternalLinkLine } from '@remixicon/vue';
import ToggleSwitch from '@/GlobalComponents/toggle-switch.vue';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import DropdownList from '@/GlobalComponents/dropdown-list.vue';

const props = defineProps({
    post: {
        type: Object,
        required: true
    },
    community: {
        type: Object,
        required: true
    },
    currentStep: {
        type: String,
        default: null
    },
    shelves: {
        type: Array,
        required: true
    }
});

const emit = defineEmits(['navigate', 'statusChange', 'shelfChange', 'hiddenChange']);

const isMobile = computed(() => window?.Laravel?.isMobile ?? false);
const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);
const isLive = computed(() => props.post?.status === 'p');

const selectedShelf = computed(() => {
    if (!props.post.shelf_id) return null;
    return props.shelves.find(s => s.id === props.post.shelf_id) || null;
});

const postImage = computed(() => {
    if (props.post?.event_id) {
        return props.post.featured_event_image?.largeImagePath 
            ? `${imageUrl.value}${props.post.featured_event_image.largeImagePath}`
            : null;
    } else if (props.post?.images?.length > 0) {
        return `${imageUrl.value}${props.post.images[0].large_image_path}`;
    } else if (props.post?.largeImagePath) {
        return `${imageUrl.value}${props.post.largeImagePath}`;
    }
    return null;
});

const handleShelfSelect = (shelf) => {
    emit('shelfChange', shelf.id);
    setTimeout(() => {
        window.location.reload();
    }, 500);
};

const removeShelf = () => {
    emit('shelfChange', null);
};

const handleStatusChange = (value) => {
    emit('statusChange', value ? 'p' : 'd');
};

const toggleHidden = async () => {
    try {
        const response = await axios.patch(`/communities/${props.community?.slug}/posts/${props.post?.slug}/toggle-hidden`);
        emit('hiddenChange', response.data.is_hidden);
        
        // Optional: Show a toast notification
        console.log(response.data.message);
    } catch (error) {
        console.error('Error toggling post visibility:', error);
        // Handle error - maybe show a toast notification
    }
};
</script>