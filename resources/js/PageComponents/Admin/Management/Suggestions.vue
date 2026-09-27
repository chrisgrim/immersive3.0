<template>
    <div class="space-y-8">
        <h1 class="text-4xl font-medium">Suggestions</h1>
        <p class="text-gray-500 font-normal">Events people think we're missing, sent from the "Tell us about an event" link and the "Missing an event?" button.</p>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <LoadingSpinner class="h-8 w-8 text-gray-400" />
        </div>

        <!-- Empty -->
        <div v-else-if="!suggestions.length" class="text-center py-12">
            <p class="text-gray-500 text-lg">No new suggestions</p>
        </div>

        <!-- Suggestions List -->
        <div v-else class="space-y-4">
            <div v-for="suggestion in suggestions" :key="suggestion.id"
                class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
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
                    </div>

                    <div class="flex gap-3 flex-shrink-0">
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

const suggestions = ref([])
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

const fetchSuggestions = async () => {
    try {
        loading.value = true
        const { data } = await axios.get('/api/admin/approve/suggestions')
        suggestions.value = data.suggestions || []
    } catch (error) {
        console.error('Error fetching suggestions:', error)
        showToastMessage('Failed to load suggestions')
    } finally {
        loading.value = false
    }
}

const act = async (suggestion, action, request, doneMessage) => {
    try {
        processing.value = suggestion.id
        processingAction.value = action
        await request()
        suggestions.value = suggestions.value.filter(s => s.id !== suggestion.id)
        showToastMessage(doneMessage)
        emit('update-counts')
    } catch (error) {
        console.error(`Error (${action}) suggestion:`, error)
        showToastMessage(error.response?.data?.message || 'Something went wrong')
        await fetchSuggestions()
    } finally {
        processing.value = null
        processingAction.value = null
    }
}

const markDone = (suggestion) => act(
    suggestion, 'done',
    () => axios.post(`/api/admin/approve/suggestions/${suggestion.id}/done`),
    'Marked as done',
)

const remove = (suggestion) => act(
    suggestion, 'delete',
    () => axios.delete(`/api/admin/approve/suggestions/${suggestion.id}`),
    'Suggestion deleted',
)

onMounted(fetchSuggestions)
</script>
