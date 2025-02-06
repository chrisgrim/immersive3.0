<template>
    <div class="relative text-1xl font-medium w-full h-full flex flex-col">
        <!-- Main Content Area -->
        <div class="flex-1 flex overflow-hidden">
            <div class="w-full flex flex-1 flex-col md:flex-row">
                <!-- Navigation Sidebar -->
                <div 
                    class="flex-shrink-0 overflow-y-auto border-r border-gray-200 w-full lg:w-[31rem] lg:block" 
                    :class="{ 'hidden': currentView }">

                    <div class="p-8 space-y-6">
                        <!-- Approval Section -->
                        <div class="nav-section">
                            <h2 class="text-4xl font-medium mb-6">Approval Queue</h2>
                            <ul class="space-y-2">
                                <li>
                                    <a @click="handleNavigation('approve-events')" 
                                       :class="['nav-link', currentView === 'approve-events' ? 'bg-blue-50 text-blue-600' : '']"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span>Events</span>
                                            <span v-if="counts.events > 0" 
                                                  class="bg-red-500 text-white text-sm px-2 py-1 rounded-full">
                                                {{ counts.events }}
                                            </span>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a @click="handleNavigation('approve-organizers')" 
                                       :class="['nav-link', currentView === 'approve-organizers' ? 'bg-blue-50 text-blue-600' : '']"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span>Organizers</span>
                                            <span v-if="counts.organizers > 0" 
                                                  class="bg-red-500 text-white text-sm px-2 py-1 rounded-full">
                                                {{ counts.organizers }}
                                            </span>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a @click="handleNavigation('approve-communities')" 
                                       :class="['nav-link', currentView === 'approve-communities' ? 'bg-blue-50 text-blue-600' : '']"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span>Communities</span>
                                            <span v-if="counts.communities > 0" 
                                                  class="bg-red-500 text-white text-sm px-2 py-1 rounded-full">
                                                {{ counts.communities }}
                                            </span>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a @click="handleNavigation('approve-requests')" 
                                       :class="['nav-link', currentView === 'approve-requests' ? 'bg-blue-50 text-blue-600' : '']"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span>Requests</span>
                                            <span v-if="counts.requests > 0" 
                                                  class="bg-red-500 text-white text-sm px-2 py-1 rounded-full">
                                                {{ counts.requests }}
                                            </span>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Management Section -->
                        <div class="nav-section">
                            <h2 class="text-4xl font-medium mb-6">Management</h2>
                            <ul class="space-y-2">
                                <li><a @click="handleNavigation('manage-events')" :class="['nav-link', currentView === 'manage-events' ? 'bg-blue-50 text-blue-600' : '']">Events</a></li>
                                <li><a @click="handleNavigation('manage-users')" :class="['nav-link', currentView === 'manage-users' ? 'bg-blue-50 text-blue-600' : '']">Users</a></li>
                                <li><a @click="handleNavigation('manage-organizers')" :class="['nav-link', currentView === 'manage-organizers' ? 'bg-blue-50 text-blue-600' : '']">Organizers</a></li>
                                <li><a @click="handleNavigation('manage-reviews')" :class="['nav-link', currentView === 'manage-reviews' ? 'bg-blue-50 text-blue-600' : '']">Reviews</a></li>
                                <li><a @click="handleNavigation('manage-docks')" :class="['nav-link', currentView === 'manage-docks' ? 'bg-blue-50 text-blue-600' : '']">Docks</a></li>
                            </ul>
                        </div>

                        <!-- Settings Section -->
                        <div class="nav-section">
                            <h2 class="text-4xl font-medium mb-2">Settings</h2>
                            <ul class="space-y-2">
                                <li><a @click="handleNavigation('settings-categories')" :class="['nav-link', currentView === 'settings-categories' ? 'bg-blue-50 text-blue-600' : '']">Categories</a></li>
                                <li><a @click="handleNavigation('settings-tags')" :class="['nav-link', currentView === 'settings-tags' ? 'bg-blue-50 text-blue-600' : '']">Tags</a></li>
                                <li><a @click="handleNavigation('settings-advisories')" :class="['nav-link', currentView === 'settings-advisories' ? 'bg-blue-50 text-blue-600' : '']">Advisories</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Main Content Column -->
                <div 
                    class="flex-1 flex flex-col min-w-0 h-full overflow-hidden"
                    :class="currentView ? 'flex' : 'hidden md:flex'">
                    <!-- Mobile back button -->
                    <div 
                        v-if="isMobile && currentView" 
                        class="flex-none relative bg-white px-8 pt-12 pb-4"
                    >
                        <div class="flex items-center gap-4">
                            <button 
                                @click="handleNavigation(null)"
                                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                            >
                                <svg 
                                    class="w-8 h-8" 
                                    viewBox="0 0 24 24" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    stroke-width="2" 
                                    stroke-linecap="round" 
                                    stroke-linejoin="round"
                                >
                                    <path d="M19 12H5"/>
                                    <path d="M12 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Component Content Area -->
                    <div class="flex-1 min-w-0 overflow-y-auto bg-white">
                        <div class="p-8">
                            <component 
                                :is="currentComponent"
                                :event="selectedEvent"
                                :organizer="selectedOrganizer"
                                :community="selectedCommunity"
                                @select-event="handleEventSelect"
                                @select-organizer="handleOrganizerSelect"
                                @select-community="handleCommunitySelect"
                                @approved="clearSelectedEvent"
                                @rejected="clearSelectedEvent"
                                @organizer-approved="clearSelectedOrganizer"
                                @organizer-rejected="clearSelectedOrganizer"
                                @community-approved="clearSelectedCommunity"
                                @community-rejected="clearSelectedCommunity"
                                @update-counts="fetchCounts"
                            ></component>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import ApproveEvents from './Approval/Events.vue'
