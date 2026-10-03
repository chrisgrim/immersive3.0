<template>
    <AnalyticsSection
        v-if="section"
        :key="section"
        :name="section"
        :days="days"
        :day-options="dayOptions"
        @back="closeSection"
        @days="(option) => (days = option)"
    />
    <div v-else class="analytics text-[#222222] space-y-[2.4rem]">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-[1.6rem] pb-[2.4rem] border-b border-[#EBEBEB]">
            <div>
                <p class="text-[1.1rem] font-bold tracking-[0.04em] uppercase text-[#717171]">Site analytics</p>
                <h1 class="text-[3.2rem] leading-[4rem] font-semibold tracking-[-0.02em]">Insights</h1>
                <p class="text-[1.4rem] text-[#717171] mt-[0.4rem]">The site's own counts, bots left out, no cookies. Place names are what visitors typed.</p>
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
                <div class="mb-[1.6rem]">
                    <h2 class="section-title">Event Views Over Time</h2>
                    <p class="section-sub">People opening an event page, per day</p>
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
                        <g v-for="tick in chart.yTicks" :key="tick.value">
                            <line :x1="chart.left" :x2="chart.width - chart.right" :y1="tick.y" :y2="tick.y" stroke="#EBEBEB" stroke-dasharray="4 4" />
                            <text :x="chart.left - 10" :y="tick.y + 4" text-anchor="end" class="axis-label">{{ tick.label }}</text>
                        </g>
                        <text v-for="tick in chart.xTicks" :key="tick.day" :x="tick.x" :y="chart.height - 6" :text-anchor="tick.anchor" class="axis-label">{{ tick.label }}</text>
                        <path :d="chart.area" fill="url(#views-fill)" />
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
                    <div class="mb-[1.6rem]">
                        <button type="button" class="section-link" @click="openSection('places')">
                            <span class="section-title">Top Searched Places</span>
                            <span class="section-count">{{ report.search_clicks.searches.toLocaleString() }} searches</span>
                            <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                        <p class="section-sub">Where people look for events, and how often a search led to a click</p>
                    </div>
                    <table v-if="placeRows.length" class="w-full text-[1.4rem]">
                        <thead class="text-[1.2rem] text-[#717171]">
                            <tr class="border-b border-[#EBEBEB]">
                                <th class="text-left font-normal py-[0.8rem]">Place</th>
                                <th class="text-right font-normal">Searches</th>
                                <th class="text-right font-normal">Clicked</th>
                                <th class="text-right font-normal">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in placeRows" :key="row.place" class="border-b border-[#EBEBEB] last:border-0">
                                <td class="py-[1.2rem] pr-[0.8rem]">{{ row.place }}</td>
                                <td class="text-right">{{ row.searches.toLocaleString() }}</td>
                                <td class="text-right font-semibold">{{ row.clicked.toLocaleString() }}</td>
                                <td class="text-right">{{ percent(row.click_rate) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="empty">No searches yet.</p>
                </section>

                <section class="card p-[2.4rem] flex flex-col">
                    <div class="mb-[1.6rem]">
                        <button type="button" class="section-link" @click="openSection('unmet')">
                            <span class="section-title">Unmet Local Demand</span>
                            <span class="section-count">{{ unmetTotal.toLocaleString() }} missed</span>
                            <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                        <p class="section-sub">Searches that found no events</p>
                    </div>
                    <ul v-if="unmetRows.length" class="list-none p-0 m-0 space-y-[0.8rem]">
                        <li v-for="row in unmetRows" :key="`${row.kind}:${row.place}`" class="border border-[#EBEBEB] rounded-[1.2rem] px-[1.6rem] py-[1.2rem] flex justify-between items-center gap-[1.2rem]">
                            <div class="min-w-0">
                                <p class="text-[1.4rem] font-semibold truncate">
                                    <span v-if="row.kind === 'at_home'" class="inline-block rounded-full bg-[#F7F7F7] text-[#717171] text-[1.1rem] font-semibold px-[0.8rem] py-[0.1rem] mr-[0.6rem] align-middle">At Home</span><span v-if="row.kind === 'no_place'" class="text-[#717171] italic">No place typed</span><template v-else>{{ row.place }}</template>
                                </p>
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
                    <p class="mt-auto pt-[1.6rem] border-t border-[#EBEBEB] text-[1.2rem] text-[#717171]">
                        "Filtered" searches had a category, genre, date or price set, which may be why nothing matched.
                    </p>
                </section>
            </div>

            <!-- At Home -->
            <section class="card p-[2.4rem]">
                <div class="mb-[1.6rem]">
                    <button type="button" class="section-link" @click="openSection('at_home')">
                        <span class="section-title">Top At Home Searches</span>
                        <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                    </button>
                    <p class="section-sub">Online events people looked for, by type</p>
                </div>
                <div v-if="report.at_home_searches?.length" class="overflow-x-auto">
                    <table class="w-full text-[1.4rem]">
                        <thead class="text-[1.2rem] text-[#717171]">
                            <tr class="border-b border-[#EBEBEB]">
                                <th class="text-left font-normal py-[0.8rem]">Type</th>
                                <th class="text-right font-normal">Searches</th>
                                <th class="text-right font-normal">Found nothing</th>
                                <th class="text-right font-normal">Clicked</th>
                                <th class="text-right font-normal">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in report.at_home_searches.slice(0, 8)" :key="row.place" class="border-b border-[#EBEBEB] last:border-0">
                                <td class="py-[1.2rem] pr-[0.8rem]">{{ row.place }}</td>
                                <td class="text-right">{{ row.searches.toLocaleString() }}</td>
                                <td class="text-right">{{ row.found_nothing.toLocaleString() }}</td>
                                <td class="text-right font-semibold">{{ row.clicked.toLocaleString() }}</td>
                                <td class="text-right">{{ percent(row.click_rate) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="empty">No At Home searches yet.</p>
            </section>

            <!-- Conversion by event -->
            <section class="card p-[2.4rem]">
                <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-[1.2rem] mb-[0.8rem]">
                    <div>
                        <button type="button" class="section-link" @click="openSection('events')">
                            <span class="section-title">Conversion by Event</span>
                            <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                        <p class="section-sub">The most viewed events and how often a view became a ticket click</p>
                    </div>
                    <div class="inline-flex self-start bg-[#F7F7F7] rounded-full p-[0.4rem]" role="group" aria-label="Sort events by">
                        <button
                            v-for="option in eventSorts"
                            :key="option.key"
                            type="button"
                            @click="eventSort = option.key"
                            :aria-pressed="eventSort === option.key"
                            :class="['px-[1.2rem] py-[0.6rem] rounded-full text-[1.2rem] font-semibold whitespace-nowrap', eventSort === option.key ? 'bg-white shadow-[0_2px_6px_rgba(0,0,0,0.06)]' : 'text-[#717171]']"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
                <ul v-if="eventRows.length" class="list-none p-0 m-0">
                    <li v-for="row in eventRows" :key="row.event_id" class="grid grid-cols-[4.8rem_1fr] md:grid-cols-[4.8rem_1fr_9rem_9rem_16rem] gap-x-[1.6rem] gap-y-[0.8rem] items-center py-[1.6rem] border-t border-[#EBEBEB] first:border-0">
                        <div class="w-[4.8rem] h-[4.8rem] rounded-[0.8rem] bg-[#F7F7F7] overflow-hidden">
                            <img v-if="row.thumb" :src="`${imageUrl}${row.thumb}`" alt="" loading="lazy" class="w-full h-full object-cover" @error="(e) => (e.target.style.display = 'none')">
                        </div>
                        <div class="min-w-0">
                            <a v-if="row.slug" :href="`/events/${row.slug}`" target="_blank" class="text-[1.4rem] font-semibold hover:underline block truncate">{{ row.name }}</a>
                            <span v-else class="text-[1.4rem] font-semibold text-[#717171] block truncate">{{ row.name || `Event ${row.event_id}` }} (removed)</span>
                            <p class="text-[1.2rem] text-[#717171]">{{ row.city || (row.online ? 'Online' : '') }}</p>
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
                <p v-else-if="eventSort === 'click_through' && report.events.length" class="empty">No event has 10 or more views in this period yet.</p>
                <p v-else class="empty">No event views yet.</p>
                <p v-if="eventSort === 'click_through' && eventRows.length" class="text-[1.2rem] text-[#717171] mt-[0.8rem]">Events with at least 10 views.</p>
            </section>

            <!-- Google Search Console -->
            <AnalyticsGoogle v-if="google?.configured" :data="google" @open="openSection" />

            <!-- Smaller breakdowns -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-[2.4rem]">
                <section class="card p-[2.4rem]">
                    <div class="mb-[1.2rem]">
                        <button type="button" class="section-link" @click="openSection('sources')">
                            <span class="section-title">Where Views Came From</span>
                            <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                    </div>
                    <ul class="breakdown">
                        <li v-for="(views, kind) in report.view_sources.by_kind" :key="kind">
                            <span>{{ sourceNames[kind] || kind }}</span><span>{{ views.toLocaleString() }}</span>
                        </li>
                    </ul>
                    <template v-if="Object.keys(report.view_sources.outside_sites).length">
                        <h3 class="text-[1.3rem] font-semibold mt-[2rem] mb-[0.8rem]">Top outside sites</h3>
                        <ul class="breakdown">
                            <li v-for="(views, site) in topSites" :key="site">
                                <span class="truncate">{{ site }}</span><span>{{ views.toLocaleString() }}</span>
                            </li>
                        </ul>
                    </template>
                </section>

                <section class="card p-[2.4rem]">
                    <h2 class="section-title mb-[0.4rem]">Do Searches Work?</h2>
                    <p class="text-[1.4rem] mb-[0.4rem]">
                        <span class="font-semibold">{{ report.search_clicks.searches_with_a_click.toLocaleString() }} of {{ report.search_clicks.searches.toLocaleString() }}</span>
                        searches ({{ percent(report.search_clicks.click_rate) }}) ended with someone opening an event.
                    </p>
                    <p class="section-sub mb-[1.6rem]">Which result they opened:</p>
                    <ul class="list-none p-0 m-0 space-y-[0.8rem]">
                        <li v-for="row in positionRows" :key="row.position">
                            <div class="flex justify-between text-[1.3rem]">
                                <span>{{ row.label }}</span>
                                <span><span class="font-semibold">{{ row.clicks.toLocaleString() }}</span> <span class="text-[#717171]">({{ row.share }})</span></span>
                            </div>
                            <div class="h-[0.6rem] rounded-full bg-[#EBEBEB] mt-[0.4rem]">
                                <div class="h-full rounded-full bg-[#FF385C]" :style="{ width: `${row.width}%` }"></div>
                            </div>
                        </li>
                    </ul>
                    <p v-if="!positionRows.length" class="empty">No result clicks yet.</p>
                    <p class="text-[1.2rem] text-[#717171] mt-[1.6rem]">Moving the map counts as browsing, not a new search, so it is left out.</p>
                </section>

                <section class="card p-[2.4rem]">
                    <button type="button" class="section-link" @click="openSection('countries')">
                        <span class="section-title">Visits by Country</span>
                        <svg class="section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                    </button>
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
                {{ report.bots.over_daily_cap.toLocaleString() }} over the daily limit, {{ report.bots.no_user_agent.toLocaleString() }} with no browser name,
                {{ (report.bots.odd_headers ?? 0).toLocaleString() }} missing what real browsers send.
                IP geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline">DB-IP</a>.
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue'
import AnalyticsSection from './AnalyticsSection.vue'
import AnalyticsGoogle from './AnalyticsGoogle.vue'
import { lineChart, nearestIndex, formatDay } from './analyticsChart.js'

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

const eventSort = ref('views')
const eventSorts = [
    { key: 'views', label: 'Views' },
    { key: 'ticket_clicks', label: 'Ticket clicks' },
    { key: 'click_through', label: 'Click-through' },
]

// A section opened on its own page (?section=places), with Back to return.
const SECTIONS = ['places', 'unmet', 'at_home', 'events', 'sources', 'countries', 'google_queries', 'google_pages']
const sectionFromUrl = () => {
    const name = new URLSearchParams(window.location.search).get('section')
    return SECTIONS.includes(name) ? name : null
}
const section = ref(sectionFromUrl())

const openSection = (name) => {
    const url = new URL(window.location)
    url.searchParams.set('section', name)
    window.history.pushState({ analyticsSection: name }, '', url)
    section.value = name
    window.scrollTo(0, 0)
}

const closeSection = () => {
    // Opened from the dashboard: step back in history, so the browser's own
    // Back and this arrow agree. Opened straight from a link: drop the param.
    if (window.history.state?.analyticsSection) {
        window.history.back()
        return
    }
    const url = new URL(window.location)
    url.searchParams.delete('section')
    window.history.replaceState({}, '', url)
    section.value = null
    showDashboard()
}

const onPopState = () => {
    section.value = sectionFromUrl()
    showDashboard()
}

// The dashboard's report is loaded only while the dashboard is shown, and
// again if the range was changed on a section page meanwhile.
const showDashboard = () => {
    if (!section.value && report.value?.days !== days.value) load()
}

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

// The dashboard shows the top of each list; its section page shows it all.
const placeRows = computed(() => report.value.searches.slice(0, 6))
const unmetRows = computed(() => report.value.zero_result_searches.slice(0, 6))
const topSites = computed(() => Object.fromEntries(Object.entries(report.value.view_sources.outside_sites).slice(0, 8)))

// The dashboard's top events, in the chosen order. Click-through needs a
// few views to mean anything, so that order skips events under 10.
const eventRows = computed(() => {
    const list = eventSort.value === 'click_through'
        ? report.value.events.filter((row) => row.views >= 10)
        : [...report.value.events]
    return list.sort((a, b) => (b[eventSort.value] ?? -1) - (a[eventSort.value] ?? -1)).slice(0, 10)
})

const ordinal = (n) => {
    const suffix = n % 100 >= 11 && n % 100 <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] || 'th')
    return `${n}${suffix}`
}

// "Which result they opened": each position's share of all result clicks,
// bars scaled to the most-clicked position.
const positionRows = computed(() => {
    const entries = Object.entries(report.value?.search_clicks?.by_position || {})
    const total = entries.reduce((sum, [, clicks]) => sum + clicks, 0)
    const most = Math.max(1, ...entries.map(([, clicks]) => clicks))
    return entries.map(([position, clicks]) => ({
        position,
        label: position === '11+' ? '11th result or lower' : `${ordinal(Number(position))} result`,
        clicks,
        share: total ? percent(clicks / total) : '0%',
        width: Math.round((clicks / most) * 100),
    }))
})

const barWidth = (rate) => {
    const best = Math.max(0, ...report.value.events.map((row) => row.click_through || 0))
    return best ? Math.round(((rate || 0) / best) * 100) : 0
}

// ---- Chart: one series (event views), plain SVG (analyticsChart.js) ----
const chart = computed(() => lineChart(report.value?.daily || [], (point) => point.event_views))

const hovered = computed(() => (hoverIndex.value === null ? null : chart.value.coords[hoverIndex.value] || null))

const onChartMove = (event) => {
    hoverIndex.value = nearestIndex(chart.value, event)
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

// The "From Google" block (Search Console) loads on its own: it is hidden
// while not configured, and a failure there never hides the rest.
const google = ref(null)
let latestGoogle = 0

const loadGoogle = async (option) => {
    const request = ++latestGoogle
    try {
        const { data } = await axios.get('/api/admin/analytics/google', { params: { days: option } })
        if (request === latestGoogle) google.value = data
    } catch (error) {
        if (request !== latestGoogle) return
        console.error('[admin-analytics] Google block failed to load', error)
        google.value = null
    }
}

const load = async (option = days.value) => {
    const request = ++latest
    loadGoogle(option)
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

onMounted(() => {
    showDashboard()
    window.addEventListener('popstate', onPopState)
})

onUnmounted(() => window.removeEventListener('popstate', onPopState))
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

.section-count {
    @apply text-[1.3rem] font-semibold text-[#717171] whitespace-nowrap;
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
