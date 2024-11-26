<template>
    <div class="card">
        <a 
            aria-label="Visit Listing"
            v-if="hasUrl"
            :rel="internalUrl"
            :href="hasUrl"
            :class="[ hasImage ? 'h-80 md:h-[42rem]' : 'h-full' ]"
            class="w-full absolute rounded-xl z-10 top-0 left-0 block" />
        <template v-if="hasImage">
            <div class="h-80 relative rounded-2xl overflow-hidden md:h-[42rem]">
                <picture>
                    <source 
                        type="image/webp" 
                        :srcset="`${imageUrl}${hasImage}`"> 
                    <img 
                        loading="lazy"
                        class="w-full rounded-2xl align-bottom object-cover h-full"
                        :src="`${imageUrl}${hasImage.slice(0, -4)}jpg`" 
                        :alt="`${card.name}`">
                </picture>
            </div>
        </template>
        <template v-if="hasName">
            <h3 class="mt-8 text-4xl">{{ hasName }}</h3>
        </template>
        <template v-if="card.event_id">
            <p class="mt-4 text-1xl">Booking Through: {{ cleanDate(card.event.closingDate) }}</p>
        </template>
        <template v-if="card.blurb">
            <p 
                class="mt-4 card-blurb" 
                v-html="card.blurb" />
        </template>
        <template v-if="hasUrl">
            <div class="mt-8 mb-16">
                <a :rel="internalUrl" :href="hasUrl">
                    <button class="px-4 py-2 text-white bg-black border-white inline-block rounded-full hover:bg-white hover:text-black hover:border-black z-50">
                        Check it out
                    </button>
                </a>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import moment from 'moment'

const props = defineProps({
    card: {
        type: Object,
        required: true
    },
    mobile: {
        type: Boolean,
        default: false
    }
})

const imageUrl = import.meta.env.VITE_IMAGE_URL

// Helper methods
const getImage = () => {
    return props.mobile 
        ? props.card.thumbImagePath 
        : props.card.largeImagePath 
            ? props.card.largeImagePath 
            : props.card.thumbImagePath
}

const getEventImage = () => {
    if (!props.card.event) return null
    
    return props.mobile 
        ? props.card.event.thumbImagePath 
        : props.card.event.largeImagePath
}

const cleanDate = (date) => {
    return moment(date).format("dddd, MMMM D YYYY")
}

// Computed properties
const hasImage = computed(() => {
    if (props.card.type === 'i') return getImage()
    if (props.card.type === 'h' || props.card.type === 't') return null
    if (props.card.type === 'e') {
        return (!props.card.thumbImagePath && props.card.event) 
            ? getEventImage() 
            : getImage()
    }
    return null
})

const hasName = computed(() => 
    props.card.event && !props.card.name 
        ? props.card.event.name 
        : props.card.name
)

const hasUrl = computed(() => 
    props.card.event && !props.card.url 
        ? `/events/${props.card.event.slug}` 
        : props.card.url
)

const internalUrl = computed(() => 
    props.card.event && !props.card.url 
        ? '' 
        : 'noopener noreferrer nofollow'
)
</script>

<style scoped>
/* Target h3 and h4 specifically within the card-blurb class */
:deep(.card-blurb) h1 {
  font-size: 2.5rem; /* 30px */
  line-height: 2.4rem; /* 36px */
}
:deep(.card-blurb) h2 {
  font-size: 2.25rem; /* 30px */
  line-height: 2.25rem; /* 36px */
}
:deep(.card-blurb) h3 {
  font-size: 2rem; /* 30px */
  line-height: 2rem; /* 36px */
}

:deep(.card-blurb) h4 {
  font-size: 1.5rem; /* 24px */
  line-height: 2rem; /* 32px */
}
</style>