import EventReview from './Approval/EventReview.vue'
import ApproveOrganizers from './Approval/Organizers.vue'
import OrganizerReview from './Approval/OrganizerReview.vue'
import ApproveCommunities from './Approval/Communities.vue'
import CommunityReview from './Approval/CommunityReview.vue'
import ManageUsers from './Management/Users.vue'
import ManageOrganizers from './Management/Organizers.vue'
import ManageEvents from './Management/Events.vue'
import ManageDocks from './Management/Docks.vue'
import ManageReviews from './Management/Reviews.vue'
import SettingsCategories from './Settings/Categories.vue'
import SettingsTags from './Settings/Tags.vue'
import SettingsAdvisories from './Settings/Advisories.vue'
import ApproveRequests from './Approval/Requests.vue'
import axios from 'axios'

const isMobile = ref(false)
const currentView = ref(null)
const selectedEvent = ref(null)
const selectedOrganizer = ref(null)
const selectedCommunity = ref(null)
const counts = ref({
    events: 0,
    organizers: 0,
    communities: 0,
    requests: 0
})

const checkMobile = () => {
    isMobile.value = window.innerWidth < 768
}

const handleNavigation = (view) => {
    selectedEvent.value = null
    selectedOrganizer.value = null
    selectedCommunity.value = null
    if (view) {
        const url = new URL(window.location)
        url.searchParams.set('view', view)
        url.searchParams.delete('eventId')
        url.searchParams.delete('organizerId')
        url.searchParams.delete('communityId')
        window.history.pushState({}, '', url)
    } else {
        const url = new URL(window.location)
        url.searchParams.delete('view')
        url.searchParams.delete('eventId')
        url.searchParams.delete('organizerId')
        url.searchParams.delete('communityId')
        window.history.pushState({}, '', url)
    }
    currentView.value = view
}

const handlePopState = () => {
    const urlParams = new URLSearchParams(window.location.search)
    const view = urlParams.get('view')
    currentView.value = view || null
    
    const eventId = urlParams.get('eventId')
    const organizerId = urlParams.get('organizerId')
    const communityId = urlParams.get('communityId')
    
    if (eventId) {
        fetchEvent(eventId)
    } else if (organizerId) {
        fetchOrganizer(organizerId)
    } else if (communityId) {
        fetchCommunity(communityId)
    } else {
        selectedEvent.value = null
        selectedOrganizer.value = null
        selectedCommunity.value = null
    }
}

