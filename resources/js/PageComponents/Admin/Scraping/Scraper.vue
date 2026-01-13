<template>
    <div class="h-full flex flex-col">
        <!-- URL Input Section -->
        <div class="flex-none pb-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold mb-6">Event Scraper</h2>
            <p class="text-gray-500 mb-4">Enter URLs for the event page, organizer page, and/or ticketing page. Data from all sources will be combined.</p>

            <!-- URL Inputs -->
            <div class="space-y-3">
                <div v-for="(urlInput, index) in urls" :key="index" class="flex gap-3">
                    <div class="flex-1 relative">
                        <input
                            v-model="urls[index]"
                            type="url"
                            :placeholder="getPlaceholder(index)"
                            class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl px-6 py-4 pr-12"
                            @keyup.enter="scrape"
                        />
                        <button
                            v-if="urls.length > 1"
                            @click="removeUrl(index)"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add URL Button -->
            <button
                v-if="urls.length < 5"
                @click="addUrl"
                class="mt-3 flex items-center gap-2 text-gray-600 hover:text-black transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add another URL
            </button>

            <!-- Scrape Button -->
            <div class="mt-6">
                <button
                    @click="scrape"
                    :disabled="loading || !hasValidUrl"
                    :class="{
                        'px-8 py-4 rounded-2xl text-xl font-medium transition-colors': true,
                        'bg-black text-white hover:bg-gray-800': !loading && hasValidUrl,
                        'bg-gray-300 text-gray-500 cursor-not-allowed': loading || !hasValidUrl
                    }"
                >
                    <div class="flex items-center gap-2">
                        <svg v-if="loading" class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        {{ loading ? 'Scraping...' : 'Scrape All URLs' }}
                    </div>
                </button>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mt-4 p-4 bg-red-50 rounded-xl border border-red-200">
                <p class="text-red-700">{{ error }}</p>
            </div>
        </div>

        <!-- Results Section -->
        <div v-if="scrapedData" class="flex-1 overflow-auto pt-8">
            <!-- Duplicate Warnings -->
            <div v-if="duplicates.events?.length || duplicates.organizers?.length" class="mb-6 space-y-4">
                <!-- Duplicate Events Warning -->
                <div v-if="duplicates.events?.length" class="p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                    <h3 class="text-yellow-800 font-semibold mb-2">⚠️ Possible Duplicate Events Found:</h3>
                    <div class="space-y-2">
                        <div v-for="event in duplicates.events" :key="event.id" class="flex items-center justify-between">
                            <span class="text-yellow-700">{{ event.name }}</span>
                            <a :href="event.url" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                                View Event
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Duplicate Organizers Warning -->
                <div v-if="duplicates.organizers?.length" class="p-4 bg-blue-50 rounded-xl border border-blue-200">
                    <h3 class="text-blue-800 font-semibold mb-2">ℹ️ Existing Organizer Found:</h3>
                    <div class="space-y-2">
                        <div v-for="org in duplicates.organizers" :key="org.id" class="flex items-center justify-between">
                            <span class="text-blue-700">{{ org.name }}</span>
                            <a :href="org.url" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                                View Organizer
                            </a>
                        </div>
                    </div>
                    <p class="text-blue-600 text-sm mt-2">This organizer already exists - you can add this event to their profile.</p>
                </div>
            </div>

            <!-- Completion Badge -->
            <div class="mb-6 flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-2">
                    <span class="text-lg text-gray-600">Completion:</span>
                    <span
                        :class="{
                            'px-3 py-1 rounded-full text-sm font-medium': true,
                            'bg-green-100 text-green-700': meta.completion_percentage >= 80,
                            'bg-yellow-100 text-yellow-700': meta.completion_percentage >= 50 && meta.completion_percentage < 80,
                            'bg-red-100 text-red-700': meta.completion_percentage < 50
                        }"
                    >
                        {{ meta.completion_percentage }}%
                    </span>
                </div>
                <div v-if="meta.urls_processed?.length > 1" class="flex items-center gap-2">
                    <span class="text-lg text-gray-600">Sources:</span>
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
                        {{ meta.urls_processed.length }} URLs combined
                    </span>
                </div>
                <div v-if="meta.fields_needing_review && Object.keys(meta.fields_needing_review).length > 0" class="text-sm text-gray-500">
                    Fields needing review: {{ Object.keys(meta.fields_needing_review).join(', ') }}
                </div>
            </div>

            <div class="max-w-screen-xl mx-auto pb-24 space-y-8">
                <!-- Primary Image Section -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">Primary Image</h3>
                    <div v-if="scrapedData.primaryImageUrl" class="aspect-[3/4] overflow-hidden rounded-xl" style="max-height: 30rem; width: fit-content;">
                        <img
                            :src="scrapedData.primaryImageUrl"
                            alt="Event primary image"
                            class="h-full object-cover"
                            style="max-height: 30rem;"
                        />
                    </div>
                    <p v-else class="text-gray-400 italic">Not found</p>

                    <!-- Additional Images -->
                    <div v-if="scrapedData.imageUrls?.length > 1" class="mt-6">
                        <h4 class="text-lg font-medium mb-3">Additional Images ({{ scrapedData.imageUrls.length }})</h4>
                        <div class="grid grid-cols-4 gap-2">
                            <div v-for="(img, idx) in scrapedData.imageUrls.slice(0, 8)" :key="idx" class="aspect-square rounded-lg overflow-hidden">
                                <img :src="img" class="w-full h-full object-cover" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Name & Description -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="text-5xl leading-tight font-semibold break-words hyphens-auto">
                            {{ scrapedData.name || 'Not found' }}
                        </h3>
                        <ConfidenceBadge :level="scrapedData.nameConfidence" />
                    </div>
                    <p v-if="scrapedData.tagline" class="text-1xl mb-16 break-words hyphens-auto text-gray-600">{{ scrapedData.tagline }}</p>
                    <p v-else class="text-1xl mb-16 text-gray-400 italic">No tagline found</p>

                    <div class="flex items-start justify-between">
                        <p v-if="scrapedData.description" class="text-2.5xl font-normal whitespace-pre-line break-words hyphens-auto flex-1">
                            {{ scrapedData.description }}
                        </p>
                        <p v-else class="text-gray-400 italic">No description found</p>
                        <ConfidenceBadge v-if="scrapedData.description" :level="scrapedData.descriptionConfidence" class="ml-4 flex-shrink-0" />
                    </div>
                </div>

                <!-- Organizer Section -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">Organizer:</h3>
                    <div v-if="scrapedData.organizerName" class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-2xl text-gray-400">
                            {{ scrapedData.organizerName.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <h4 class="text-4xl font-medium">{{ scrapedData.organizerName }}</h4>
                        </div>
                    </div>
                    <p v-else class="text-gray-400 italic">Not found</p>

                    <!-- Social Links -->
                    <div v-if="scrapedData.additionalUrls && Object.keys(scrapedData.additionalUrls).length > 0" class="mt-6 grid grid-cols-2 gap-4">
                        <a v-for="(url, platform) in scrapedData.additionalUrls"
                           :key="platform"
                           v-show="url"
                           :href="url"
                           target="_blank"
                           class="flex items-center gap-2 px-4 py-3 border border-neutral-300 rounded-2xl hover:border-black text-sm truncate">
                            <span class="capitalize">{{ platform }}:</span>
                            <span class="truncate text-blue-600">{{ url }}</span>
                        </a>
                    </div>
                </div>

                <!-- Location Section -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold">Location:</h3>
                        <ConfidenceBadge :level="scrapedData.locationConfidence" />
                    </div>

                    <div v-if="scrapedData.locationType === 'online'">
                        <p class="text-gray-600">Online / Remote Event</p>
                    </div>
                    <div v-else-if="scrapedData.city || scrapedData.venueName">
                        <p v-if="scrapedData.venueName" class="text-black font-medium mb-2">{{ scrapedData.venueName }}</p>
                        <p v-if="scrapedData.streetAddress" class="text-neutral-500 font-normal text-1xl leading-tight">{{ scrapedData.streetAddress }}</p>
                        <p class="text-neutral-500 font-normal text-1xl leading-tight">
                            {{ [scrapedData.city, scrapedData.state, scrapedData.zipCode].filter(Boolean).join(', ') }}
                        </p>
                        <p v-if="scrapedData.country" class="text-neutral-500 font-normal text-1xl leading-tight">{{ scrapedData.country }}</p>
                        <p v-if="scrapedData.isSecretLocation" class="mt-2 text-yellow-600 italic">Secret location - revealed after booking</p>
                    </div>
                    <p v-else class="text-gray-400 italic">Not found</p>
                </div>

                <!-- Category & Tags -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold">Category:</h3>
                        <ConfidenceBadge :level="scrapedData.categoryConfidence" />
                    </div>
                    <p v-if="scrapedData.category" class="text-gray-600 mb-8">{{ formatCategory(scrapedData.category) }}</p>
                    <p v-else class="text-gray-400 italic mb-8">Not found</p>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold">Tags:</h3>
                        <ConfidenceBadge :level="scrapedData.tagsConfidence" />
                    </div>
                    <div v-if="scrapedData.tags?.length" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div v-for="tag in scrapedData.tags"
                             :key="tag"
                             class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-1xl break-words hyphens-auto">
                            {{ tag }}
                        </div>
                    </div>
                    <p v-else class="text-gray-400 italic">Not found</p>
                </div>

                <!-- Dates & Tickets -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold">Dates:</h3>
                        <ConfidenceBadge :level="scrapedData.datesConfidence" />
                    </div>

                    <div v-if="scrapedData.dateType">
                        <p class="text-gray-600 mb-2">Type: <span class="font-medium capitalize">{{ scrapedData.dateType }}</span></p>
                        <p v-if="scrapedData.startDate" class="text-gray-600">Start: {{ scrapedData.startDate }}</p>
                        <p v-if="scrapedData.endDate" class="text-gray-600">End: {{ scrapedData.endDate }}</p>

                        <!-- Specific Dates -->
                        <div v-if="scrapedData.specificDates?.length" class="mt-4">
                            <p class="text-gray-600 font-medium mb-2">Specific Dates ({{ scrapedData.specificDates.length }}):</p>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="date in scrapedData.specificDates"
                                      :key="date"
                                      class="px-3 py-1 bg-gray-100 rounded-full text-sm">
                                    {{ date }}
                                </span>
                            </div>
                        </div>

                        <p v-if="scrapedData.recurringDays?.length" class="text-gray-600 mt-2">
                            Recurring: {{ scrapedData.recurringDays.join(', ') }}
                        </p>
                        <p v-if="scrapedData.showTimes" class="text-gray-600 mt-2">Show Times: {{ scrapedData.showTimes }}</p>
                        <p v-if="scrapedData.datesNotes" class="text-gray-500 mt-2 italic text-sm">{{ scrapedData.datesNotes }}</p>
                    </div>
                    <p v-else class="text-gray-400 italic">Not found</p>

                    <div class="flex items-center justify-between mb-4 mt-12">
                        <h3 class="text-xl font-semibold">Tickets:</h3>
                        <ConfidenceBadge :level="scrapedData.priceConfidence" />
                    </div>

                    <div v-if="scrapedData.priceMin !== null">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div class="flex flex-col border border-neutral-300 rounded-2xl">
                                <p class="px-4 pt-4 text-1xl font-semibold">Base Price</p>
                                <div class="flex-grow flex flex-col justify-end px-4 pb-4">
                                    <p class="text-1xl font-semibold mt-14 leading-tight">
                                        {{ scrapedData.currency === 'USD' ? '$' : scrapedData.currency + ' ' }}{{ scrapedData.priceMin }}
                                        <span v-if="scrapedData.priceMax && scrapedData.priceMax !== scrapedData.priceMin">
                                            - {{ scrapedData.currency === 'USD' ? '$' : '' }}{{ scrapedData.priceMax }}
                                        </span>
                                    </p>
                                    <p v-if="scrapedData.priceNotes" class="text-lg text-gray-600 leading-tight mt-2">
                                        {{ scrapedData.priceNotes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <a v-if="scrapedData.ticketUrl"
                           :href="scrapedData.ticketUrl"
                           target="_blank"
                           class="inline-block mt-4 text-blue-600 hover:underline">
                            {{ scrapedData.ticketUrl }}
                        </a>
                    </div>
                    <p v-else class="text-gray-400 italic">Not found</p>
                </div>

                <!-- Advisories -->
                <div class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-6">Experience Details & Advisories:</h3>

                    <div class="space-y-8">
                        <!-- Age Requirement -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium">Age Requirement</p>
                                <ConfidenceBadge :level="scrapedData.minimumAgeConfidence" />
                            </div>
                            <p v-if="scrapedData.minimumAge" class="text-gray-600">{{ scrapedData.minimumAge }}+</p>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>

                        <!-- Interaction Level -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium">Interaction Level</p>
                                <ConfidenceBadge :level="scrapedData.interactionLevelConfidence" />
                            </div>
                            <p v-if="scrapedData.interactionLevel" class="text-gray-600 capitalize">{{ scrapedData.interactionLevel }}</p>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>

                        <!-- Contact Level -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium">Physical Contact Level</p>
                                <ConfidenceBadge :level="scrapedData.contactLevelConfidence" />
                            </div>
                            <p v-if="scrapedData.contactLevel" class="text-gray-600 capitalize">{{ scrapedData.contactLevel }}</p>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>

                        <!-- Audience Role -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium">Audience Role</p>
                                <ConfidenceBadge :level="scrapedData.audienceRoleConfidence" />
                            </div>
                            <p v-if="scrapedData.audienceRole" class="text-gray-600">{{ scrapedData.audienceRole }}</p>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>

                        <!-- Sexual Content -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium">Sexual Content</p>
                                <ConfidenceBadge :level="scrapedData.contentConfidence" />
                            </div>
                            <p v-if="scrapedData.hasSexualContent !== null" class="text-gray-600">
                                {{ scrapedData.hasSexualContent ? 'Yes' : 'No' }}
                            </p>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>

                        <!-- Content Advisories -->
                        <div>
                            <p class="font-medium mb-2">Content Advisories</p>
                            <div v-if="scrapedData.contentAdvisories?.length" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div v-for="advisory in scrapedData.contentAdvisories"
                                     :key="advisory"
                                     class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words hyphens-auto">
                                    {{ advisory }}
                                </div>
                            </div>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>

                        <!-- Wheelchair Accessible -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium">Wheelchair Accessible</p>
                                <ConfidenceBadge :level="scrapedData.accessibilityConfidence" />
                            </div>
                            <p v-if="scrapedData.wheelchairAccessible !== null" class="text-gray-600">
                                {{ scrapedData.wheelchairAccessible ? 'Yes' : 'No' }}
                            </p>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>

                        <!-- Mobility Advisories -->
                        <div>
                            <p class="font-medium mb-2">Mobility Advisories</p>
                            <div v-if="scrapedData.mobilityAdvisories?.length" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div v-for="advisory in scrapedData.mobilityAdvisories"
                                     :key="advisory"
                                     class="flex flex-col justify-end px-4 pb-4 pt-14 border border-neutral-300 rounded-2xl text-xl break-words hyphens-auto">
                                    {{ advisory }}
                                </div>
                            </div>
                            <p v-else class="text-gray-400 italic">Not found</p>
                        </div>
                    </div>
                </div>

                <!-- Raw Notes -->
                <div v-if="scrapedData.rawNotes?.length" class="p-8 shadow-custom-1 rounded-3xl">
                    <h3 class="text-xl font-semibold mb-4">Additional Notes:</h3>
                    <ul class="list-disc list-inside space-y-2">
                        <li v-for="(note, idx) in scrapedData.rawNotes" :key="idx" class="text-gray-600">
                            {{ note }}
                        </li>
                    </ul>
                </div>

                <!-- Meta Info -->
                <div class="p-8 bg-gray-50 rounded-3xl text-sm text-gray-500">
                    <p>Source: <a :href="scrapedData.sourceUrl" target="_blank" class="text-blue-600 hover:underline">{{ scrapedData.sourceUrl }}</a></p>
                    <p>Scraped at: {{ scrapedData.scrapedAt }}</p>
                    <p>Scraper: {{ scrapedData.scraperUsed }}</p>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loading" class="flex-1 flex items-center justify-center">
            <div class="text-center text-gray-400">
                <svg class="w-24 h-24 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <p class="text-xl">Paste an event URL above to extract data</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

// Confidence Badge Component
const ConfidenceBadge = {
    props: ['level'],
    template: `
        <span v-if="level" :class="badgeClass" class="px-2 py-1 rounded text-xs font-medium">
            {{ level }}
        </span>
    `,
    computed: {
        badgeClass() {
            return {
                'bg-green-100 text-green-700': this.level === 'high',
                'bg-yellow-100 text-yellow-700': this.level === 'medium',
                'bg-red-100 text-red-700': this.level === 'low'
            }
        }
    }
}

// Multiple URLs support
const urls = ref([''])
const loading = ref(false)
const error = ref(null)
const scrapedData = ref(null)
const meta = ref({
    completion_percentage: 0,
    fields_needing_review: {},
    urls_processed: []
})
const duplicates = ref({
    events: [],
    organizers: []
})

const hasValidUrl = computed(() => {
    return urls.value.some(url => url && url.trim().length > 0)
})

const addUrl = () => {
    if (urls.value.length < 5) {
        urls.value.push('')
    }
}

const removeUrl = (index) => {
    if (urls.value.length > 1) {
        urls.value.splice(index, 1)
    }
}

const getPlaceholder = (index) => {
    const placeholders = [
        'Event page URL (e.g., venue website)',
        'Organizer page URL (optional)',
        'Ticketing page URL (optional)',
        'Additional URL (optional)',
        'Additional URL (optional)'
    ]
    return placeholders[index] || 'Additional URL'
}

const scrape = async () => {
    if (!hasValidUrl.value) return

    loading.value = true
    error.value = null
    scrapedData.value = null
    duplicates.value = { events: [], organizers: [] }

    // Filter out empty URLs
    const validUrls = urls.value.filter(url => url && url.trim().length > 0)

    try {
        // Increase timeout for multiple URLs (30s per URL + buffer)
        const timeoutMs = (validUrls.length * 30000) + 30000

        const response = await axios.post('/api/admin/scraper/extract', {
            urls: validUrls
        }, {
            timeout: timeoutMs
        })

        scrapedData.value = response.data.data
        meta.value = response.data.meta
        duplicates.value = response.data.duplicates || { events: [], organizers: [] }
    } catch (err) {
        console.error('Scraping error:', err)

        // Better error messages
        if (err.code === 'ECONNABORTED' || err.message?.includes('timeout')) {
            error.value = 'Request timed out. Try with fewer URLs or try again.'
        } else if (err.response?.status === 401) {
            error.value = 'Session expired. Please refresh the page and log in again.'
        } else if (err.response?.status === 403) {
            error.value = 'You do not have permission to use the scraper.'
        } else if (err.response?.status === 422) {
            error.value = err.response.data?.error || err.response.data?.message || 'Invalid URL(s) provided.'
        } else if (err.response?.status >= 500) {
            error.value = 'Server error. Check the Laravel logs for details.'
        } else {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to scrape URL(s). Please check the URLs and try again.'
        }
    } finally {
        loading.value = false
    }
}

const formatCategory = (category) => {
    if (!category) return ''
    return category
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase())
}
</script>
