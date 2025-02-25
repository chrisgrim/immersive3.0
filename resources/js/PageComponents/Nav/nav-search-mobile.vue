<template>
    <div class="w-full inline-block relative z-20">
        <div class="flex items-center justify-end pr-8 relative">
        </div>
        <template v-if="search">
            <div class="search-container fixed left-0 top-0 h-screen w-full z-20 bg-[#f4f3f3] flex flex-col">
                <div class="w-full flex justify-center items-center p-4">
                    <button 
                        @click.stop="hideSearch"
                        class="absolute top-4 z-20 left-8 items-center justify-center rounded-full p-0 w-14 h-14 flex border bg-white border-slate-400 hover:bg-black hover:fill-white">
                        <svg class="w-8 h-8 text-red-500">
                            <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                        </svg>
                    </button>
                    <button 
                        @click="search='l'"
                        :class="[
                            'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                            search === 'l' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Location</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'l' }">Location</span>
                    </button>
                    <button 
                        @click="search='e'"
                        :class="[
                            'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                            search === 'e' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Name</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'e' }">Name</span>
                    </button>
                </div>
                <div class="flex-grow overflow-y-auto px-4">
                    <div class="h-full">
                        <Transition name="fade" mode="out-in">
                            <div class="h-full" :key="search">
                                <SearchLocation 
                                    v-if="search==='l'" 
                                    ref="searchLocation"
                                    class="h-full"
                                    :initial-city="city"
                                    :initial-start-date="startDate"
                                    :initial-end-date="endDate"
                                    @update:location="handleLocationUpdate"
                                />
                                <SearchEvent v-if="search==='e'" class="h-full"/>
                            </div>
                        </Transition>
                    </div>
                </div>
                <div v-if="search === 'l'" class="w-full bg-white flex p-12 justify-between items-center mt-auto">
                    <div>
                        <button @click="handleClearAll" class="underline">Clear All</button>
                    </div>
                    <div>
                        <button 
                            @click="handleSearch"
                            :disabled="!city"
                            :class="[
                                'py-4 px-8 rounded-2xl flex gap-4',
                                city ? 'bg-[#ff385c] text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                            ]">
                            <svg class="w-8 h-8" :class="city ? 'fill-white' : 'fill-gray-500'">
                                <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                            </svg>
                            Search
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Default State -->
         
        <template v-else>
            <div class="w-full flex justify-between items-center gap-10 px-0">
                <button 
                    v-if="!isHomePage"
                    @click="handleBack"
                    class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors flex-shrink-0 z-[501]"
                >
                    <svg 
                        class="w-6 h-6" 
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
                <div class="relative flex-1">
                    <div
                        class="w-full absolute top-0 bottom-0 z-[500] cursor-pointer" 
                        @click="openSearch" 
                    />
                    <div class="w-full p-4 border rounded-full flex items-center shadow-custom-3">
                        <p class="w-full flex items-center justify-center text-center truncate">
                            <template v-if="city">
                                <span class="text-black text-1xl font-bold truncate max-w-[40%]">{{ city }}</span>
                                <span class="text-gray-300 mx-4">|</span>
                                <span class="text-black text-1xl truncate max-w-[40%]" :class="{ 'font-bold': startDate }">
                                    {{ startDate ? formatDateDisplay : 'Add dates' }}
                                </span>
                            </template>
                            <template v-else>
                                <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                    <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                                </svg>
                                <span class="text-black font-bold text-2xl">Search</span>
                            </template>
                        </p>
                    </div>
                </div>
                <button 
                    @click="openFilters"
                    class="w-[4.5rem] h-[4.5rem] flex-shrink-0 flex items-center justify-center rounded-full shadow-custom-3 transition-colors"
                    :class="[
                        hasActiveFilters 
                            ? 'bg-black hover:bg-gray-800' 
                            : 'hover:bg-gray-200'
                    ]"
                >
                    <svg 
                        xmlns="http://www.w3.org/2000/svg" 
                        viewBox="0 0 32 32" 
                        aria-hidden="true" 
                        role="presentation" 
                        focusable="false" 
                        style="display: block; fill: none; height: 16px; width: 16px; stroke-width: 2.5; overflow: visible;"
                        :style="{ stroke: hasActiveFilters ? 'white' : 'currentcolor' }"
                    >
                        <path fill="none" d="M7 16H3m26 0H15M29 6h-4m-8 0H3m26 20h-4M7 16a4 4 0 1 0 8 0 4 4 0 0 0-8 0zM17 6a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 20a4 4 0 1 0 8 0 4 4 0 0 0-8 0zm0 0H3"></path>
                    </svg>
                </button>
            </div>
        </template>
    </div>
    <Filters
        v-if="showFilters"
        :show-price="true"
        :price-range="[0, 3900]"
        :max-price="3900"
        @close="showFilters = false"
    />
</template>

<script>
import SearchLocation from './Components/location-search-mobile.vue'
import SearchEvent from './Components/events-search-mobile.vue'
import Filters from './Components/filters.vue'

