<template>
    <div>
        <blockquote v-if="blockquote" class="text-3.5xl md:text-2.5xl px-4 py-2">
            <span :class="[bodyClass]" :style="`white-space: ${whiteSpace};`">"{{ adjustedText }}"</span>
            <span v-if="needsShowMore" v-show="showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer"><br><span>Show Less</span></span>
            <span v-if="needsShowMore" v-show="lessButton && !showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer"><br>Show More</span>
        </blockquote>
        <p v-else class="text-3.5xl md:text-2.5xl leading-normal md:leading-9">
            <span :class="[bodyClass]" :style="`white-space: ${whiteSpace};`">{{ adjustedText }}</span>
            <span v-if="needsShowMore" v-show="showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer"><br><span>Show Less</span></span>
            <span v-if="needsShowMore" v-show="lessButton && !showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer"><br>Show More</span>
        </p>
    </div>
</template>

<script>
export default {
    props: {
        text: {
            type: String,
            default: ''
        },
        bodyClass: {
            type: String,
            default: ''
        },
        limit: {
            type: Number,
            default: 100,
        },
        whiteSpace: {
            type: String,
            default: 'pre-wrap'
        },
        lessButton: {
            type: Boolean,
            default: true
        },
        blockquote: {
            type: Boolean,
            default: false
        },
    },

    computed: {
        needsShowMore() {
            return this.text && this.text.split(' ').length > this.limit;
        },
        adjustedText() {
            if (!this.text) return '';
            return this.needsShowMore && !this.showMore
                ? this.text.split(' ').slice(0, this.limit).join(' ') + '...'
                : this.text;
        },
    },

    data() {
        return {
            showMore: false
        }
    },

    methods: {
        toggleShowMore() {
            this.showMore = !this.showMore;
        }
    }
}
</script>
