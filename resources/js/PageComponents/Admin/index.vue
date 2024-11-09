<template>
    <div class="admin-container flex min-h-screen bg-white">
        <!-- Mobile Nav Toggle -->
        <button 
            @click="toggleNav" 
            class="fixed top-4 left-4 z-50 md:hidden bg-white p-2 rounded-lg"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Side Navigation -->
        <nav 
            :class="{'translate-x-0': isNavOpen, '-translate-x-full': !isNavOpen}"
            class="fixed md:static w-80 h-screen bg-white border-r border-gray-200 transition-transform duration-300 ease-in-out md:translate-x-0 z-40"
        >
            <!-- Add EI Logo/Text Link -->
            <div class="p-4 border-b border-gray-200">
                <a 
                    href="/" 
                    class="text-2xl font-bold text-blue-600 hover:text-blue-800 transition-colors duration-200"
                >
                    EI
                </a>
            </div>

            <div class="p-4 space-y-6 overflow-y-auto h-[calc(100vh-68px)]"> <!-- Adjusted height to account for logo -->
                <!-- Approval Section -->
                <div class="nav-section">
                    <h2 class="text-lg font-semibold text-gray-700 mb-2">Approval Queue</h2>
                    <ul class="space-y-2">
                        <li><a @click="currentView = 'approve-events'" :class="['nav-link', currentView === 'approve-events' ? 'bg-blue-50 text-blue-600' : '']">Events</a></li>
                        <li><a @click="currentView = 'approve-organizers'" :class="['nav-link', currentView === 'approve-organizers' ? 'bg-blue-50 text-blue-600' : '']">Organizers</a></li>
                        <li><a @click="currentView = 'approve-communities'" :class="['nav-link', currentView === 'approve-communities' ? 'bg-blue-50 text-blue-600' : '']">Communities</a></li>
                    </ul>
                </div>

                <!-- Management Section -->
                <div class="nav-section">
                    <h2 class="text-lg font-semibold text-gray-700 mb-2">Management</h2>
                    <ul class="space-y-2">
                        <li><a @click="currentView = 'manage-events'" :class="['nav-link', currentView === 'manage-events' ? 'bg-blue-50 text-blue-600' : '']">Events</a></li>
                        <li><a @click="currentView = 'manage-users'" :class="['nav-link', currentView === 'manage-users' ? 'bg-blue-50 text-blue-600' : '']">Users</a></li>
                        <li><a @click="currentView = 'manage-organizers'" :class="['nav-link', currentView === 'manage-organizers' ? 'bg-blue-50 text-blue-600' : '']">Organizers</a></li>
                        <li><a @click="currentView = 'manage-reviews'" :class="['nav-link', currentView === 'manage-reviews' ? 'bg-blue-50 text-blue-600' : '']">Reviews</a></li>
                    </ul>
                </div>

                <!-- Settings Section -->
                <div class="nav-section">
                    <h2 class="text-lg font-semibold text-gray-700 mb-2">Settings</h2>
                    <ul class="space-y-2">
                        <li><a @click="currentView = 'settings-categories'" :class="['nav-link', currentView === 'settings-categories' ? 'bg-blue-50 text-blue-600' : '']">Categories</a></li>
                        <li><a @click="currentView = 'settings-tags'" :class="['nav-link', currentView === 'settings-tags' ? 'bg-blue-50 text-blue-600' : '']">Tags</a></li>
                        <li><a @click="currentView = 'settings-advisories'" :class="['nav-link', currentView === 'settings-advisories' ? 'bg-blue-50 text-blue-600' : '']">Advisories</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:p-8 bg-white">
            <!-- Back Button (when viewing event) -->
            <button 
                v-if="selectedEvent"
                @click="clearSelectedEvent"
                class="mb-4 flex items-center text-gray-600 hover:text-gray-800"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to List
            </button>

            <!-- Dynamic Component -->
            <component 
                :is="currentComponent"
                :event="selectedEvent"
                @select-event="handleEventSelect"
                @approved="clearSelectedEvent"
                @rejected="clearSelectedEvent"
            ></component>
        </main>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import ApproveEvents from './Approval/Events.vue'
import EventReview from './Approval/EventReview.vue'
import ApproveOrganizers from './Approval/Organizers.vue'
import ApproveCommunities from './Approval/Communities.vue'
import ManageUsers from './Management/Users.vue'
import ManageOrganizers from './Management/Organizers.vue'
import ManageEvents from './Management/Events.vue'
import ManageReviews from './Management/Reviews.vue'
import SettingsCategories from './Settings/Categories.vue'
import SettingsTags from './Settings/Tags.vue'
import SettingsAdvisories from './Settings/Advisories.vue'

const isNavOpen = ref(false)
const currentView = ref('approve-events')
const selectedEvent = ref(null)

const currentComponent = computed(() => {
    if (selectedEvent.value) {
        return EventReview
    }

    const components = {
        'approve-events': ApproveEvents,
        'approve-organizers': ApproveOrganizers,
        'approve-communities': ApproveCommunities,
        'manage-users': ManageUsers,
        'manage-organizers': ManageOrganizers,
        'manage-events': ManageEvents,
        'manage-reviews': ManageReviews,
        'settings-categories': SettingsCategories,
        'settings-tags': SettingsTags,
        'settings-advisories': SettingsAdvisories,
    }
    return components[currentView.value]
})

const handleEventSelect = (event) => {
    selectedEvent.value = event
}

const clearSelectedEvent = () => {
    selectedEvent.value = null
}

const toggleNav = () => {
    isNavOpen.value = !isNavOpen.value
}
</script>

<style scoped>
.nav-link {
    @apply block px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200 cursor-pointer;
}

.nav-section {
    @apply pb-4 border-b border-gray-200 last:border-0;
}
</style>