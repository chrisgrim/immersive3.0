<template>
    <div class="flex justify-end">
        <div class="px-8 md:px-32 w-full ml-[-2rem] mt-20 md:mt-12">
            <!-- Title and Add Button -->
            <div class="w-full flex items-center justify-between mb-8">
                <h2 class="font-medium">Select Organizer</h2>
                <div class="flex items-center gap-4">
                    <!-- Search Input (with fixed width when shown) -->
                    <div v-if="showSearch" class="w-[300px]">
                        <div class="flex border border-black rounded-full items-center">
                            <div class="relative flex-grow">
                                <input 
                                    v-model="searchQuery"
                                    type="text" 
                                    placeholder="Search teams..."
                                    class="w-full px-4 py-3 bg-transparent border-none"
                                    @input="handleSearch"
                                >
                                <button
                                    @click="clearSearch"
                                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-black hover:text-gray-600"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-8 h-8">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Search Button (with flex-shrink-0 to prevent squashing) -->
                    <button 
                        v-else
                        @click="showSearch = !showSearch"
                        class="flex-shrink-0 cursor-pointer rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center hover:bg-gray-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                    
                    <!-- Add Button (with flex-shrink-0 to prevent squashing) -->
                    <a href="/hosting/getting-started" class="flex-shrink-0 cursor-pointer">
                        <div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center text-5xl font-light hover:bg-gray-200">
                            +
                        </div>
                    </a>
                </div>
            </div>

            <!-- Search Form -->
            

            <div class="w-full">
                <!-- Header Row -->
                <div class="grid gap-8 py-4 items-center grid-cols-[4rem_auto] md:grid-cols-[4rem_30%_auto_auto]">
                    <div><h5 class="font-medium">Teams</h5></div>
                    <div class="hidden md:block"><h5 class="font-medium">Name</h5></div>
                    <div class="hidden md:block"><h5 class="font-medium">Events</h5></div>
                    <div class="hidden md:block"><h5 class="font-medium">Created</h5></div>
                </div>
            </div>

            <!-- Teams List -->
            <div class="w-full">
                <div 
                    v-for="team in teams.data" 
                    :key="team.id"
                    class="group mb-2 relative grid grid-cols-2 md:grid-cols-4 gap-8 p-4 items-center hover:bg-gray-100 rounded-2xl grid-cols-[4rem_auto] md:grid-cols-[4rem_30%_auto_auto]"
                    :class="{ 'bg-neutral-100 ring-1 ring-neutral-300': team === currentTeam }"
                >
                    <!-- Team Image -->
                    <div>
                        <template v-if="team.images && team.images.length > 0">
                            <picture>
                                <source 
                                    :srcset="imageUrl(team.images[0].thumb_image_path)" 
                                    type="image/webp"
                                >
                                <img 
                                    :src="imageUrl(team.images[0].thumb_image_path)" 
                                    :alt="`${team.name} logo`"
                                    class="h-16 w-16 object-cover rounded-full"
                                >
                            </picture>
                        </template>
                        <template v-else-if="team.thumbImagePath">
                            <picture>
                                <source 
                                    :srcset="imageUrl(team.thumbImagePath)" 
                                    type="image/webp"
                                >
                                <img 
                                    :src="imageUrl(team.thumbImagePath)" 
                                    :alt="`${team.name} logo`"
                                    class="h-16 w-16 object-cover rounded-full"
                                >
                            </picture>
                        </template>
                        <div 
                            v-else 
                            class="h-16 w-16 rounded-full bg-white border-2 border-neutral-200 flex items-center justify-center"
                        >
                            <span class="text-2xl font-medium text-neutral-500">
                                {{ team.name?.[0]?.toUpperCase() || '' }}
                            </span>
                        </div>
                    </div>

                    <!-- Team Name -->
                    <div>
                        <div class="flex items-center gap-2">
                            <button 
                                @click="switchTeam(team)"
                                class="text-2xl font-medium hover:underline"
                            >
                                {{ team.name }}
                            </button>
                            <span 
                                class="text-sm px-2 py-1 bg-gray-100 text-gray-600 rounded-full"
                            >
                                {{ team.role }}
                            </span>
                        </div>
                    </div>

                    <!-- Event Count -->
                    <div class="hidden md:block">
                        <div class="flex flex-col">
                            <p class="text-1xl text-gray-500">
                                {{ team.events_count }} total
                            </p>
                            <p class="text-xl text-gray-400 mt-1">
                                {{ team.published_events_count }} published
                            </p>
                        </div>
                    </div>

                    <!-- Created Date -->
                    <div class="hidden md:block">
                        <p class="text-1xl text-gray-500">{{ formatDate(team.created_at) }}</p>
                    </div>

                    <!-- Arrow Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                         class="hidden md:absolute right-6 top-1/2 transform -translate-y-1/2 w-8 h-8 opacity-0 group-hover:opacity-100"
                         stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </div>

            <!-- Pagination -->
            <div class="mt-8 mb-20">
                <pagination 
                    v-if="showPagination"
                    :pagination="teams"
                    @paginate="handlePagination"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import pagination from '@/GlobalComponents/pagination.vue'
import axios from 'axios'

const props = defineProps({
    user: {
        type: Object,
        required: true
    }
})

const teams = ref({
    data: [],
    total: 0,
    per_page: 40,
    current_page: 1,
    last_page: 1
})
const currentTeamId = ref(props.user.current_team_id)
const showSearch = ref(false)
const searchQuery = ref('')

// Custom debounce function
const debounce = (callback, wait) => {
    let timeout
    return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => callback(...args), wait)
    }
}

const handleSearch = debounce(() => {
    fetchTeams(1, searchQuery.value)
}, 300)

const clearSearch = () => {
    searchQuery.value = ''
    showSearch.value = false
    fetchTeams()
}

const handlePagination = (page) => {
    fetchTeams(page, searchQuery.value)
}

const fetchTeams = async (page = 1, search = '') => {
    try {
        const response = await axios.get('/api/teams/search', {
            params: { page, q: search }
        })
        teams.value = response.data.teams
        currentTeamId.value = response.data.current_team_id
    } catch (error) {
        console.error('Error fetching teams:', error)
    }
}

const switchTeam = async (team) => {
    try {
        await axios.post(`/teams/switch/${team.slug}`)
        window.location.href = '/hosting/events'
    } catch (error) {
        console.error('Error switching team:', error)
    }
}

const imageUrl = (path) => {
    if (!path) return '';
    return `${import.meta.env.VITE_IMAGE_URL}${path}`;
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

// Add this computed property
const currentTeam = computed(() => {
    return teams.value.data.find(team => team.id === currentTeamId.value)
})

// Add back onMounted
onMounted(() => {
    fetchTeams()
})

const showPagination = computed(() => teams.value.total > teams.value.per_page)
</script>