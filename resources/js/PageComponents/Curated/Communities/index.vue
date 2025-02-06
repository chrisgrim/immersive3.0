<template>
    <div class="flex justify-end">
        <div class="px-8 md:px-32 w-full ml-[-2rem] mt-20 md:mt-12">
            <!-- Title and Add Button -->
            <div class="w-full flex items-center justify-between mb-8">
                <h2 class="font-medium">My Communities</h2>
                <a href="/communities/create" class="cursor-pointer">
                    <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center text-5xl font-light hover:bg-gray-200">
                        +
                    </div>
                </a>
            </div>

            <div class="w-full">
                <!-- Header Row -->
                <div class="grid gap-8 py-4 items-center grid-cols-[4rem_auto] md:grid-cols-[4rem_30%_auto_auto]">
                    <div>
                        <h5 class="font-medium">Image</h5>
                    </div>
                    <div class="hidden md:block">
                        <h5 class="font-medium">Name</h5>
                    </div>
                    <div class="hidden md:block">
                        <h5 class="font-medium">Status</h5>
                    </div>
                    <div class="hidden md:block">
                        <h5 class="font-medium">Created</h5>
                    </div>
                </div>
            </div>

            <!-- Communities List -->
            <div class="w-full">
                <div 
                    v-for="community in communities" 
                    :key="community.id"
                    class="group relative grid grid-cols-2 md:grid-cols-4 gap-8 p-4 items-center hover:bg-gray-100 rounded-2xl grid-cols-[4rem_auto] md:grid-cols-[4rem_30%_auto_auto]"
                >
                    <!-- Community Image -->
                    <div>
                        <picture v-if="community.images?.[0]?.large_image_path || community.largeImagePath">
                            <source 
                                :srcset="`${imageUrl}${community.images?.[0]?.large_image_path || community.largeImagePath}?timestamp=${community.updated_at}`" 
                                type="image/webp"
                            >
                            <img 
                                :src="`${imageUrl}${(community.images?.[0]?.large_image_path || community.largeImagePath).slice(0, -4)}jpg?timestamp=${community.updated_at}`"
                                :alt="`${community.name} image`"
                                class="h-16 w-full object-cover rounded-2xl"
                            >
                        </picture>
                        <div v-else class="h-16 w-full rounded-2xl bg-gray-300"></div>
                    </div>

                    <!-- Community Name -->
                    <div>
                        <div class="flex items-center gap-2">
                            <a 
                                :href="`/communities/${community.slug}`" 
                                class="text-2xl font-medium hover:underline"
                            >
                                {{ community.name }}
                            </a>
                        </div>
                        <p class="text-md leading-4 text-gray-500 mt-1">
                            {{ community.blurb }}
                        </p>
                    </div>

                    <!-- Status -->
                    <div class="hidden md:block">
                        <div class="flex flex-col">
                            <p class="text-lg text-gray-500">
                                {{ community.status === 'n' ? 'Changes Requested' : isPublic(community) ? 'Published' : 'Under Review' }}
                            </p>
                            <p v-if="!isPublic(community) && community.name_change_requests?.length" 
                               class="text-sm text-gray-400 italic mt-1">
                                Name change requested
                            </p>
                            <p v-else-if="!isPublic(community) && community.status === 'p'" 
                               class="text-sm text-gray-400 italic mt-1">
                                Pending initial review
                            </p>
                        </div>
                    </div>

                    <!-- Created Date -->
                    <div class="hidden md:block">
                        <p class="text-lg text-gray-500">
                            {{ new Date(community.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                        </p>
                    </div>

                    <!-- Arrow Icon -->
                    <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24" 
                        stroke="currentColor"
                        class="hidden md:absolute right-6 top-1/2 transform -translate-y-1/2 w-8 h-8 opacity-0 group-hover:opacity-100"
                        stroke-width="3"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const imageUrl = import.meta.env.VITE_IMAGE_URL

const props = defineProps({
    user: {
        type: Object,
        default: () => null
    },
    communities: {
        type: Array,
        required: true
    }
})

const isPublic = (community) => {
    return community.status === 'p'
}
</script>