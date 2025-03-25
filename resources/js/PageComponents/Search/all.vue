<template>
    <div class="max-w-screen-5xl mx-auto px-10 md:px-32 py-16">
        <!-- Main Content -->
        <event-grid 
            :items="events.data"
            :columns="6"
        />

        <!-- Pagination -->
        <div v-if="events.last_page > 1" class="mt-12">
            <Pagination 
                v-if="events"
                class="mt-6"
                :pagination="events"
                @paginate="handlePageChange"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import EventGrid from '@/GlobalComponents/Grid/event-grid.vue'
import Pagination from '@/GlobalComponents/pagination.vue'
import eventStore from '@/Stores/EventStore.vue'

const props = defineProps({
    searchedEvents: {
        type: Object,
        required: true
    },
})

const events = ref({
    data: props.searchedEvents?.data || [],
    total: props.searchedEvents?.total || 0,
    current_page: props.searchedEvents?.current_page || 1,
    last_page: props.searchedEvents?.last_page || 1,
    from: props.searchedEvents?.from || 0,
    to: props.searchedEvents?.to || 0,
    per_page: props.searchedEvents?.per_page || 20
})


const unsubscribe = ref(null);
const isInitialLoad = ref(true)

const handlePageChange = (page) => {
    eventStore.changePage(page);
    window.scrollTo(0, 0);
}

onMounted(() => {
    eventStore.initializeFromUrl();
    
    if (eventStore.isFirstFetch) {
        eventStore.setInitialData(props.maxPrice, {
            data: props.searchedEvents?.data || [],
            total: props.searchedEvents?.total || 0,
            current_page: props.searchedEvents?.current_page || 1,
            per_page: props.searchedEvents?.per_page || 20,
            from: props.searchedEvents?.from,
            to: props.searchedEvents?.to,
            last_page: props.searchedEvents?.last_page || 1,
            loading: false
        });
    }
    
    unsubscribe.value = eventStore.subscribe(state => {
        
        if (eventStore.isUpdating && !isInitialLoad.value) return;
        
        if (state) {
            events.value = {
                data: state.events.data,
                total: state.events.total,
                current_page: state.events.current_page,
                last_page: state.events.last_page,
                from: state.events.from,
                to: state.events.to,
                per_page: state.events.per_page
            };
        }
        
        isInitialLoad.value = false;
    });
    
    if (window.location.pathname === '/index/search') {
        eventStore.fetchEvents();
    }
})

onUnmounted(() => {
    if (unsubscribe.value) {
        unsubscribe.value();
    }
})

const imageUrl = computed(() => import.meta.env.VITE_IMAGE_URL)
</script>