export default {
    components: { 
        SearchLocation, 
        SearchEvent,
        Filters
    },

    data() {
        const urlParams = new URLSearchParams(window.location.search);
        return {
            search: null,
            city: urlParams.get("city"),
            startDate: urlParams.get("start") ? urlParams.get("start").split(' ')[0] : null,
            endDate: urlParams.get("end") ? urlParams.get("end").split(' ')[0] : null,
            showFilters: false,
        };
    },

    computed: {
        formatDateDisplay() {
            if (!this.startDate) return '';

            const start = new Date(this.startDate);
            const end = new Date(this.endDate);
            
            // Format dates
            const formatDate = (date, isEndDate = false) => {
                const month = date.toLocaleDateString('en-US', { month: 'short' });
                const day = date.getDate();
                
                // If it's the end date and same month as start date, only return day
                if (isEndDate && start.getMonth() === end.getMonth()) {
                    return day;
                }
                
                return `${month} ${day}`;
            };

            // If dates are the same, show only one date
            if (start.getTime() === end.getTime()) {
                return formatDate(start);
            } else {
                return `${formatDate(start)}-${formatDate(end, true)}`;
            }
        },
        
        searchPlaceholder() {
            if (!this.city) return 'Where to?';

            let text = this.city;

            if (this.startDate && this.endDate) {
                const start = new Date(this.startDate);
                const end = new Date(this.endDate);
                
                // Format dates
                const formatDate = (date, isEndDate = false) => {
                    const month = date.toLocaleDateString('en-US', { month: 'short' });
                    const day = date.getDate();
                    
                    // If it's the end date and same month as start date, only return day
                    if (isEndDate && start.getMonth() === end.getMonth()) {
                        return day;
                    }
                    
                    return `${month} ${day}`;
                };

                // If dates are the same, show only one date
                if (start.getTime() === end.getTime()) {
                    text += ` · ${formatDate(start)}`;
                } else {
                    text += ` · ${formatDate(start)}-${formatDate(end, true)}`;
                }
            }

            return text;
        },
        isHomePage() {
            return window.location.pathname === '/';
        }
    },

    methods: {
        openSearch() {
            this.search = 'l'
        },
        hideSearch() {
            console.log('Hiding search, resetting to URL state');
            const urlParams = new URLSearchParams(window.location.search);
            
            this.search = null;
            this.city = urlParams.get("city");
            this.startDate = urlParams.get("start") ? urlParams.get("start").split(' ')[0] : null;
            this.endDate = urlParams.get("end") ? urlParams.get("end").split(' ')[0] : null;
            
            console.log('State after hide:', {
                city: this.city,
                startDate: this.startDate,
                endDate: this.endDate
            });
        },
        handleLocationUpdate(value) {
            console.log('Parent handleLocationUpdate received:', value);
            if (typeof value === 'string') {
                // Just update the city name, don't touch the URL
                this.city = value;
            } else if (value && typeof value === 'object') {
                // Full location data with coordinates - update URL and everything
                this.city = value.city;
                // Update URL with new coordinates and city
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.set('city', value.city);
                currentParams.set('lat', value.lat);
                currentParams.set('lng', value.lng);
                
                // Handle dates
                this.startDate = value.start || null;
                this.endDate = value.end || null;
                
                if (this.startDate && this.endDate) {
                    currentParams.set('start', this.startDate + ' 00:00:00');
                    currentParams.set('end', this.endDate + ' 00:00:00');
                } else {
                    currentParams.delete('start');
                    currentParams.delete('end');
                }
                
                // Update URL without page reload
                window.history.replaceState({}, '', `${window.location.pathname}?${currentParams.toString()}`);
            } else {
                // Clear everything
                this.city = null;
                this.startDate = null;
                this.endDate = null;
                
                // Remove coordinates from URL
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.delete('city');
                currentParams.delete('lat');
                currentParams.delete('lng');
                currentParams.delete('start');
                currentParams.delete('end');
                window.history.replaceState({}, '', `${window.location.pathname}?${currentParams.toString()}`);
            }
        },
        handleSearch() {
            // This will trigger the search in the child component
            window.dispatchEvent(new CustomEvent('trigger-search'));
        },
        handleClearAll() {
            console.log('Parent handleClearAll called');
            
            // First clear all state
            this.city = null;
            this.startDate = null;
            this.endDate = null;
            
            console.log('Parent state cleared:', {
                city: this.city,
                startDate: this.startDate,
                endDate: this.endDate
            });
            
            // Then tell child to clear its state
            if (this.$refs.searchLocation) {
                console.log('Calling child clearState');
                this.$refs.searchLocation.clearState(true); // Pass true to indicate this is a clear operation
            }
        },
        openFilters() {
            this.showFilters = true;
        },
        handleBack() {
            window.location.href = '/';
        }
    },

    mounted() {
        window.addEventListener('hide-search', this.hideSearch);
    },

    beforeUnmount() {
        window.removeEventListener('hide-search', this.hideSearch);
    }
}
</script>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
