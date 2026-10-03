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
                <p class="text-[1.1rem] font-bold tracking-[0.04em] uppercase text-[#717171]">Analytics</p>
                <h1 class="text-[3.2rem] leading-[4rem] font-semibold tracking-[-0.02em]">Search</h1>
                <p class="text-[1.4rem] text-[#717171] mt-[0.4rem]">How the site does on Google, from Google Search Console.</p>
            </div>
            <div class="flex flex-wrap self-start md:self-auto bg-[#F7F7F7] rounded-[2rem] md:rounded-full p-[0.4rem]" role="group" aria-label="Date range">
                <button
                    v-for="option in dayOptions"
                    :key="option.days"
                    type="button"
                    @click="load(option.days)"
                    :aria-pressed="days === option.days"
                    :class="['px-[1.4rem] py-[0.8rem] rounded-full text-[1.3rem] font-semibold transition-colors',
                        days === option.days ? 'bg-white shadow-[0_2px_6px_rgba(0,0,0,0.06)] text-[#222222]' : 'text-[#717171] hover:text-[#222222]']"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div v-if="loading && !data" class="flex justify-center items-center py-12">
            <LoadingSpinner class="h-8 w-8 text-gray-400" />
        </div>

        <p v-else-if="failed" class="text-[1.6rem] text-[#E00B41]">{{ failed }}</p>

        <p v-else-if="data && !data.configured" class="text-[1.4rem] text-[#717171]">
            Google Search Console is not connected on this server yet.
        </p>

        <div v-else-if="data" :class="['transition-opacity', loading ? 'opacity-50' : '']">
            <AnalyticsGoogle :data="data" @open="openSection" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue'
import AnalyticsSection from './AnalyticsSection.vue'
import AnalyticsGoogle from './AnalyticsGoogle.vue'

// Google Search Console's own ranges (its data reaches back 16 months).
const dayOptions = [
    { days: 7, label: '7 days' },
    { days: 28, label: '28 days' },
    { days: 90, label: '3 months' },
    { days: 180, label: '6 months' },
    { days: 365, label: '12 months' },
    { days: 480, label: '16 months' },
]
const days = ref(28)
const data = ref(null)
const loading = ref(true)
const failed = ref('')

// A full list opened on its own page (?section=google_queries), with Back.
const SECTIONS = ['google_queries', 'google_pages']
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
    if (window.history.state?.analyticsSection) {
        window.history.back()
        return
    }
    const url = new URL(window.location)
    url.searchParams.delete('section')
    window.history.replaceState({}, '', url)
    section.value = null
    showPage()
}

const onPopState = () => {
    section.value = sectionFromUrl()
    showPage()
}

// Loaded only while the page itself is shown, and again if the range was
// changed on a list page meanwhile.
const showPage = () => {
    if (!section.value && data.value?.requested !== days.value) load()
}

// Only the newest request may land.
let latest = 0

const load = async (option = days.value) => {
    const request = ++latest
    days.value = option
    loading.value = true
    failed.value = ''
    try {
        const { data: answer } = await axios.get('/api/admin/analytics/google', { params: { days: option } })
        if (request === latest) data.value = { ...answer, requested: option }
    } catch (error) {
        if (request !== latest) return
        console.error('[admin-analytics] search page failed to load', error)
        failed.value = error?.response?.data?.message || 'Could not load the Google numbers. Please try again.'
    } finally {
        if (request === latest) loading.value = false
    }
}

onMounted(() => {
    showPage()
    window.addEventListener('popstate', onPopState)
})

onUnmounted(() => window.removeEventListener('popstate', onPopState))
</script>
