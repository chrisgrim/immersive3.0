<template>
    <div class="space-y-[2.4rem]">
        <p v-if="!data.has_data" class="card p-[2.4rem] empty">No data yet. Google's numbers are imported every night.</p>

        <template v-else>
            <p class="section-sub -mt-[1.2rem]">
                {{ formatDay(data.period.from) }} to {{ formatDay(data.period.to) }}<template v-if="kpiNote">. {{ kpiNote }}</template>
            </p>

            <!-- Performance: like Google Search Console, each card turns its
                 line on the chart on or off. -->
            <section class="card">
                <div class="grid grid-cols-2 lg:grid-cols-4 overflow-hidden rounded-t-[2.4rem]">
                    <button
                        v-for="metric in metrics"
                        :key="metric.key"
                        type="button"
                        @click="toggle(metric.key)"
                        :aria-pressed="shown.includes(metric.key)"
                        :class="['text-left p-[1.6rem] md:p-[2rem] transition-colors border-b lg:border-b-0 border-[#EBEBEB]',
                            shown.includes(metric.key) ? 'text-white' : 'bg-white text-[#222222] hover:bg-[#F7F7F7]']"
                        :style="shown.includes(metric.key) ? { backgroundColor: metric.color } : {}"
                    >
                        <span class="flex items-center gap-[0.6rem] text-[1.3rem] font-semibold">
                            <span
                                class="inline-flex items-center justify-center w-[1.6rem] h-[1.6rem] rounded-[0.4rem] border-2 shrink-0"
                                :style="{ borderColor: shown.includes(metric.key) ? '#FFFFFF' : metric.color }"
                                aria-hidden="true"
                            >
                                <svg v-if="shown.includes(metric.key)" class="w-[1.2rem] h-[1.2rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7" /></svg>
                            </span>
                            {{ metric.label }}
                        </span>
                        <span class="block text-[2.8rem] leading-[3.4rem] font-bold tracking-[-0.02em] mt-[0.8rem]">{{ metric.value }}</span>
                        <span
                            v-if="metric.change"
                            :class="['block text-[1.2rem] font-semibold mt-[0.2rem]', shown.includes(metric.key) ? 'text-white/90' : (metric.change.good ? 'text-[#008A05]' : 'text-[#E00B41]')]"
                        >
                            {{ metric.change.up ? '↑' : '↓' }} {{ metric.change.text }}
                        </span>
                        <span v-else class="block text-[1.2rem] mt-[0.2rem] opacity-0" aria-hidden="true">.</span>
                    </button>
                </div>

                <div class="p-[1.6rem] md:p-[2.4rem] border-t border-[#EBEBEB]">
                    <p v-if="!shown.length" class="empty text-center">Choose a number above to chart it.</p>
                    <div v-else class="relative" @mouseleave="hoverIndex = null">
                        <svg
                            :viewBox="`0 0 ${chart.width} ${chart.height}`"
                            class="w-full h-auto block"
                            role="img"
                            :aria-label="`${shownLabels} from Google, ${formatDay(data.period.from)} to ${formatDay(data.period.to)}`"
                            @pointermove="(event) => (hoverIndex = nearestIndex(chart, event))"
                            @pointerdown="(event) => (hoverIndex = nearestIndex(chart, event))"
                        >
                            <template v-if="chart.lines.length === 1">
                                <g v-for="tick in chart.lines[0].ticks" :key="tick.value">
                                    <line :x1="chart.left" :x2="chart.width - chart.right" :y1="tick.y" :y2="tick.y" stroke="#EBEBEB" stroke-dasharray="4 4" />
                                    <text :x="chart.left - 10" :y="tick.y + 4" text-anchor="end" class="axis-label">{{ tick.label }}</text>
                                </g>
                            </template>
                            <template v-else>
                                <line v-for="k in 4" :key="k" :x1="chart.left" :x2="chart.width - chart.right" :y1="chart.top + ((k - 1) / 3) * (chart.height - chart.top - chart.bottom)" :y2="chart.top + ((k - 1) / 3) * (chart.height - chart.top - chart.bottom)" stroke="#EBEBEB" stroke-dasharray="4 4" />
                            </template>
                            <text v-for="tick in chart.xTicks" :key="tick.day" :x="tick.x" :y="chart.height - 6" :text-anchor="tick.anchor" class="axis-label">{{ tick.label }}</text>
                            <path
                                v-for="line in chart.lines"
                                :key="line.key"
                                :d="line.path"
                                fill="none"
                                :stroke="colorOf(line.key)"
                                stroke-width="2"
                                stroke-linejoin="round"
                                stroke-linecap="round"
                            />
                            <template v-for="line in chart.lines" :key="`dots-${line.key}`">
                                <circle v-for="(dot, i) in line.dots" :key="i" :cx="dot.x" :cy="dot.y" r="3" :fill="colorOf(line.key)" />
                            </template>
                            <g v-if="hoverIndex !== null">
                                <line :x1="chart.coords[hoverIndex].x" :x2="chart.coords[hoverIndex].x" :y1="chart.top" :y2="chart.height - chart.bottom" stroke="#222222" stroke-opacity="0.2" />
                                <template v-for="line in chart.lines" :key="line.key">
                                    <circle v-if="line.coords[hoverIndex]" :cx="line.coords[hoverIndex].x" :cy="line.coords[hoverIndex].y" r="5" :fill="colorOf(line.key)" stroke="#FFFFFF" stroke-width="2" />
                                </template>
                            </g>
                        </svg>
                        <div
                            v-if="hoverPoint"
                            class="absolute top-0 pointer-events-none bg-[#222222] text-white rounded-[0.8rem] px-[1.2rem] py-[0.8rem] text-[1.2rem] leading-[1.8rem] whitespace-nowrap"
                            :style="tooltipStyle"
                        >
                            <div class="font-semibold">{{ formatDay(hoverPoint.day) }}</div>
                            <div v-for="metric in shownMetrics" :key="metric.key" class="flex items-center gap-[0.6rem]">
                                <span class="w-[0.8rem] h-[0.8rem] rounded-full" :style="{ backgroundColor: metric.color }" aria-hidden="true"></span>
                                {{ metric.label }}: {{ metric.format(hoverPoint[metric.key]) }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-[2.4rem]">
                <!-- Top searches -->
                <section class="card p-[2.4rem] flex flex-col">
                    <div class="mb-[1.6rem]">
                        <button type="button" class="section-link" @click="$emit('open', 'google_queries')">
                            <span class="section-title">Top Google Searches</span>
                            <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                        <p class="section-sub">What people typed into Google before clicking through</p>
                    </div>
                    <table v-if="data.queries.length" class="w-full text-[1.4rem]">
                        <thead class="text-[1.2rem] text-[#717171]">
                            <tr class="border-b border-[#EBEBEB]">
                                <th class="text-left font-normal py-[0.8rem]">Search</th>
                                <th class="text-right font-normal">Clicks</th>
                                <th class="text-right font-normal hidden sm:table-cell">Shown</th>
                                <th class="text-right font-normal">Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data.queries" :key="row.query" class="border-b border-[#EBEBEB] last:border-0">
                                <td class="py-[1.2rem] pr-[0.8rem] break-words">{{ row.query }}</td>
                                <td class="text-right font-semibold">{{ row.clicks.toLocaleString() }}</td>
                                <td class="text-right hidden sm:table-cell">{{ row.impressions.toLocaleString() }}</td>
                                <td class="text-right">{{ position(row.position) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="empty">No searches listed yet.</p>
                </section>

                <!-- Top pages -->
                <section class="card p-[2.4rem] flex flex-col">
                    <div class="mb-[1.6rem]">
                        <button type="button" class="section-link" @click="$emit('open', 'google_pages')">
                            <span class="section-title">Pages Google Sends People To</span>
                            <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                        <p class="section-sub">Events and other pages, by clicks from Google</p>
                    </div>
                    <ul v-if="data.pages.length" class="list-none p-0 m-0">
                        <li v-for="row in data.pages" :key="row.page" class="flex items-center gap-[1.2rem] py-[1.2rem] border-t border-[#EBEBEB] first:border-0">
                            <div v-if="row.kind === 'event' || row.kind === 'organizer'" class="shrink-0 w-[4rem] h-[4rem] rounded-[0.8rem] bg-[#F7F7F7] overflow-hidden">
                                <img v-if="row.thumb" :src="`${imageUrl}${row.thumb}`" alt="" loading="lazy" class="w-full h-full object-cover" @error="(e) => (e.target.style.display = 'none')">
                            </div>
                            <div class="min-w-0 flex-1">
                                <a v-if="linkable(row.page)" :href="row.page" target="_blank" rel="noopener" class="text-[1.4rem] font-semibold hover:underline block truncate">{{ row.name || row.page }}</a>
                                <span v-else class="text-[1.4rem] font-semibold block truncate">{{ row.name || row.page }}</span>
                                <p class="text-[1.2rem] text-[#717171] truncate">{{ pageNote(row) }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="block text-[1.4rem] font-semibold">{{ row.clicks.toLocaleString() }}</span>
                                <span class="block text-[1.2rem] text-[#717171]">{{ percent(row.ctr) }} of {{ row.impressions.toLocaleString() }}</span>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="empty">No pages listed yet.</p>
                </section>
            </div>

            <p class="text-[1.2rem] text-[#717171]">{{ data.note }} {{ data.pages_note }} Position 1 is the top result.</p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { multiLineChart, nearestIndex, formatDay } from './analyticsChart.js'

const props = defineProps({
    data: { type: Object, required: true },
})

defineEmits(['open'])

const imageUrl = import.meta.env.VITE_IMAGE_URL
const hoverIndex = ref(null)

const percent = (value) => (value === null || value === undefined ? 'n/a' : `${Math.round(value * 1000) / 10}%`)
const position = (value) => (value === null || value === undefined ? 'n/a' : value.toFixed(1))

// Only our own paths and web addresses become links.
const linkable = (page) => /^(\/|https?:\/\/)/.test(page || '')

const pageNote = (row) => {
    if (row.kind === 'event') return row.page
    if (row.kind === 'gone') return 'Not a live event or organizer page'
    if (row.kind === 'organizer') return `Organizer · ${row.page}`
    return `Position ${position(row.position)}`
}

// Change against the period before. For position lower is better, so the
// arrow shows the direction and the colour whether that is good.
const change = (now, before, { points = false, lowerIsBetter = false } = {}) => {
    if (now === null || before === null || before === undefined || (!points && !before)) return null
    const diff = points ? now - before : (now - before) / before
    // Rounded as shown: a change that rounds to 0 gets no badge.
    const shown = points
        ? (lowerIsBetter ? Math.round(diff * 10) / 10 : Math.round(diff * 1000) / 10)
        : Math.round(diff * 100)
    if (shown === 0) return null
    const up = shown > 0
    const text = points && !lowerIsBetter ? `${Math.abs(shown)} pts` : `${Math.abs(shown)}${points ? '' : '%'}`
    return { up, good: lowerIsBetter ? !up : up, text }
}

// previous is null when the days before start before the first import:
// no change is shown rather than one against missing days.
const compare = (key, options) => (props.data.previous ? change(props.data.totals[key], props.data.previous[key], options) : null)

const kpiNote = computed(() => {
    if (!props.data.totals) return ''
    return props.data.previous
        ? `Changes are against the ${props.data.days} days before`
        : `No earlier data (imported since ${formatDay(props.data.period?.data_since)})`
})

// Search Console's four colours, darkened so white text on a selected
// card stays readable (4.5:1 or better).
const metrics = computed(() => {
    const now = props.data.totals || {}
    return [
        { key: 'clicks', label: 'Clicks', color: '#1A73E8', value: (now.clicks ?? 0).toLocaleString(), change: compare('clicks'), format: (v) => (v ?? 0).toLocaleString() },
        { key: 'impressions', label: 'Impressions', color: '#5E35B1', value: (now.impressions ?? 0).toLocaleString(), change: compare('impressions'), format: (v) => (v ?? 0).toLocaleString() },
        { key: 'ctr', label: 'Click rate', color: '#00796B', value: percent(now.ctr), change: compare('ctr', { points: true }), format: percent },
        { key: 'position', label: 'Average position', color: '#B45309', value: position(now.position), change: compare('position', { points: true, lowerIsBetter: true }), format: position },
    ]
})

// Clicks and impressions start on, as in Search Console.
const shown = ref(['clicks', 'impressions'])
const toggle = (key) => {
    shown.value = shown.value.includes(key) ? shown.value.filter((k) => k !== key) : [...shown.value, key]
    hoverIndex.value = null
}
const shownMetrics = computed(() => metrics.value.filter((metric) => shown.value.includes(metric.key)))
const shownLabels = computed(() => shownMetrics.value.map((metric) => metric.label).join(', '))
const colorOf = (key) => metrics.value.find((metric) => metric.key === key)?.color

const chart = computed(() => multiLineChart(
    props.data.daily || [],
    shownMetrics.value.map((metric) => ({
        key: metric.key,
        // A day with no impressions has no click rate or position.
        value: (point) => (['ctr', 'position'].includes(metric.key) && !point.impressions ? null : point[metric.key]),
        invert: metric.key === 'position',
        format: metric.key === 'ctr' ? (v) => `${Math.round(v * 1000) / 10}%` : undefined,
    })),
))

const hoverPoint = computed(() => (hoverIndex.value === null ? null : chart.value.coords[hoverIndex.value]?.point || null))

// The tooltip sits in the top corner away from the hovered day, so it
// never covers it and never runs off a narrow chart.
const tooltipStyle = computed(() => {
    const x = (chart.value.coords[hoverIndex.value]?.x || 0) / chart.value.width
    return x > 0.5 ? { left: '0' } : { right: '0' }
})
</script>

<style scoped>
.card {
    @apply bg-white border border-[#EBEBEB] rounded-[2.4rem];
    box-shadow: 0 2px 12px -2px rgba(34, 34, 34, 0.04), 0 1px 3px 0 rgba(34, 34, 34, 0.02);
}

.section-title {
    @apply text-[1.8rem] leading-[2.4rem] font-semibold tracking-[-0.01em];
}

.section-link {
    @apply flex flex-wrap items-center gap-x-[0.8rem] gap-y-[0.2rem] text-left -mx-[0.6rem] px-[0.6rem] py-[0.2rem] rounded-[0.8rem] hover:bg-[#F7F7F7];
}

.section-chevron {
    @apply w-[1.6rem] h-[1.6rem] shrink-0 text-[#717171];
}

.section-sub {
    @apply text-[1.3rem] text-[#717171];
}

.empty {
    @apply text-[1.4rem] text-[#717171] py-[1.6rem];
}

.axis-label {
    font-size: 11px;
    fill: #717171;
}
</style>
