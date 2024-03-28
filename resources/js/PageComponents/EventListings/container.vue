<template>
    <div class="event-search relative min-h-[calc(100vh-7rem)]">
        <div class="w-full flex">
            <section :class="{ 'w-0 hidden': searchData.location.fullMap }" class="z-10 relative inline-block w-[59%] min-h-[calc(100vh-8rem)]">
                <div class="inline-block text-left pt-16 pb-4 px-8">
                    <p v-if="events.data && events.data.length">{{ events.total }} immersive events.</p>
                    <p v-else>There are no location based events in {{ searchData.location.name }} with these filters.</p>
                    <h2>{{ searchData.location.name }}</h2>
                </div>
                <Filters @update="updateEvents" @clear="clear" ref="childNav" v-model="searchData" filter="location" :tags="tags" :searched-categories="searchedCategories" :in-person-categories="inPersonCategories" :categories="categories" />
                <div class="w-full">
                    <div class="px-8">
                        <EventList v-if="events.data.length" :user="user" :items="events.data" />
                        <Pagination :list="events" :limit="2" @selectpage="selectPage" />
                    </div>
                </div>
            </section>
            <Map @submit="onSubmit" @fullMap="fullMap" v-model="searchData" :key="mapKey" :events="events.data" />
        </div>
    </div>
</template>

<script>
import { ref, reactive } from 'vue';
import Filters from './filters.vue';
import EventList from '../../GlobalComponents/Albums/vertical.vue';
import Pagination from '../../GlobalComponents/pagination.vue';
import Map from './map.vue';

export default {
    components: { Filters, EventList, Pagination, Map },
    props: ['searchedEvents', 'docks', 'categories', 'user', 'tags', 'mobile', 'searchedCategories', 'searchedTags', 'inPersonCategories'],
    setup(props) {
        const events = ref(props.searchedEvents);
        const mapKey = ref(0);
        const searchData = reactive(initializeSearchData());

        function initializeSearchData() {
            const searchParams = new URL(window.location.href).searchParams;
            return {
                paginate: 1,
                currentTab: 'location',
                searchType: 'inPerson',
                loading: false,
                location: {
                    name: searchParams.get("city") ?? 'Search by City',
                    zoom: initializeZoom(),
                    center: initializeCenter(),
                    mapboundary: initializeBoundaries(),
                    fullMap: false,
                    live: searchParams.get("live") ? JSON.parse(searchParams.get("live")) : false
                },
                searchDates: initializeDates(),
                dates: initializeDates(),
                naturalDate: initializeNaturalDates(),
                tag: props.searchedTags ?? [],
                category: props.searchedCategories ?? [],
                price: initializePrice(),
            };
        }

        function initializePrice() {
            const searchParams = new URL(window.location.href).searchParams;
            return searchParams.get("price0") ? [Number(searchParams.get("price0")), Number(searchParams.get("price1"))] : [0, 100];
        }

        function initializeDates() {
            const searchParams = new URL(window.location.href).searchParams;
            return searchParams.get("start") ? [searchParams.get("start"), searchParams.get("end")] : [];
        }

        function initializeNaturalDates() {
            const searchParams = new URL(window.location.href).searchParams;
            if (searchParams.get("start")) {
                return [this.$dayjs(searchParams.get("start")).format("MMM D"), this.$dayjs(searchParams.get("end")).format("MMM D")];
            }
            return [];
        }

        function initializeZoom() {
            const searchParams = new URL(window.location.href).searchParams;
            return searchParams.get("zoom") ? Number(searchParams.get("zoom")) : 11;
        }

        function initializeCenter() {
            const searchParams = new URL(window.location.href).searchParams;
            if (searchParams.get("Clat")) {
                return {
                    lat: parseFloat(searchParams.get("Clat")),
                    lng: parseFloat(searchParams.get("Clng"))
                };
            }
            return {
                lat: parseFloat(searchParams.get("lat") ?? 0),
                lng: parseFloat(searchParams.get("lng") ?? 0)
            };
        }

        function initializeBoundaries() {
            const searchParams = new URL(window.location.href).searchParams;
            if (!searchParams.get("NElat")) return null;
            return {
                    _northEast: {
                        lat: parseFloat(new URL(window.location.href).searchParams.get("NElat")),
                        lng: parseFloat(new URL(window.location.href).searchParams.get("NElng"))
                    },
                    _southWest: {
                        lat: parseFloat(new URL(window.location.href).searchParams.get("SWlat")),
                        lng: parseFloat(new URL(window.location.href).searchParams.get("SWlng"))
                    }
                }

        },
    };
</script> 