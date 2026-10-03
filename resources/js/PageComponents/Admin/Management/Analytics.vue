<template>
    <div class="analytics text-[#222222] space-y-[2.4rem]">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-[1.6rem] pb-[2.4rem] border-b border-[#EBEBEB]">
            <div>
                <p class="text-[1.1rem] font-bold tracking-[0.04em] uppercase text-[#717171]">Site analytics</p>
                <h1 class="text-[3.2rem] leading-[4rem] font-semibold tracking-[-0.02em]">Insights</h1>
                <p class="text-[1.4rem] text-[#717171] mt-[0.4rem]">The site's own counts, bots left out. No cookies, and nothing here identifies a person.</p>
            </div>
            <div class="inline-flex self-start md:self-auto bg-[#F7F7F7] rounded-full p-[0.4rem]" role="group" aria-label="Date range">
                <button
                    v-for="option in dayOptions"
                    :key="option.days"
                    type="button"
                    @click="load(option.days)"
                    :aria-pressed="days === option.days"
                    :class="['px-[1.6rem] py-[0.8rem] rounded-full text-[1.3rem] font-semibold transition-colors',
                        days === option.days ? 'bg-white shadow-[0_2px_6px_rgba(0,0,0,0.06)] text-[#222222]' : 'text-[#717171] hover:text-[#222222]']"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div v-if="loading && !report" class="flex justify-center items-center py-12">
            <LoadingSpinner class="h-8 w-8 text-gray-400" />
        </div>

        <p v-else-if="failed" class="text-[1.6rem] text-[#E00B41]">Could not load analytics. Please try again.</p>

        <div v-else-if="report" :class="['space-y-[2.4rem] transition-opacity', loading ? 'opacity-50' : '']">
            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-[1.6rem]">
                <div v-for="kpi in kpis" :key="kpi.label" class="card p-[2rem]">
                    <p class="text-[1.3rem] text-[#717171]">{{ kpi.label }}</p>
                    <div class="flex items-baseline gap-[0.8rem] mt-[0.4rem]">
                        <span class="text-[2.8rem] leading-[3.4rem] font-bold tracking-[-0.02em]">{{ kpi.value }}</span>
                        <span v-if="kpi.change" :class="['text-[1.2rem] font-semibold', kpi.change.up ? 'text-[#008A05]' : 'text-[#E00B41]']">
                            {{ kpi.change.up ? '↑' : '↓' }} {{ kpi.change.text }}
                        </span>
                    </div>
                    <p class="text-[1.3rem] text-[#717171] mt-[0.4rem]">{{ kpi.note }}</p>
                </div>
            </div>

            <!-- Views over time -->
            <section class="card p-[2.4rem]">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-[0.8rem] mb-[1.6rem]">
                    <div>
                        <h2 class="section-title">Event Views Over Time</h2>
                        <p class="section-sub">People opening an event page, per day</p>
                    </div>
                </div>

                <div class="relative" @mouseleave="hoverIndex = null">
                    <svg
                        :viewBox="`0 0 ${chart.width} ${chart.height}`"
                        class="w-full h-auto block"
                        role="img"
                        :aria-label="`Event views per day for the last ${days} days`"
                        @mousemove="onChartMove"
                    >
                        <defs>
                            <linearGradient id="views-fill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#FF385C" stop-opacity="0.18" />
                                <stop offset="100%" stop-color="#FF385C" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <!-- Grid + y labels -->
                        <g v-for="tick in chart.yTicks" :key="tick.value">
                            <line :x1="chart.left" :x2="chart.width - chart.right" :y1="tick.y" :y2="tick.y" stroke="#EBEBEB" stroke-dasharray="4 4" />
                            <text :x="chart.left - 10" :y="tick.y + 4" text-anchor="end" class="axis-label">{{ tick.label }}</text>
                        </g>
                        <!-- X labels -->
                        <text v-for="tick in chart.xTicks" :key="tick.day" :x="tick.x" :y="chart.height - 6" :text-anchor="tick.anchor" class="axis-label">{{ tick.label }}</text>
                        <!-- Area + line -->
                        <path :d="chart.area" fill="url(#views-fill)" />
                        <path :d="chart.line" fill="none" stroke="#FF385C" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                        <!-- Hover crosshair -->
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
                        <div>{{ hovered.point.event_views.toLocaleString() }} event views</div>
                        <div class="text-white/70">{{ hovered.point.searches.toLocaleString() }} searches · {{ hovered.point.ticket_clicks.toLocaleString() }} ticket clicks</div>
                    </div>
                </div>

                <details class="mt-[1.2rem]">
                    <summary class="text-[1.3rem] text-[#717171] cursor-pointer">Show as a table</summary>
                    <div class="max-h-[30rem] overflow-y-auto mt-[0.8rem]">
                        <table class="w-full text-[1.3rem]">
                            <thead class="text-[#717171] text-left"><tr><th class="py-[0.4rem]">Day</th><th>Event views</th><th>Searches</th><th>Ticket clicks</th></tr></thead>
                            <tbody>
                                <tr v-for="point in report.daily" :key="point.day" class="border-t border-[#EBEBEB]">
                                    <td class="py-[0.4rem]">{{ formatDay(point.day) }}</td>
                                    <td>{{ point.event_views }}</td>
                                    <td>{{ point.searches }}</td>
                                    <td>{{ point.ticket_clicks }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>

            <!-- Searches + unmet demand -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-[2.4rem]">
                <section class="card p-[2.4rem] flex flex-col">
                    <div class="flex justify-between items-start gap-[1.6rem] mb-[1.6rem]">
                        <div>
                            <h2 class="section-title">Top Searched Places</h2>
                            <p class="section-sub">Where people look for events, and how often a search led to a click</p>
                        </div>
                        <span class="text-[1.3rem] font-semibold whitespace-nowrap">{{ report.search_clicks.searches.toLocaleString() }} searches</span>
                    </div>
                    <table v-if="report.searches.length" class="w-full text-[1.4rem]">
                        <thead class="text-[1.2rem] text-[#717171]">
                            <tr class="border-b border-[#EBEBEB]">
                                <th class="text-left font-normal py-[0.8rem]">Place</th>
                                <th class="text-right font-normal">Searches</th>
                                <th class="text-right font-normal">Clicked</th>
                                <th class="text-right font-normal">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in visibleSearches" :key="row.place" class="border-b border-[#EBEBEB] last:border-0">
                                <td class="py-[1.2rem] pr-[0.8rem]">{{ row.place }}</td>
                                <td class="text-right">{{ row.searches.toLocaleString() }}</td>
                                <td class="text-right font-semibold">{{ row.clicked.toLocaleString() }}</td>
                                <td class="text-right">{{ percent(row.click_rate) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="empty">No searches yet.</p>
                    <div v-if="report.searches.length > 6" class="flex justify-between items-center mt-auto pt-[1.6rem] border-t border-[#EBEBEB] text-[1.2rem]">
                        <span class="text-[#717171]">Showing {{ visibleSearches.length }} of {{ report.searches.length }} places</span>
                        <button type="button" class="font-semibold" @click="showAllSearches = !showAllSearches">
                            {{ showAllSearches ? 'Show fewer' : 'View all places →' }}
                        </button>
                    </div>
                </section>

                <section class="card p-[2.4rem] flex flex-col">
                    <div class="flex justify-between items-start gap-[1.6rem] mb-[1.6rem]">
                        <div>
                            <h2 class="section-title">Unmet Local Demand</h2>
                            <p class="section-sub">Searches that found no events</p>
                        </div>
                        <span class="text-[1.3rem] font-semibold whitespace-nowrap">{{ unmetTotal.toLocaleString() }} missed</span>
                    </div>
                    <ul v-if="report.zero_result_searches.length" class="list-none p-0 m-0 space-y-[0.8rem]">
                        <li v-for="row in visibleUnmet" :key="row.place" class="border border-[#EBEBEB] rounded-[1.2rem] px-[1.6rem] py-[1.2rem] flex justify-between items-center gap-[1.2rem]">
                            <div class="min-w-0">
                                <p class="text-[1.4rem] font-semibold truncate">{{ row.place }}</p>
                                <p class="text-[1.2rem] text-[#717171]">
                                    {{ row.searches }} {{ row.searches === 1 ? 'search' : 'searches' }} · {{ row.visitors }} {{ row.visitors === 1 ? 'visit' : 'visits' }} · last {{ formatDay(row.last_searched) }}
                                </p>
                            </div>
                            <span v-if="row.with_filters" class="shrink-0 rounded-full bg-[#F7F7F7] text-[#717171] text-[1.2rem] px-[1.2rem] py-[0.4rem]" :title="`${row.with_filters} of these had a filter on, which may be why`">
                                {{ row.with_filters }} filtered
                            </span>
                        </li>
                    </ul>
                    <p v-else class="empty">Every search found something.</p>
                    <button v-if="report.zero_result_searches.length > 6" type="button" class="self-start mt-[1.2rem] text-[1.2rem] font-semibold" @click="showAllUnmet = !showAllUnmet">
                        {{ showAllUnmet ? 'Show fewer' : `View all ${report.zero_result_searches.length} places →` }}
                    </button>
                    <p class="mt-auto pt-[1.6rem] border-t border-[#EBEBEB] text-[1.2rem] text-[#717171]">
                        "Filtered" searches had a category, genre, date or price set, which may be why nothing matched.
                    </p>
                </section>
            </div>

            <!-- Conversion by event -->
            <section class="card p-[2.4rem]">
                <div class="flex justify-between items-start gap-[1.6rem] mb-[0.8rem]">
                    <div>
                        <h2 class="section-title">Conversion by Event</h2>
                        <p class="section-sub">The most viewed events and how often a view became a ticket click</p>
                    </div>
                    <span class="text-[1.2rem] text-[#717171] whitespace-nowrap">Sorted by views</span>
                </div>
                <ul v-if="report.events.length" class="list-none p-0 m-0">
                    <li v-for="row in visibleEvents" :key="row.event_id" class="grid grid-cols-[4.8rem_1fr] md:grid-cols-[4.8rem_1fr_9rem_9rem_16rem] gap-x-[1.6rem] gap-y-[0.8rem] items-center py-[1.6rem] border-t border-[#EBEBEB] first:border-0">
                        <div class="w-[4.8rem] h-[4.8rem] rounded-[0.8rem] bg-[#F7F7F7] overflow-hidden">
                            <img v-if="row.thumb" :src="`${imageUrl}${row.thumb}`" alt="" loading="lazy" class="w-full h-full object-cover" @error="(e) => (e.target.style.display = 'none')">
                        </div>
                        <div class="min-w-0">
                            <a v-if="row.slug" :href="`/events/${row.slug}`" target="_blank" class="text-[1.4rem] font-semibold hover:underline block truncate">{{ row.name }}</a>
                            <span v-else class="text-[1.4rem] font-semibold text-[#717171] block truncate">{{ row.name || `Event ${row.event_id}` }} (removed)</span>
                            <p class="text-[1.2rem] text-[#717171]">{{ row.city || 'Online' }}</p>
                        </div>
                        <div class="col-start-2 md:col-start-auto flex md:block gap-[1.6rem] items-baseline">
                            <span class="block text-[1.2rem] leading-[1.6rem] text-[#717171]">Views</span>
                            <span class="block text-[1.4rem] leading-[2rem] font-semibold">{{ row.views.toLocaleString() }}</span>
                        </div>
                        <div class="col-start-2 md:col-start-auto flex md:block gap-[1.6rem] items-baseline">
                            <span class="block text-[1.2rem] leading-[1.6rem] text-[#717171] whitespace-nowrap">Ticket clicks</span>
                            <span class="block text-[1.4rem] leading-[2rem] font-semibold">{{ row.ticket_clicks.toLocaleString() }}</span>
                        </div>
                        <div class="col-start-2 md:col-start-auto">
                            <div class="flex justify-between text-[1.2rem]">
                                <span class="text-[#717171]">Click-through</span>
                                <span class="font-semibold">{{ percent(row.click_through) }}</span>
                            </div>
                            <div class="h-[0.4rem] rounded-full bg-[#EBEBEB] mt-[0.6rem]">
                                <div class="h-full rounded-full bg-[#FF385C]" :style="{ width: `${barWidth(row.click_through)}%` }"></div>
                            </div>
                        </div>
                    </li>
                </ul>
                <button v-if="report.events.length > 10" type="button" class="mt-[1.2rem] text-[1.2rem] font-semibold" @click="showAllEvents = !showAllEvents">
                    {{ showAllEvents ? 'Show fewer' : `View all ${report.events.length} events →` }}
                </button>
                <p v-else-if="!report.events.length" class="empty">No event views yet.</p>
            </section>

            <!-- Smaller breakdowns -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-[2.4rem]">
                <section class="card p-[2.4rem]">
                    <h2 class="section-title mb-[1.2rem]">Where Views Came From</h2>
                    <ul class="breakdown">
                        <li v-for="(views, kind) in report.view_sources.by_kind" :key="kind">
                            <span>{{ sourceNames[kind] || kind }}</span><span>{{ views.toLocaleString() }}</span>
                        </li>
                    </ul>
                    <template v-if="Object.keys(report.view_sources.outside_sites).length">
                        <h3 class="text-[1.3rem] font-semibold mt-[2rem] mb-[0.8rem]">Top outside sites</h3>
                        <ul class="breakdown">
                            <li v-for="(views, site) in report.view_sources.outside_sites" :key="site">
                                <span class="truncate">{{ site }}</span><span>{{ views.toLocaleString() }}</span>
                            </li>
                        </ul>
                    </template>
                </section>

                <section class="card p-[2.4rem]">
                    <h2 class="section-title mb-[0.4rem]">Search Result Clicks</h2>
                    <p class="section-sub mb-[1.2rem]">
                        {{ percent(report.search_clicks.click_rate) }} of searches led to a click
                        ({{ report.search_clicks.searches_with_a_click.toLocaleString() }} of {{ report.search_clicks.searches.toLocaleString() }}).
                        Clicks after moving the map are not included.
                    </p>
                    <ul class="breakdown">
                        <li v-for="(clicks, position) in report.search_clicks.by_position" :key="position">
                            <span>Result #{{ position }}</span><span>{{ clicks.toLocaleString() }}</span>
                        </li>
                    </ul>
                </section>

                <section class="card p-[2.4rem]">
                    <h2 class="section-title mb-[0.4rem]">Visits by Country</h2>
                    <p class="section-sub mb-[1.2rem]">One person on one day counts once</p>
                    <ul class="breakdown">
                        <li v-for="(visitors, country) in report.countries" :key="country">
                            <span>{{ countryName(country) }}</span><span>{{ visitors.toLocaleString() }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <p class="text-[1.2rem] text-[#717171]">
                Bots left out: {{ report.bots.flagged.toLocaleString() }} of {{ report.bots.all_rows.toLocaleString() }} hits ({{ percent(report.bots.share) }}):
                {{ report.bots.datacenter.toLocaleString() }} from cloud networks, {{ report.bots.crawler.toLocaleString() }} declared crawlers,
                {{ report.bots.over_daily_cap.toLocaleString() }} over the daily limit, {{ report.bots.no_user_agent.toLocaleString() }} with no browser name.
                IP geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline">DB-IP</a>.
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue'

const dayOptions = [
    { days: 7, label: '7 days' },
    { days: 30, label: '30 days' },
    { days: 90, label: '90 days' },
]
const days = ref(30)
const report = ref(null)
const loading = ref(true)
const failed = ref(false)
const hoverIndex = ref(null)
const showAllSearches = ref(false)
const showAllEvents = ref(false)
const showAllUnmet = ref(false)

const imageUrl = import.meta.env.VITE_IMAGE_URL

const sourceNames = {
    direct: 'Direct (no link)',
    search_engine: 'Search engines',
    ai: 'AI assistants',
    social: 'Social media',
    referral: 'Other websites',
    search: 'Our search',
    home: 'Our homepage',
    community: 'Our communities',
    organizer: 'Organizer pages',
    event: 'Other event pages',
    site: 'Elsewhere on our site',
}

const total = (type, field = 'total', key = 'totals') => report.value?.[key]?.[type]?.[field] ?? 0

const percent = (value) => (value === null || value === undefined ? 'n/a' : `${Math.round(value * 1000) / 10}%`)

// Change against the same span just before; nothing to compare with when
// the earlier span had none.
const change = (now, before) => {
    if (!before) return null
    const pct = Math.round(((now - before) / before) * 100)
    return { up: pct >= 0, text: `${Math.abs(pct)}%` }
}

const ctr = (key) => {
    const views = total('event_view', 'total', key)
    return views ? total('ticket_click', 'total', key) / views : null
}

const unmetTotal = computed(() => report.value?.zero_result_total ?? 0)

const kpis = computed(() => {
    const views = total('event_view')
    const clicks = total('ticket_click')
    const rate = ctr('totals')
    const previousRate = ctr('totals_previous')
    const searches = report.value?.search_clicks?.searches ?? 0

    return [
        {
            label: 'Event Page Views',
            value: views.toLocaleString(),
            change: change(views, total('event_view', 'total', 'totals_previous')),
            note: `${total('event_view', 'visitors').toLocaleString()} visits (one person, one day)`,
        },
        {
            label: 'Ticket Clicks',
            value: clicks.toLocaleString(),
            change: change(clicks, total('ticket_click', 'total', 'totals_previous')),
            note: `vs ${total('ticket_click', 'total', 'totals_previous').toLocaleString()} the ${days.value} days before`,
        },
        {
            label: 'Click-Through Rate',
            value: percent(rate),
            change: rate !== null && previousRate !== null
                ? { up: rate >= previousRate, text: `${Math.abs(Math.round((rate - previousRate) * 1000) / 10)} pts` }
                : null,
            note: 'Ticket clicks per event page view',
        },
        {
            label: 'Searches That Found Nothing',
            value: unmetTotal.value.toLocaleString(),
            change: null,
            note: searches ? `${percent(unmetTotal.value / searches)} of ${searches.toLocaleString()} searches` : 'No searches yet',
        },
    ]
})

const visibleSearches = computed(() => (showAllSearches.value ? report.value.searches : report.value.searches.slice(0, 6)))
const visibleEvents = computed(() => (showAllEvents.value ? report.value.events : report.value.events.slice(0, 10)))
const visibleUnmet = computed(() => (showAllUnmet.value ? report.value.zero_result_searches : report.value.zero_result_searches.slice(0, 6)))

const barWidth = (rate) => {
    const best = Math.max(...report.value.events.map((row) => row.click_through || 0))
    return best ? Math.round(((rate || 0) / best) * 100) : 0
}

// ---- Chart: one series (event views), plain SVG ----
const chart = computed(() => {
    const width = 1000
    const height = 260
    const left = 44
    const right = 12
    const top = 16
    const bottom = 28
    const points = report.value?.daily || []
    const max = Math.max(1, ...points.map((point) => point.event_views))
    const step = niceStep(max / 3)
    const yMax = Math.max(step, Math.ceil(max / step) * step)
    const plotWidth = width - left - right
    const plotHeight = height - top - bottom
    const x = (i) => left + (points.length > 1 ? (i / (points.length - 1)) * plotWidth : plotWidth / 2)
    const y = (value) => top + plotHeight - (value / yMax) * plotHeight

    const coords = points.map((point, i) => ({ x: x(i), y: y(point.event_views), point }))
    const line = coords.map((c, i) => `${i ? 'L' : 'M'}${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ')
    const area = coords.length
        ? `${line} L${coords[coords.length - 1].x.toFixed(1)},${y(0)} L${coords[0].x.toFixed(1)},${y(0)} Z`
        : ''

    const yTicks = []
    for (let value = 0; value <= yMax; value += step) {
        yTicks.push({ value, y: y(value), label: value >= 1000 ? `${Math.round(value / 100) / 10}k` : String(value) })
    }

    const xCount = Math.min(5, points.length)
    const xTicks = xCount > 1
        ? Array.from({ length: xCount }, (_, k) => {
            const i = Math.round((k / (xCount - 1)) * (points.length - 1))
            return { day: points[i].day, x: x(i), label: formatDay(points[i].day), anchor: k === 0 ? 'start' : k === xCount - 1 ? 'end' : 'middle' }
        })
        : []

    return { width, height, left, right, top, bottom, coords, line, area, yTicks, xTicks }
})

const niceStep = (raw) => {
    const power = 10 ** Math.floor(Math.log10(Math.max(raw, 1)))
    const scaled = raw / power
    return (scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10) * power
}

const hovered = computed(() => (hoverIndex.value === null ? null : chart.value.coords[hoverIndex.value] || null))

const onChartMove = (event) => {
    const coords = chart.value.coords
    if (!coords.length) return
    const box = event.currentTarget.getBoundingClientRect()
    const svgX = ((event.clientX - box.left) / box.width) * chart.value.width
    let nearest = 0
    coords.forEach((c, i) => {
        if (Math.abs(c.x - svgX) < Math.abs(coords[nearest].x - svgX)) nearest = i
    })
    hoverIndex.value = nearest
}

const formatDay = (date) => {
    if (!date) return ''
    const iso = date.length === 10 ? `${date}T00:00:00Z` : `${date.replace(' ', 'T')}Z`
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' })
}

const regionNames = typeof Intl !== 'undefined' && Intl.DisplayNames ? new Intl.DisplayNames(['en'], { type: 'region' }) : null
const countryName = (code) => {
    try {
        return regionNames?.of(code) || code
    } catch (e) {
        return code
    }
}

// Only the newest request may land: a slow 90-day answer arriving after a
// quick 7-day one must not replace it.
let latest = 0

const load = async (option = days.value) => {
    const request = ++latest
    days.value = option
    loading.value = true
    failed.value = false
    hoverIndex.value = null
    try {
        const { data } = await axios.get('/api/admin/analytics', { params: { days: option } })
        if (request === latest) report.value = data
    } catch (error) {
        if (request !== latest) return
        console.error('[admin-analytics] failed to load', error)
        failed.value = true
    } finally {
        if (request === latest) loading.value = false
    }
}

onMounted(() => load())
</script>

<style scoped>
.card {
    @apply bg-white border border-[#EBEBEB] rounded-[2.4rem];
    box-shadow: 0 2px 12px -2px rgba(34, 34, 34, 0.04), 0 1px 3px 0 rgba(34, 34, 34, 0.02);
}

.section-title {
    @apply text-[1.8rem] leading-[2.4rem] font-semibold tracking-[-0.01em];
}

.section-sub {
    @apply text-[1.3rem] text-[#717171];
}

.empty {
    @apply text-[1.4rem] text-[#717171] py-[1.6rem];
}

.breakdown {
    @apply list-none p-0 m-0;
}

.breakdown li {
    @apply flex justify-between gap-[1.6rem] text-[1.4rem] py-[0.6rem] border-b border-[#EBEBEB] last:border-0;
}

.axis-label {
    font-size: 11px;
    fill: #717171;
}
</style>
