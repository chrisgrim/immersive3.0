<template>
    <div>
        <blockquote v-if="blockquote" class="text-3.5xl md:text-2.5xl px-4 py-2">
            <span :class="[bodyClass, needsShowMore && showMore ? 'mb-8 block' : '']" :style="`white-space: ${whiteSpace};`">"{{ headText }}<span v-show="!collapsed">{{ tailText }}</span><span v-if="collapsed">...</span>"</span>
            <div v-if="needsShowMore" v-show="showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer mt-2">Show Less</div>
            <div v-if="needsShowMore" v-show="lessButton && !showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer mt-2">Show More</div>
        </blockquote>
        <div v-else class="text-3.5xl md:text-2.5xl leading-normal md:leading-9">
            <p class="transform-none" :class="needsShowMore && showMore ? 'mb-8' : 'mb-0'">
                <span :class="[bodyClass]" :style="`white-space: ${whiteSpace};`">{{ headText }}<span v-show="!collapsed">{{ tailText }}</span><span v-if="collapsed">...</span></span>
            </p>
            <div v-if="needsShowMore" v-show="showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer mt-2">Show Less</div>
            <div v-if="needsShowMore" v-show="lessButton && !showMore" @click.prevent="toggleShowMore" class="text-2.5xl md:text-xl text-[#008489] font-semibold cursor-pointer mt-2">Show More</div>
        </div>
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
        // The whole text is always in the page, the rest only hidden while
        // collapsed: Google reads the rendered page, and a sliced string left
        // it with the first N words of every description.
        // What the reader sees right now (head plus "..." while collapsed).
        adjustedText() {
            return this.collapsed ? this.headText + '...' : this.text || '';
        },
        collapsed() {
            return this.needsShowMore && !this.showMore;
        },
        headText() {
            if (!this.text) return '';
            return this.needsShowMore ? this.text.split(' ').slice(0, this.limit).join(' ') : this.text;
        },
        tailText() {
            if (!this.needsShowMore) return '';
            return ' ' + this.text.split(' ').slice(this.limit).join(' ');
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
