<template>
    <div>
        <blockquote v-if="blockquote" class="px-4 py-2">
            <span :class="[bodyClass]" :style="`white-space: ${whiteSpace};`">"{{ adjustedText }}"</span>
            <span v-show="showMore" @click.prevent="toggleShowMore" class="text-xl text-[#008489] font-semibold cursor-pointer"><br><span>Show Less</span></span>
            <span v-show="lessButton && !showMore && this.text.split(' ').length > this.limit" @click.prevent="toggleShowMore" class="text-xl text-[#008489] font-semibold cursor-pointer"><br>Show More</span>
        </blockquote>
        <p v-else class="text">
            <span :class="[bodyClass]" :style="`white-space: ${whiteSpace};`">{{ adjustedText }}</span>
            <span v-show="showMore" @click.prevent="toggleShowMore" class="text-xl text-[#008489] font-semibold cursor-pointer"><br><span>Show Less</span></span>
            <span v-show="lessButton && !showMore && this.text.split(' ').length > this.limit" @click.prevent="toggleShowMore" class="text-xl text-[#008489] font-semibold cursor-pointer"><br>Show More</span>
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
        adjustedText() {
            return this.text.split(' ').length > this.limit && !this.showMore
                ? this.text.split(' ').slice(0, this.limit).join(' ') + '...'
                : this.text;
        },
    },

    data() {
        return {
            showMore: this.text.split(' ').length > this.limit ? false : true,
        }
    },
    methods: {
        toggleShowMore() {
            this.showMore = !this.showMore;
        }
    }
}
</script>