const handleEventSelect = (event) => {
    selectedEvent.value = event
    const url = new URL(window.location)
    url.searchParams.set('eventId', event.id)
    window.history.pushState({}, '', url)
}

const handleOrganizerSelect = (organizer) => {
    selectedOrganizer.value = organizer
    const url = new URL(window.location)
    url.searchParams.set('organizerId', organizer.id)
    window.history.pushState({}, '', url)
}

const handleCommunitySelect = (community) => {
    selectedCommunity.value = community
    const url = new URL(window.location)
    url.searchParams.set('communityId', community.id)
    window.history.pushState({}, '', url)
}

const clearSelectedEvent = () => {
    selectedEvent.value = null
    const url = new URL(window.location)
    url.searchParams.delete('eventId')
    window.history.pushState({}, '', url)
}

const clearSelectedOrganizer = () => {
    selectedOrganizer.value = null
    const url = new URL(window.location)
    url.searchParams.delete('organizerId')
    window.history.pushState({}, '', url)
}

const clearSelectedCommunity = () => {
    selectedCommunity.value = null
    const url = new URL(window.location)
    url.searchParams.delete('communityId')
    window.history.pushState({}, '', url)
}

const fetchEvent = async (eventId) => {
    try {
        const response = await axios.get(`/api/admin/events/${eventId}`);
        selectedEvent.value = {
            ...response.data.event,
            duplicateEvents: response.data.duplicateEvents
        };
    } catch (error) {
        console.error('Error fetching event:', error);
    }
};

const fetchOrganizer = async (organizerId) => {
    try {
        const response = await axios.get(`/api/admin/organizers/${organizerId}`);
        selectedOrganizer.value = response.data.organizer;
    } catch (error) {
        console.error('Error fetching organizer:', error);
    }
};

const fetchCommunity = async (communityId) => {
    try {
        const response = await axios.get(`/api/admin/communities/${communityId}`);
        selectedCommunity.value = response.data.community;
    } catch (error) {
        console.error('Error fetching community:', error);
    }
};

const fetchCounts = async () => {
    try {
        const response = await axios.get('/api/admin/approval-counts')
        counts.value = response.data
    } catch (error) {
        console.error('Error fetching approval counts:', error)
    }
}

onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
    window.addEventListener('popstate', handlePopState)
    
    const urlParams = new URLSearchParams(window.location.search)
    const view = urlParams.get('view')
    if (view) {
        currentView.value = view
    }
    
    const eventId = urlParams.get('eventId')
    const organizerId = urlParams.get('organizerId')
    const communityId = urlParams.get('communityId')
    if (eventId) {
        fetchEvent(eventId)
    } else if (organizerId) {
        fetchOrganizer(organizerId)
    } else if (communityId) {
        fetchCommunity(communityId)
    }

    fetchCounts()
    const countInterval = setInterval(fetchCounts, 60000)
    onUnmounted(() => {
        clearInterval(countInterval)
    })
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    window.removeEventListener('popstate', handlePopState)
})

const currentComponent = computed(() => {
    if (selectedEvent.value) {
        return EventReview
    }
    if (selectedOrganizer.value) {
        return OrganizerReview
    }
    if (selectedCommunity.value) {
        return CommunityReview
    }

    const components = {
        'approve-events': ApproveEvents,
        'approve-organizers': ApproveOrganizers,
        'approve-communities': ApproveCommunities,
        'approve-requests': ApproveRequests,
        'manage-users': ManageUsers,
        'manage-organizers': ManageOrganizers,
        'manage-events': ManageEvents,
        'manage-reviews': ManageReviews,
        'manage-docks': ManageDocks,
        'settings-categories': SettingsCategories,
        'settings-tags': SettingsTags,
        'settings-advisories': SettingsAdvisories,
    }
    return components[currentView.value]
})
</script>

<style scoped>
.nav-link {
    @apply text-3xl md:text-2xl block px-4 py-6 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200 cursor-pointer;
}

.nav-section {
    @apply pb-4 border-b border-gray-200 last:border-0;
}
</style>