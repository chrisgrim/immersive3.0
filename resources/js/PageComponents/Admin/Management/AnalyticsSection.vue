<template>
    <div class="analytics-section text-[#222222] space-y-[2rem]">
        <!-- Back + title -->
        <div>
            <button
                type="button"
                @click="$emit('back')"
                class="inline-flex items-center gap-[0.4rem] -ml-[0.8rem] px-[0.8rem] py-[0.6rem] rounded-full text-[1.3rem] font-semibold text-[#222222] hover:bg-[#F7F7F7]"
                aria-label="Back to the Insights dashboard"
            >
                <svg class="w-[1.6rem] h-[1.6rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                Insights
            </button>
            <h1 class="text-[2.4rem] md:text-[3.2rem] leading-[3rem] md:leading-[4rem] font-semibold tracking-[-0.02em] mt-[0.4rem]">{{ config.title }}</h1>
            <p class="text-[1.4rem] text-[#717171]">{{ config.sub }}</p>
        </div>

        <!-- Range -->
        <div class="inline-flex bg-[#F7F7F7] rounded-full p-[0.4rem]" role="group" aria-label="Date range">
            <button
                v-for="option in dayOptions"
                :key="option.days"
                type="button"
                @click="$emit('days', option.days)"
                :aria-pressed="days === option.days"
                :class="['px-[1.4rem] py-[0.8rem] rounded-full text-[1.3rem] font-semibold transition-colors',
                    days === option.days ? 'bg-white shadow-[0_2px_6px_rgba(0,0,0,0.06)] text-[#222222]' : 'text-[#717171] hover:text-[#222222]']"
            >
                {{ option.label }}
            </button>
        </div>

        <!-- Filters -->
        <div class="card p-[1.6rem] flex flex-col md:flex-row md:flex-wrap md:items-center gap-[1.2rem]">
            <input
                type="search"
                maxlength="100"
                :value="text"
                @input="(e) => { text = e.target.value; shown = PAGE }"
                :placeholder="config.placeholder"
                class="w-full md:w-[28rem] rounded-[1.2rem] border border-[#EBEBEB] px-[1.4rem] py-[1rem] text-[1.4rem] focus:outline-none focus:border-[#222222]"
            >
            <label class="flex items-center gap-[0.8rem] text-[1.3rem]">
                <span class="text-[#717171]">Sort by</span>
                <select v-model="sort" class="rounded-[1rem] border border-[#EBEBEB] px-[1rem] py-[0.8rem] text-[1.3rem] bg-white">
                    <option v-for="option in config.sorts" :key="option.key" :value="option.key">{{ option.label }}</option>
                </select>
            </label>
            <div v-if="config.kinds" class="inline-flex bg-[#F7F7F7] rounded-full p-[0.4rem] self-start" role="group" aria-label="Show">
                <button
                    v-for="option in config.kinds"
                    :key="option.key"
                    type="button"
                    @click="kind = option.key; shown = PAGE"
                    :class="['px-[1.2rem] py-[0.6rem] rounded-full text-[1.2rem] font-semibold',
                        kind === option.key ? 'bg-white shadow-[0_2px_6px_rgba(0,0,0,0.06)]' : 'text-[#717171]']"
                >
                    {{ option.label }}
                </button>
            </div>
            <label v-if="config.minViews" class="flex items-center gap-[0.8rem] text-[1.3rem]">
                <span class="text-[#717171]">At least</span>
                <select v-model.number="minViews" class="rounded-[1rem] border border-[#EBEBEB] px-[1rem] py-[0.8rem] text-[1.3rem] bg-white">
                    <option :value="0">any views</option>
                    <option :value="10">10 views</option>
                    <option :value="50">50 views</option>
                    <option :value="200">200 views</option>
                </select>
            </label>
            <label v-if="config.onlyMissed" class="flex items-center gap-[0.8rem] text-[1.3rem] cursor-pointer">
                <input type="checkbox" v-model="onlyMissed" class="w-[1.6rem] h-[1.6rem]">
                <span>Only places where a search found nothing</span>
            </label>
        </div>

        <div v-if="loading" class="flex justify-center py-12"><LoadingSpinner class="h-8 w-8 text-gray-400" /></div>
        <p v-else-if="failed" class="text-[1.6rem] text-[#E00B41]">Could not load this list. Please try again.</p>

        <template v-else>
            <p class="text-[1.3rem] text-[#717171]">
                {{ filtered.length.toLocaleString() }} {{ filtered.length === 1 ? 'row' : 'rows' }}<template v-if="filtered.length !== rows.length"> of {{ rows.length.toLocaleString() }}</template>
                <template v-if="rows.length >= limit"> (the top {{ limit }} only)</template>
            </p>

            <section class="card overflow-hidden">
                <!-- Desktop table -->
                <table v-if="visible.length" class="hidden md:table w-full text-[1.4rem]">
                    <thead class="text-[1.2rem] text-[#717171] bg-[#FCFCFC]">
                        <tr class="border-b border-[#EBEBEB]">
                            <th
                                v-for="column in config.columns"
                                :key="column.key"
                                :class="['font-normal py-[1.2rem] px-[1.6rem]', column.align === 'right' ? 'text-right' : 'text-left']"
                            >
                                <button v-if="column.sort" type="button" @click="sort = column.sort" :class="['hover:text-[#222222]', sort === column.sort ? 'text-[#222222] font-semibold' : '']">
                                    {{ column.label }}<span v-if="sort === column.sort" aria-hidden="true"> ↓</span>
                                </button>
                                <template v-else>{{ column.label }}</template>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in visible" :key="rowKey(row)" class="border-b border-[#EBEBEB] last:border-0">
                            <td v-for="column in config.columns" :key="column.key" :class="['py-[1.2rem] px-[1.6rem]', column.align === 'right' ? 'text-right' : 'text-left']">
                                <SectionCell :row="row" :column="column" :image-url="imageUrl" :format-day="formatDay" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Phone cards -->
                <ul v-if="visible.length" class="md:hidden list-none p-0 m-0">
                    <li v-for="row in visible" :key="rowKey(row)" class="px-[1.6rem] py-[1.4rem] border-b border-[#EBEBEB] last:border-0">
                        <div class="text-[1.5rem] font-semibold mb-[0.6rem]">
                            <SectionCell :row="row" :column="config.columns[0]" :image-url="imageUrl" :format-day="formatDay" />
                        </div>
                        <dl class="grid grid-cols-2 gap-x-[1.6rem] gap-y-[0.4rem] text-[1.3rem]">
                            <template v-for="column in config.columns.slice(1)" :key="column.key">
                                <dt class="text-[#717171]">{{ column.label }}</dt>
                                <dd class="text-right font-semibold"><SectionCell :row="row" :column="column" :image-url="imageUrl" :format-day="formatDay" /></dd>
                            </template>
                        </dl>
                    </li>
                </ul>

                <p v-if="!visible.length" class="empty px-[1.6rem]">{{ rows.length ? 'Nothing matches these filters.' : 'Nothing recorded in this period yet.' }}</p>
            </section>

            <button
                v-if="visible.length < filtered.length"
                type="button"
                @click="shown += PAGE"
                class="w-full md:w-auto px-[2rem] py-[1rem] rounded-[1.2rem] border border-[#222222] text-[1.4rem] font-semibold hover:bg-[#F7F7F7]"
            >
                Show {{ Math.min(PAGE, filtered.length - visible.length) }} more
            </button>

            <p v-if="config.footnote" class="text-[1.2rem] text-[#717171]">{{ config.footnote }}</p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue'

const props = defineProps({
    name: { type: String, required: true },
    days: { type: Number, required: true },
    dayOptions: { type: Array, required: true },
})

defineEmits(['back', 'days'])

const PAGE = 50
const imageUrl = import.meta.env.VITE_IMAGE_URL

const percent = (value) => (value === null || value === undefined ? 'n/a' : `${Math.round(value * 1000) / 10}%`)

const sourceNames = {
    direct: 'Direct (no link)', search_engine: 'Search engines', ai: 'AI assistants', social: 'Social media',
    referral: 'Other websites', search: 'Our search', home: 'Our homepage', community: 'Our communities',
    organizer: 'Organizer pages', event: 'Other event pages', site: 'Elsewhere on our site',
}

const regionNames = typeof Intl !== 'undefined' && Intl.DisplayNames ? new Intl.DisplayNames(['en'], { type: 'region' }) : null
const countryName = (code) => {
    try {
        return regionNames?.of(code) || code
    } catch (e) {
        return code
    }
}

const number = (value) => (value ?? 0).toLocaleString()

// What each section lists, how it can be sorted and filtered.
const SECTIONS = {
    places: {
        title: 'Top Searched Places',
        sub: 'Every place people searched for, and how often a search led to a click',
        placeholder: 'Filter places',
        onlyMissed: true,
        sorts: [
            { key: 'searches', label: 'Most searches' },
            { key: 'found_nothing', label: 'Most found nothing' },
            { key: 'clicked', label: 'Most clicked' },
            { key: 'click_rate', label: 'Highest click rate' },
            { key: 'place', label: 'Name (A-Z)' },
        ],
        columns: [
            { key: 'place', label: 'Place', sort: 'place' },
            { key: 'searches', label: 'Searches', align: 'right', sort: 'searches', format: number },
            { key: 'found_nothing', label: 'Found nothing', align: 'right', sort: 'found_nothing', format: number },
            { key: 'clicked', label: 'Clicked', align: 'right', sort: 'clicked', format: number },
            { key: 'click_rate', label: 'Rate', align: 'right', sort: 'click_rate', format: percent },
        ],
        text: (row) => row.place,
    },
    unmet: {
        title: 'Unmet Local Demand',
        sub: 'Searches that found no events: places and online types worth filling',
        placeholder: 'Filter places or types',
        kinds: [{ key: 'all', label: 'All' }, { key: 'place', label: 'Places' }, { key: 'at_home', label: 'At Home' }],
        sorts: [
            { key: 'searches', label: 'Most searches' },
            { key: 'visitors', label: 'Most visits' },
            { key: 'last_searched', label: 'Most recent' },
            { key: 'with_filters', label: 'Most with filters' },
            { key: 'place', label: 'Name (A-Z)' },
        ],
        columns: [
            { key: 'place', label: 'Place or type', sort: 'place', type: 'unmet_place' },
            { key: 'searches', label: 'Searches', align: 'right', sort: 'searches', format: number },
            { key: 'visitors', label: 'Visits', align: 'right', sort: 'visitors', format: number },
            { key: 'with_filters', label: 'With filters', align: 'right', sort: 'with_filters', format: number },
            { key: 'last_searched', label: 'Last', align: 'right', sort: 'last_searched', type: 'day' },
        ],
        text: (row) => row.place,
        footnote: '"With filters" searches had a category, genre, date or price set, which may be why nothing matched.',
    },
    at_home: {
        title: 'Top At Home Searches',
        sub: 'Online events people looked for, by type',
        placeholder: 'Filter types',
        sorts: [
            { key: 'searches', label: 'Most searches' },
            { key: 'found_nothing', label: 'Most found nothing' },
            { key: 'clicked', label: 'Most clicked' },
            { key: 'click_rate', label: 'Highest click rate' },
        ],
        columns: [
            { key: 'place', label: 'Type', sort: 'place' },
            { key: 'searches', label: 'Searches', align: 'right', sort: 'searches', format: number },
            { key: 'found_nothing', label: 'Found nothing', align: 'right', sort: 'found_nothing', format: number },
            { key: 'clicked', label: 'Clicked', align: 'right', sort: 'clicked', format: number },
            { key: 'click_rate', label: 'Rate', align: 'right', sort: 'click_rate', format: percent },
        ],
        text: (row) => row.place,
    },
    events: {
        title: 'Conversion by Event',
        sub: 'How often a view of each event became a ticket click',
        placeholder: 'Filter events by name or city',
        minViews: true,
        sorts: [
            { key: 'views', label: 'Most views' },
            { key: 'ticket_clicks', label: 'Most ticket clicks' },
            { key: 'click_through', label: 'Highest click-through' },
            { key: 'name', label: 'Name (A-Z)' },
        ],
        columns: [
            { key: 'name', label: 'Event', sort: 'name', type: 'event' },
            { key: 'views', label: 'Views', align: 'right', sort: 'views', format: number },
            { key: 'ticket_clicks', label: 'Ticket clicks', align: 'right', sort: 'ticket_clicks', format: number },
            { key: 'click_through', label: 'Click-through', align: 'right', sort: 'click_through', format: percent },
        ],
        text: (row) => `${row.name || ''} ${row.city || ''}`,
    },
    sources: {
        title: 'Where Views Came From',
        sub: 'Kinds of traffic, and every outside site that sent people to an event',
        placeholder: 'Filter sites',
        kinds: [{ key: 'all', label: 'All' }, { key: 'kind', label: 'Kinds' }, { key: 'site', label: 'Outside sites' }],
        sorts: [{ key: 'views', label: 'Most views' }, { key: 'name', label: 'Name (A-Z)' }],
        columns: [
            { key: 'name', label: 'Source', sort: 'name' },
            { key: 'group', label: 'Type' },
            { key: 'views', label: 'Views', align: 'right', sort: 'views', format: number },
        ],
        text: (row) => row.name,
    },
    countries: {
        title: 'Visits by Country',
        sub: 'One person on one day counts once',
        placeholder: 'Filter countries',
        sorts: [{ key: 'visitors', label: 'Most visits' }, { key: 'name', label: 'Name (A-Z)' }],
        columns: [
            { key: 'name', label: 'Country', sort: 'name' },
            { key: 'visitors', label: 'Visits', align: 'right', sort: 'visitors', format: number },
        ],
        text: (row) => row.name,
    },
}

const config = computed(() => SECTIONS[props.name])

const rows = ref([])
const limit = ref(500)
const loading = ref(true)
const failed = ref(false)
const text = ref('')
const sort = ref(SECTIONS[props.name].sorts[0].key)
const kind = ref('all')
const minViews = ref(0)
const onlyMissed = ref(false)
const shown = ref(PAGE)

// Section answers come in a few shapes; turn them all into flat rows.
const normalize = (name, data) => {
    if (name === 'sources') {
        return [
            ...Object.entries(data.by_kind || {}).map(([key, views]) => ({ name: sourceNames[key] || key, group: 'Kind', kindKey: 'kind', views })),
            ...Object.entries(data.outside_sites || {}).map(([site, views]) => ({ name: site, group: 'Outside site', kindKey: 'site', views })),
        ]
    }
    if (name === 'countries') {
        return Object.entries(data || {}).map(([code, visitors]) => ({ name: countryName(code), visitors }))
    }
    return data
}

const filtered = computed(() => {
    const needle = text.value.trim().toLowerCase()
    let list = rows.value
    if (needle) list = list.filter((row) => (config.value.text(row) || '').toLowerCase().includes(needle))
    if (config.value.kinds && kind.value !== 'all') list = list.filter((row) => (row.kind ?? row.kindKey) === kind.value)
    if (config.value.minViews && minViews.value) list = list.filter((row) => row.views >= minViews.value)
    if (config.value.onlyMissed && onlyMissed.value) list = list.filter((row) => row.found_nothing > 0)

    const key = sort.value
    const isText = ['place', 'name'].includes(key)
    return [...list].sort((a, b) => {
        if (isText) return String(a[key] ?? '').localeCompare(String(b[key] ?? ''))
        return (b[key] ?? -1) > (a[key] ?? -1) ? 1 : (b[key] ?? -1) < (a[key] ?? -1) ? -1 : 0
    })
})

const visible = computed(() => filtered.value.slice(0, shown.value))

const rowKey = (row) => `${row.kind ?? row.kindKey ?? ''}:${row.event_id ?? row.place ?? row.name}`

const formatDay = (date) => {
    if (!date) return ''
    const iso = date.length === 10 ? `${date}T00:00:00Z` : `${date.replace(' ', 'T')}Z`
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' })
}

// One cell: plain values, an event (photo, name, city), or an unmet line's kind.
const SectionCell = (cellProps) => {
    const { row, column } = cellProps
    if (column.type === 'event') {
        const name = row.slug
            ? h('a', { href: `/events/${row.slug}`, target: '_blank', class: 'font-semibold hover:underline block truncate' }, row.name)
            : h('span', { class: 'font-semibold text-[#717171] block truncate' }, `${row.name || `Event ${row.event_id}`} (removed)`)
        return h('div', { class: 'flex items-center gap-[1.2rem] min-w-0 text-left' }, [
            h('div', { class: 'shrink-0 w-[4rem] h-[4rem] rounded-[0.8rem] bg-[#F7F7F7] overflow-hidden' },
                row.thumb ? [h('img', { src: `${cellProps.imageUrl}${row.thumb}`, alt: '', loading: 'lazy', class: 'w-full h-full object-cover', onError: (e) => (e.target.style.display = 'none') })] : []),
            h('div', { class: 'min-w-0' }, [name, h('span', { class: 'block text-[1.2rem] text-[#717171] font-normal' }, row.city || (row.online ? 'Online' : ''))]),
        ])
    }
    if (column.type === 'unmet_place') {
        if (row.kind === 'no_place') return h('span', { class: 'italic text-[#717171]' }, 'No place typed')
        return h('span', {}, [
            row.kind === 'at_home' ? h('span', { class: 'inline-block rounded-full bg-[#F7F7F7] text-[#717171] text-[1.1rem] font-semibold px-[0.8rem] py-[0.1rem] mr-[0.6rem] align-middle' }, 'At Home') : null,
            row.place,
        ])
    }
    if (column.type === 'day') return cellProps.formatDay(row[column.key])
    return column.format ? column.format(row[column.key]) : (row[column.key] ?? '')
}
SectionCell.props = ['row', 'column', 'imageUrl', 'formatDay']

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/admin/analytics/section/${props.name}`, { params: { days: props.days } })
        rows.value = normalize(props.name, data.rows)
        limit.value = props.name === 'countries' ? 250 : 500
    } catch (error) {
        console.error('[admin-analytics] section failed', error)
        failed.value = true
    } finally {
        loading.value = false
    }
})
</script>

<style scoped>
.card {
    @apply bg-white border border-[#EBEBEB] rounded-[2rem];
    box-shadow: 0 2px 12px -2px rgba(34, 34, 34, 0.04), 0 1px 3px 0 rgba(34, 34, 34, 0.02);
}

.empty {
    @apply text-[1.4rem] text-[#717171] py-[1.6rem];
}
</style>
