<template>
    <div class="space-y-[2.4rem]">
        <div class="pt-[0.8rem]">
            <h2 class="text-[2.2rem] leading-[2.8rem] font-semibold tracking-[-0.01em]">From Google</h2>
            <p class="section-sub mt-[0.2rem]">
                What people searched on Google before landing here (Google Search Console).
                <template v-if="data.has_data">{{ formatDay(data.period.from) }} to {{ formatDay(data.period.to) }}.</template>
            </p>
        </div>

        <p v-if="!data.has_data" class="card p-[2.4rem] empty">No data yet. Google's numbers are imported every night.</p>

        <template v-else>
            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[1.6rem]">
                <div v-for="kpi in kpis" :key="kpi.label" class="card p-[2rem]">
                    <p class="text-[1.3rem] text-[#717171]">{{ kpi.label }}</p>
                    <div class="flex items-baseline gap-[0.8rem] mt-[0.4rem]">
                        <span class="text-[2.8rem] leading-[3.4rem] font-bold tracking-[-0.02em]">{{ kpi.value }}</span>
                        <span v-if="kpi.change" :class="['text-[1.2rem] font-semibold', kpi.change.good ? 'text-[#008A05]' : 'text-[#E00B41]']">
                            {{ kpi.change.up ? '↑' : '↓' }} {{ kpi.change.text }}
                        </span>
                    </div>
                    <p class="text-[1.3rem] text-[#717171] mt-[0.4rem]">{{ kpi.note }}</p>
                </div>
            </div>

            <!-- Clicks over time -->
            <section class="card p-[2.4rem]">
                <div class="mb-[1.6rem]">
                    <h3 class="section-title">Clicks from Google</h3>
                    <p class="section-sub">People clicking through from a Google search, per day</p>
                </div>
                <div class="relative" @mouseleave="hoverIndex = null">
                    <svg
                        :viewBox="`0 0 ${chart.width} ${chart.height}`"
                        class="w-full h-auto block"
                        role="img"
                        :aria-label="`Clicks from Google per day, ${formatDay(data.period.from)} to ${formatDay(data.period.to)}`"
                        @mousemove="(event) => (hoverIndex = nearestIndex(chart, event))"
                    >
                        <defs>
                            <linearGradient id="google-clicks-fill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#FF385C" stop-opacity="0.18" />
                                <stop offset="100%" stop-color="#FF385C" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <g v-for="tick in chart.yTicks" :key="tick.value">
                            <line :x1="chart.left" :x2="chart.width - chart.right" :y1="tick.y" :y2="tick.y" stroke="#EBEBEB" stroke-dasharray="4 4" />
                            <text :x="chart.left - 10" :y="tick.y + 4" text-anchor="end" class="axis-label">{{ tick.label }}</text>
                        </g>
                        <text v-for="tick in chart.xTicks" :key="tick.day" :x="tick.x" :y="chart.height - 6" :text-anchor="tick.anchor" class="axis-label">{{ tick.label }}</text>
                        <path :d="chart.area" fill="url(#google-clicks-fill)" />
                        <path :d="chart.line" fill="none" stroke="#FF385C" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                        <g v-if="hovered">
                            <line :x1="hovered.x" :x2="hovered.x" :y1="chart.top" :y2="chart.height - chart.bottom" stroke="#222222" stroke-opacity="0.2" />
                            <circle :cx="hovered.x" :cy="hovered.y" r="5" fill="#FF385C" stroke="#FFFFFF" stroke-width="2" />
                        </g>
                    </svg>
                    <div
                        v-if="hovered"
                        class="absolute pointer-events-none bg-[#222222] text-white rounded-[0.8rem] px-[1.2rem] py-[0.8rem] text-[1.2rem] leading-[1.6rem] whitespace-nowrap -translate-x-1/2 -translate-y-full"
                        :style="{ left: `${(hovered.x / chart.width) * 100}%`, top: `${(hovered.y / chart.height) * 100}%`, marginTop: '-1.2rem' }"
                    >
                        <div class="font-semibold">{{ formatDay(hovered.point.day) }}</div>
                        <div>{{ hovered.point.clicks.toLocaleString() }} clicks</div>
                        <div class="text-white/70">{{ hovered.point.impressions.toLocaleString() }} impressions</div>
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
import { lineChart, nearestIndex, formatDay } from './analyticsChart.js'

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
    if (row.kind === 'gone') return 'Not a current event page'
    if (row.kind === 'organizer') return `Organizer · ${row.page}`
    return `Position ${position(row.position)}`
}

// Change against the period before. For position lower is better, so the
// arrow shows the direction and the colour whether that is good.
const change = (now, before, { points = false, lowerIsBetter = false } = {}) => {
    if (now === null || before === null || before === undefined || (!points && !before)) return null
    const diff = points ? now - before : (now - before) / before
    const up = diff >= 0
    const text = points
        ? (lowerIsBetter ? `${Math.abs(Math.round(diff * 10) / 10)}` : `${Math.abs(Math.round(diff * 1000) / 10)} pts`)
        : `${Math.abs(Math.round(diff * 100))}%`
    return { up, good: lowerIsBetter ? !up : up, text }
}

// previous is null when the days before start before the first import:
// no change is shown rather than one against missing days.
const kpis = computed(() => {
    const now = props.data.totals
    const before = props.data.previous
    const days = props.data.days
    const since = formatDay(props.data.period?.data_since)
    const compare = (key, options) => (before ? change(now[key], before[key], options) : null)

    return [
        {
            label: 'Clicks from Google',
            value: now.clicks.toLocaleString(),
            change: compare('clicks'),
            note: before ? `vs ${before.clicks.toLocaleString()} the ${days} days before` : `No earlier data (imported since ${since})`,
        },
        {
            label: 'Impressions',
            value: now.impressions.toLocaleString(),
            change: compare('impressions'),
            note: 'Times the site showed up in results',
        },
        {
            label: 'Click-Through Rate',
            value: percent(now.ctr),
            change: compare('ctr', { points: true }),
            note: 'Clicks per impression',
        },
        {
            label: 'Average Position',
            value: position(now.position),
            change: compare('position', { points: true, lowerIsBetter: true }),
            note: '1 is the top result; lower is better',
        },
    ]
})

const chart = computed(() => lineChart(props.data.daily || [], (point) => point.clicks))
const hovered = computed(() => (hoverIndex.value === null ? null : chart.value.coords[hoverIndex.value] || null))
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
