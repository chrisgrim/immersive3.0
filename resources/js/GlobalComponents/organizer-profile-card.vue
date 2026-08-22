<template>
    <!-- Profile Card — exact markup from Organizer/show.vue's own Profile Card,
         extracted here so every place that shows an organizer's card (the
         organizer page itself, the Liked Events detail drill-down, etc.)
         renders the literal same component instead of a redrawn lookalike. -->
    <div class="flex flex-row shadow-custom-6 w-full p-8 py-16 rounded-3xl gap-8">
        <!-- Left Column - Image and Name -->
        <div class="flex flex-col items-center w-2/3">
            <!-- Profile Image -->
            <div class="w-44 flex-shrink-0">
                <div class="relative w-full">
                    <div class="relative w-full aspect-square">
                        <div class="w-full h-full rounded-full overflow-hidden shadow-sm">
                            <picture v-if="organizerImage">
                                <source
                                    type="image/webp"
                                    :srcset="`${organizerImage}`"
                                >
                                <img
                                    class="w-full h-full object-cover"
                                    :src="`${organizerImage}`"
                                    :alt="`${organizer.name} organizer`"
                                >
                            </picture>
                            <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <span class="text-6xl font-bold text-gray-400">
                                    {{ organizer.name?.charAt(0).toUpperCase() || '?' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Name -->
            <div class="w-full flex justify-center px-4">
                <p class="text-3xl text-black font-medium leading-tight mt-8 text-center break-words hyphens-auto md:max-w-[25rem] overflow-hidden">
                    {{ organizer.name }}
                </p>
            </div>
        </div>

        <!-- Right Column - Info -->
        <div class="flex-1 flex flex-col space-y-8 m-auto">
            <!-- Stats -->
            <div class="flex flex-col items-start">
                <p class="text-5xl font-semibold text-gray-900">
                    {{ organizer.events_count || organizer.events?.length || 0 }}
                </p>
                <p class="text-md font-bold text-gray-600">
                    Events
                </p>
            </div>

            <div class="w-24 h-px bg-gray-200"></div>

            <div class="flex flex-col items-start">
                <p class="text-5xl font-semibold text-gray-900">
                    {{ calculateYearsOnEI }}
                </p>
                <p class="text-md font-bold text-gray-600">
                    Years on EI
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    organizer: {
        type: Object,
        required: true,
    },
});

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL);

const organizerImage = computed(() => {
    const firstImage = props.organizer.images?.[0];
    if (firstImage) {
        return `${imageUrl.value}${firstImage.large_image_path}`;
    }

    if (props.organizer.thumbImagePath) {
        return `${imageUrl.value}${props.organizer.thumbImagePath}`;
    }

    return null;
});

const calculateYearsOnEI = computed(() => {
    const joinDate = new Date(props.organizer.created_at);
    const now = new Date();
    const years = now.getFullYear() - joinDate.getFullYear();

    if (
        now.getMonth() < joinDate.getMonth()
        || (now.getMonth() === joinDate.getMonth() && now.getDate() < joinDate.getDate())
    ) {
        return years - 1;
    }
    return years;
});
</script>
