<template>
    <div class="space-y-10">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <h1 class="text-4xl font-medium">Analytics</h1>
                <p class="text-gray-500 font-normal">The site's own counts, bots left out. No cookies; nothing here identifies a person.</p>
            </div>
            <div class="flex gap-2">
                <button
                    v-for="option in dayOptions"
                    :key="option"
                    type="button"
                    @click="load(option)"
                    :class="['px-4 py-2 text-lg rounded-lg border', days === option ? 'bg-black text-white border-black' : 'border-gray-300 text-gray-700 hover:bg-gray-100']"
                >
                    {{ option === 365 ? '1 year' : `${option} days` }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center items-center py-12">
            <LoadingSpinner class="h-8 w-8 text-gray-400" />
        </div>

        <p v-else-if="failed" class="text-lg text-red-600">Could not load analytics. Please try again.</p>

        <template v-else-if="report">
            <!-- Totals -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="tile in tiles" :key="tile.label" class="rounded-lg border border-gray-200 p-6">
                    <div class="text-sm text-gray-500">{{ tile.label }}</div>
                    <div class="text-4xl font-medium mt-2">{{ tile.value }}</div>
                    <div v-if="tile.note" class="text-sm text-gray-500 mt-1">{{ tile.note }}</div>
                </div>
            </div>

            <!-- Searches that found nothing -->
            <section>
                <h2 class="text-2xl font-medium mb-2">Searches that found nothing</h2>
                <p class="text-gray-500 mb-4">Places people looked for events and got none. "With filters" means a filter (category, genre, date, price) may be why.</p>
                <table v-if="report.zero_result_searches.length" class="w-full text-left text-lg">
                    <thead class="text-gray-500 text-sm">
                        <tr><th class="py-2">Place</th><th>Searches</th><th>With filters</th><th>Visitors</th><th>Last</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.zero_result_searches" :key="row.place" class="border-t border-gray-100">
                            <td class="py-2">{{ row.place }}</td>
                            <td>{{ row.searches }}</td>
                            <td>{{ row.with_filters }}</td>
                            <td>{{ row.visitors }}</td>
                            <td class="text-gray-500">{{ formatDate(row.last_searched) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-gray-500">None yet.</p>
            </section>

            <!-- Top searched places -->
            <section>
                <h2 class="text-2xl font-medium mb-4">Most searched places</h2>
                <table v-if="report.searches.length" class="w-full text-left text-lg">
                    <thead class="text-gray-500 text-sm">
                        <tr><th class="py-2">Place</th><th>Searches</th><th>Found nothing</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.searches" :key="row.place" class="border-t border-gray-100">
                            <td class="py-2">{{ row.place }}</td>
                            <td>{{ row.searches }}</td>
                            <td>{{ row.found_nothing }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-gray-500">None yet.</p>
            </section>

            <!-- Events -->
            <section>
                <h2 class="text-2xl font-medium mb-4">Most viewed events</h2>
                <table v-if="report.events.length" class="w-full text-left text-lg">
                    <thead class="text-gray-500 text-sm">
                        <tr><th class="py-2">Event</th><th>Views</th><th>Ticket clicks</th><th>Click-through</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.events" :key="row.event_id" class="border-t border-gray-100">
                            <td class="py-2">
                                <a v-if="row.slug" :href="`/events/${row.slug}`" target="_blank" class="text-blue-600 hover:text-blue-800">{{ row.name }}</a>
                                <span v-else class="text-gray-500">Event {{ row.event_id }} (removed)</span>
                            </td>
                            <td>{{ row.views }}</td>
                            <td>{{ row.ticket_clicks }}</td>
                            <td>{{ percent(row.click_through) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-gray-500">None yet.</p>
            </section>

            <div class="grid md:grid-cols-3 gap-10">
                <!-- Where views came from -->
                <section>
                    <h2 class="text-2xl font-medium mb-4">Where event views came from</h2>
                    <ul class="space-y-1 text-lg">
                        <li v-for="(views, kind) in report.view_sources.by_kind" :key="kind" class="flex justify-between">
                            <span>{{ sourceNames[kind] || kind }}</span><span>{{ views }}</span>
                        </li>
                    </ul>
                    <h3 class="text-lg font-medium mt-6 mb-2">Outside sites</h3>
                    <ul class="space-y-1 text-lg">
                        <li v-for="(views, site) in report.view_sources.outside_sites" :key="site" class="flex justify-between gap-4">
                            <span class="truncate">{{ site }}</span><span>{{ views }}</span>
                        </li>
                    </ul>
                </section>

                <!-- Result clicks -->
                <section>
                    <h2 class="text-2xl font-medium mb-4">Search result clicks</h2>
                    <p class="text-lg mb-4">
                        {{ percent(report.search_clicks.click_rate) }} of searches led to a click
                        ({{ report.search_clicks.searches_with_a_click }} of {{ report.search_clicks.searches }}).
                    </p>
                    <ul class="space-y-1 text-lg">
                        <li v-for="(clicks, position) in report.search_clicks.by_position" :key="position" class="flex justify-between">
                            <span>Result #{{ position }}</span><span>{{ clicks }}</span>
                        </li>
                    </ul>
                </section>

                <!-- Countries -->
                <section>
                    <h2 class="text-2xl font-medium mb-4">Visitors by country</h2>
                    <ul class="space-y-1 text-lg">
                        <li v-for="(visitors, country) in report.countries" :key="country" class="flex justify-between">
                            <span>{{ country }}</span><span>{{ visitors }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <p class="text-sm text-gray-500">
                Bots filtered out: {{ report.bots.flagged }} of {{ report.bots.all_rows }} hits ({{ percent(report.bots.share) }}):
                {{ report.bots.datacenter }} from cloud networks, {{ report.bots.crawler }} declared crawlers,
                {{ report.bots.over_daily_cap }} over the daily limit, {{ report.bots.no_user_agent }} with no browser name.
                IP geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline">DB-IP</a>.
            </p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue'

const dayOptions = [7, 30, 90, 365]
const days = ref(30)
const report = ref(null)
const loading = ref(true)
const failed = ref(false)

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

const total = (type, field = 'total') => report.value?.totals?.[type]?.[field] ?? 0

const tiles = computed(() => [
    { label: 'Searches', value: total('search').toLocaleString(), note: `${total('search', 'visitors').toLocaleString()} visitors` },
    { label: 'Event views', value: total('event_view').toLocaleString(), note: `${total('event_view', 'visitors').toLocaleString()} visitors` },
    { label: 'Ticket clicks', value: total('ticket_click').toLocaleString() },
    { label: 'Search result clicks', value: total('search_click').toLocaleString() },
])

const percent = (value) => (value === null || value === undefined ? 'n/a' : `${Math.round(value * 1000) / 10}%`)

const formatDate = (date) => (date ? new Date(date.replace(' ', 'T') + 'Z').toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '')

const load = async (option = days.value) => {
    days.value = option
    loading.value = true
    failed.value = false
    try {
        const { data } = await axios.get('/api/admin/analytics', { params: { days: option } })
        report.value = data
    } catch (error) {
        console.error('[admin-analytics] failed to load', error)
        failed.value = true
    } finally {
        loading.value = false
    }
}

onMounted(() => load())
</script>
