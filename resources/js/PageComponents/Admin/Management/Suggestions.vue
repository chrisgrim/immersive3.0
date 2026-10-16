<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-4xl font-medium">Suggestions</h1>
            <button
                @click="toggleDone"
                class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 flex-shrink-0"
            >
                {{ showDone ? 'Hide done' : 'Show done' }}
            </button>
        </div>
        <p class="text-gray-500 font-normal">Events people think we're missing, sent from the "Tell us about an event" link and the "Missing an event?" button.</p>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <LoadingSpinner class="h-8 w-8 text-gray-400" />
        </div>

        <!-- Empty -->
        <div v-else-if="!suggestions.length" class="text-center py-12">
            <p class="text-gray-500 text-lg">{{ showDone ? 'No suggestions yet' : 'No new suggestions' }}</p>
        </div>

        <!-- Suggestions List -->
        <div v-else class="space-y-4">
            <!-- Done ones stay on the list, grayed out with no buttons, so the page is a record. -->
            <div v-for="suggestion in suggestions" :key="suggestion.id"
                :class="['rounded-lg border p-6', isDone(suggestion)
                    ? 'bg-gray-50 border-gray-200 opacity-60'
                    : 'bg-white shadow-sm border-gray-200']">
                <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-6">
                    <div class="space-y-2 min-w-0">
                        <div class="text-sm text-gray-500">{{ formatDate(suggestion.created_at) }}</div>
                        <!-- Links in the message are clickable (http/https only); everything else is plain text. -->
                        <p class="text-xl text-gray-900 whitespace-pre-line break-words">
                            <template v-for="(part, i) in messageParts(suggestion.message)" :key="i">
                                <a v-if="part.url" :href="part.url" target="_blank" rel="noopener noreferrer nofollow"
                                   class="text-blue-600 hover:text-blue-800 underline break-all">{{ part.text }}</a>
                                <template v-else>{{ part.text }}</template>
                            </template>
                        </p>
                        <div class="text-sm text-gray-500">
                            <template v-if="suggestion.user">From {{ suggestion.user.name }} ({{ suggestion.user.email }})</template>
                            <template v-else>Anonymous</template>
                        </div>
                        <div v-if="isDone(suggestion)" class="text-sm font-medium text-gray-600">
                            Done{{ suggestion.processed_by ? ` by ${suggestion.processed_by}` : '' }}{{ suggestion.processed_at ? ` on ${formatDate(suggestion.processed_at)}` : '' }}
                        </div>
                    </div>

                    <div v-if="!isDone(suggestion)" class="flex gap-3 flex-shrink-0">
                        <button
                            @click="markDone(suggestion)"
                            :disabled="processing === suggestion.id"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md disabled:opacity-50 flex items-center gap-2"
                        >
                            <LoadingSpinner v-if="processing === suggestion.id && processingAction === 'done'" />
                            Done
                        </button>
                        <button
                            @click="remove(suggestion)"
                            :disabled="processing === suggestion.id"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50 flex items-center gap-2"
                        >
                            <LoadingSpinner v-if="processing === suggestion.id && processingAction === 'delete'" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Pagination
            v-if="!loading && pagination"
            :pagination="pagination"
            @paginate="fetchSuggestions"
        />

        <!-- Toast Message -->
        <div v-if="showToast"
            class="fixed bottom-4 right-4 bg-gray-800 text-white px-6 py-3 rounded-lg shadow-lg">
            {{ toastMessage }}
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/GlobalComponents/loading-spinner.vue'
import Pagination from '@/GlobalComponents/pagination.vue'

// Remembered per browser so the page opens the way it was left.
const SHOW_DONE_KEY = 'admin-suggestions-show-done'
const readShowDone = () => {
    try {
        return localStorage.getItem(SHOW_DONE_KEY) !== '0'
    } catch {
        return true
    }
}

const suggestions = ref([])
const pagination = ref(null)
const showDone = ref(readShowDone())
const loading = ref(true)
const processing = ref(null)
const processingAction = ref(null)
const showToast = ref(false)
const toastMessage = ref('')

const emit = defineEmits(['update-counts'])

const showToastMessage = (message) => {
    toastMessage.value = message
    showToast.value = true
    setTimeout(() => {
        showToast.value = false
    }, 3000)
}

const isDone = (suggestion) => suggestion.status === 'done'

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

// Split a message into plain-text and link pieces so pasted URLs can be clicked.
const messageParts = (message) => {
    const parts = []
    let last = 0
    for (const match of message.matchAll(/https?:\/\/[^\s<>"]+/gi)) {
        // Trailing punctuation usually ends the sentence, not the link.
        const url = match[0].replace(/[.,;:!?)\]]+$/, '')
        if (match.index > last) parts.push({ text: message.slice(last, match.index) })
        parts.push({ text: url, url })
        last = match.index + url.length
    }
    if (last < message.length) parts.push({ text: message.slice(last) })
    return parts
}

const fetchSuggestions = async (page = 1) => {
    try {
        loading.value = true
        const { data } = await axios.get('/api/admin/approve/suggestions', {
            params: { page, done: showDone.value ? 1 : 0 },
        })
        suggestions.value = data.suggestions || []
        pagination.value = data.pagination || null
    } catch (error) {
        console.error('Error fetching suggestions:', error)
        showToastMessage('Failed to load suggestions')
    } finally {
        loading.value = false
    }
}

const toggleDone = () => {
    showDone.value = !showDone.value
    try {
        localStorage.setItem(SHOW_DONE_KEY, showDone.value ? '1' : '0')
    } catch {}
    fetchSuggestions(1)
}

const act = async (suggestion, action, request, doneMessage, update) => {
    try {
        processing.value = suggestion.id
        processingAction.value = action
        await request()
        update()
        showToastMessage(doneMessage)
        emit('update-counts')
    } catch (error) {
        console.error(`Error (${action}) suggestion:`, error)
        showToastMessage(error.response?.data?.message || 'Something went wrong')
        await fetchSuggestions(pagination.value?.current_page)
    } finally {
        processing.value = null
        processingAction.value = null
    }
}

const markDone = (suggestion) => act(
    suggestion, 'done',
    () => axios.post(`/api/admin/approve/suggestions/${suggestion.id}/done`),
    'Marked as done',
    // Stays on the list, grayed out, with who did it and when (or leaves it while done ones are hidden).
    () => showDone.value ? Object.assign(suggestion, {
        status: 'done',
        processed_at: new Date().toISOString(),
        processed_by: window.Laravel?.user?.name ?? null,
    }) : (suggestions.value = suggestions.value.filter(s => s.id !== suggestion.id)),
)

const remove = (suggestion) => act(
    suggestion, 'delete',
    () => axios.delete(`/api/admin/approve/suggestions/${suggestion.id}`),
    'Suggestion deleted',
    () => { suggestions.value = suggestions.value.filter(s => s.id !== suggestion.id) },
)

onMounted(fetchSuggestions)
</script>
